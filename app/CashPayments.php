<?php
declare(strict_types=1);

final class CashPayments {
    public const STATUS = "CASE WHEN t.state='paid' AND p.voided_at IS NOT NULL THEN 'voided' ELSE t.state END";
    public const FROM = 'cash_requests t JOIN reservations r ON r.id=t.reservation_id JOIN guests g ON g.id=t.guest_id LEFT JOIN payments p ON p.id=t.payment_id LEFT JOIN users collector ON collector.id=p.recorded_by';
    public const COLUMNS = "t.*,r.reference booking,t.guest_name guest,r.status booking_status,r.total booking_total,COALESCE((SELECT SUM(paid.amount) FROM payments paid WHERE paid.reservation_id=r.id AND paid.voided_at IS NULL),0) paid,p.created_at received_at,p.voided_at,p.void_reason,collector.name received_by";

    private static function balance(array $r): int {
        if (!in_array($r['status'],['pending','confirmed','checked_in','checked_out'],true)) {
            throw new HttpError('This booking is closed. A cash payment cannot be requested or received.',409);
        }
        $paid=DB::one('SELECT COALESCE(SUM(amount),0) paid FROM payments WHERE reservation_id=? AND voided_at IS NULL',[$r['id']]);
        return money_cents($r['total'])-money_cents($paid['paid']);
    }

    public static function create(array $u,array $d): array {
        Auth::require($u,'payments.manage');
        $rid=integer($d,'reservation_id');$amount=money_cents($d['amount']??'');$notes=field($d,'notes',500,false);
        if($amount<=0)throw new HttpError('Enter a cash amount greater than zero.');
        return DB::transaction(function()use($u,$rid,$amount,$notes){
            $r=DB::one('SELECT * FROM reservations WHERE id=? FOR UPDATE',[$rid]);
            if(!$r)throw new HttpError('Reservation not found.',404);
            if($amount>self::balance($r))throw new HttpError('The requested amount exceeds the current balance. Refresh the booking.');
            $existing=DB::one("SELECT reference FROM cash_requests WHERE reservation_id=? AND state='pending'",[$rid]);
            if($existing)throw new HttpError('A pending slip already exists: '.$existing['reference'].'. Open it or cancel it before generating another.',409);
            $g=DB::one('SELECT name,user_id FROM guests WHERE id=?',[$r['guest_id']]);
            $f=DB::one('SELECT name FROM facilities WHERE id=?',[$r['facility_id']]);
            $ref='CASH-'.strtoupper(bin2hex(random_bytes(6)));
            DB::run('INSERT INTO cash_requests(reference,reservation_id,guest_id,guest_name,facility_name,arrival,departure,total_at_issue,amount,notes,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[$ref,$rid,$r['guest_id'],$g['name'],$f['name'],$r['arrival'],$r['departure'],$r['total'],decimal($amount),$notes,$u['id']]);
            $id=DB::id();audit('generated','cash_requests',$id,$ref.' for '.$r['reference']);
            if($g['user_id'])notify_user((int)$g['user_id'],$ref.' for '.$r['reference'].': pay PHP '.decimal($amount).' in cash at reception. Open Cash payments to view your slip.');
            return ['id'=>$id,'reference'=>$ref];
        });
    }

    public static function act(array $u,int $id,array $d): array {
        Auth::require($u,'payments.manage');
        $action=choice($d,'action',['receive','cancel']);
        $reason=$action==='cancel'?field($d,'reason',500):'';
        return DB::transaction(function()use($u,$id,$action,$reason){
            $stub=DB::one('SELECT reservation_id FROM cash_requests WHERE id=?',[$id]);
            if(!$stub)throw new HttpError('Payment slip not found.',404);
            // Always lock the booking first, matching manual payments and reservation edits.
            $r=DB::one('SELECT * FROM reservations WHERE id=? FOR UPDATE',[$stub['reservation_id']]);
            $slip=DB::one('SELECT * FROM cash_requests WHERE id=? FOR UPDATE',[$id]);
            if($slip['state']==='paid'&&$action==='receive'){
                $p=DB::one('SELECT voided_at FROM payments WHERE id=?',[$slip['payment_id']]);
                if($p['voided_at'])throw new HttpError('This receipt was voided. Generate a new slip if cash is still due.',409);
                return ['id'=>$id,'payment_id'=>$slip['payment_id']]; // Safe retry: never record cash twice.
            }
            if($slip['state']!=='pending')throw new HttpError('This slip is no longer pending. Refresh to see its current status.',409);
            if($action==='cancel'){
                self::closePending((int)$r['id'],$reason);
                return ['id'=>$id];
            }
            if($slip['guest_id']!=$r['guest_id']||money_cents($slip['amount'])>self::balance($r))throw new HttpError('The booking or balance has changed. Cancel this slip and generate a new one.',409);
            DB::run("INSERT INTO payments(reservation_id,amount,method,reference,notes,recorded_by) VALUES(?,?,'cash',?,?,?)",[$r['id'],$slip['amount'],$slip['reference'],$slip['notes'],$u['id']]);
            $pid=DB::id();
            DB::run("UPDATE cash_requests SET state='paid',payment_id=? WHERE id=?",[$pid,$id]);
            audit('create','payments',$pid,'Cash received against '.$slip['reference']);
            audit('cash_received','cash_requests',$id,'Payment #'.$pid);
            $g=DB::one('SELECT user_id FROM guests WHERE id=?',[$slip['guest_id']]);
            if($g['user_id'])notify_user((int)$g['user_id'],'Cash received: PHP '.$slip['amount'].' for '.$r['reference'].'. Receipt '.$slip['reference'].' is ready in Cash payments.');
            return ['id'=>$id,'payment_id'=>$pid];
        });
    }

    // Caller holds the booking lock and transaction. Old slips must never remain payable.
    public static function closePending(int $rid,string $reason): void {
        $slips=DB::all("SELECT t.id,t.reference,g.user_id FROM cash_requests t JOIN guests g ON g.id=t.guest_id WHERE t.reservation_id=? AND t.state='pending'",[$rid]);
        DB::run("UPDATE cash_requests SET state='cancelled',cancelled_reason=?,cancelled_at=NOW() WHERE reservation_id=? AND state='pending'",[$reason,$rid]);
        foreach($slips as $s){
            audit('cancelled','cash_requests',(int)$s['id'],$reason);
            if($s['user_id'])notify_user((int)$s['user_id'],$s['reference'].' cancelled: '.$reason);
        }
    }
}
