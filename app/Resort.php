<?php
declare(strict_types=1);
final class Resort {
    public const READ = ['reservations'=>'reservations.manage','guests'=>'guests.manage','facilities'=>'facilities.read','payments'=>'payments.manage','cash_requests'=>'payments.manage','maintenance'=>'maintenance.manage','devices'=>'monitoring.read','readings'=>'monitoring.read','users'=>'users.manage','audit'=>'audit.read','notifications'=>''];
    public const ACTIVE = "('pending','confirmed','checked_in')";
    public static function access(array $u,string $m): void {
        if(!array_key_exists($m,self::READ))throw new HttpError('Module not found.',404);
        if($m==='notifications'||($u['role']==='guest'&&in_array($m,['reservations','payments','cash_requests','facilities'])))return;
        Auth::require($u,self::READ[$m]);
    }
    public static function list(array $u,string $m,array $q=[],bool $export=false): array {
        self::access($u,$m); $args=[];$where=['1=1'];
        $defs=[
            'cash_requests'=>[CashPayments::FROM,CashPayments::COLUMNS.','.CashPayments::STATUS.' status',['t.reference','r.reference','t.guest_name'],['reference','amount','created_at']],
            'reservations'=>["reservations t JOIN guests g ON g.id=t.guest_id JOIN facilities f ON f.id=t.facility_id", "t.*,g.name guest,g.user_id guest_user_id,f.name facility,f.image_path facility_image_path,COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.reservation_id=t.id AND p.voided_at IS NULL),0) paid",['t.reference','g.name','f.name'],['reference','arrival','departure','total','status','created_at']],
            'guests'=>['guests t','t.*',['t.name','t.email','t.phone'],['name','email','created_at']],
            'facilities'=>['facilities t',"t.*,(SELECT COUNT(*) FROM reservations r WHERE r.facility_id=t.id AND r.status='checked_in') occupied",['t.name','t.description'],['name','type','capacity','nightly_rate','status']],
            'payments'=>['payments t JOIN reservations r ON r.id=t.reservation_id JOIN guests g ON g.id=r.guest_id',"t.*,r.reference booking,g.name guest,IF(t.voided_at IS NULL,'recorded','voided') status",['r.reference','t.reference','g.name'],['amount','method','created_at']],
            'maintenance'=>['maintenance t JOIN facilities f ON f.id=t.facility_id LEFT JOIN users u ON u.id=t.assigned_to','t.*,f.name facility,u.name assignee',['t.title','f.name'],['title','priority','status','created_at']],
            'devices'=>['devices t JOIN facilities f ON f.id=t.facility_id',"t.id,t.facility_id,t.name,t.active,t.min_temp,t.max_temp,t.stale_minutes,t.last_seen,t.created_at,t.updated_at,f.name facility,(SELECT temperature FROM readings WHERE device_id=t.id ORDER BY id DESC LIMIT 1) temperature,CASE WHEN t.active=0 THEN 'disabled' WHEN t.last_seen IS NULL THEN 'no_data' WHEN TIMESTAMPDIFF(SECOND,t.last_seen,NOW())>t.stale_minutes*60 THEN 'stale' ELSE 'online' END status",['t.name','f.name'],['name','last_seen','created_at']],
            'readings'=>['readings t JOIN devices d ON d.id=t.device_id JOIN facilities f ON f.id=d.facility_id','t.*,d.name device,f.name facility',['d.name','f.name','t.sample_id'],['temperature','received_at']],
            'users'=>['users t JOIN roles r ON r.id=t.role_id',"t.id,t.name,t.email,t.role_id,t.active,t.created_at,t.updated_at,r.name role,IF(t.active=1,'active','inactive') status",['t.name','t.email'],['name','email','created_at']],
            'audit'=>['audit_logs t LEFT JOIN users u ON u.id=t.user_id','t.*,u.name actor',['t.action','t.entity','t.details','u.name'],['action','entity','created_at']],
            'notifications'=>['notifications t',"t.*,IF(t.read_at IS NULL,'unread','read') status",['t.message'],['created_at']],
        ];
        [$from,$cols,$search,$sorts]=$defs[$m];
        if($u['role']==='guest') {
            if($m==='reservations'){$where[]='g.user_id=?';$args[]=$u['id'];}
            if(in_array($m,['payments','cash_requests'])){$where[]='g.user_id=?';$args[]=$u['id'];}
            if($m==='facilities')$where[]="t.type IN ('room','cottage') AND t.archived_at IS NULL";
        }
        if($m==='notifications'){$where[]='t.user_id=?';$args[]=$u['id'];}
        if(in_array($m,['guests','facilities']) && !($m==='facilities'&&$u['role']==='guest'))$where[]='t.archived_at IS '.(($q['archived']??'0')==='1'?'NOT NULL':'NULL');
        if(isset($q['id'])){$where[]='t.id=?';$args[]=integer($q,'id');}
        if(isset($q['reservation_id'])&&$m==='cash_requests'){$where[]='t.reservation_id=?';$args[]=integer($q,'reservation_id');}
        if(isset($q['guest_id'])&&$m==='reservations'){$where[]='t.guest_id=?';$args[]=integer($q,'guest_id');}
        if(isset($q['device_id'])&&$m==='readings'){$where[]='t.device_id=?';$args[]=integer($q,'device_id');}
        if($m==='reservations'&&!empty($q['operation'])){
            $operations=[
                'arrivals'=>"t.arrival=CURDATE() AND t.status IN ('pending','confirmed')",
                'departures'=>"t.departure=CURDATE() AND t.status='checked_in'",
                'overdue'=>"t.departure<CURDATE() AND t.status='checked_in'",
                'outstanding'=>"t.status IN ('pending','confirmed','checked_in','checked_out') AND t.total>COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.reservation_id=t.id AND p.voided_at IS NULL),0)",
            ];
            $where[]=$operations[choice($q,'operation',array_keys($operations))];
        }
        if(!empty($q['search'])){ $term='%'.field($q,'search',100).'%';$where[]='('.implode(' OR ',array_map(fn($s)=>"$s LIKE ?",$search)).')';foreach($search as $_)$args[]=$term; }
        if(!empty($q['status'])) {
            $status=field($q,'status',30);
            if(in_array($m,['reservations','facilities','maintenance'])){$where[]='t.status=?';$args[]=$status;}
            elseif($m==='cash_requests'){$where[]=CashPayments::STATUS.'=?';$args[]=$status;}
            elseif($m==='payments')$where[]='t.voided_at IS '.($status==='voided'?'NOT NULL':'NULL');
            elseif($m==='users'){$where[]='t.active=?';$args[]=$status==='active'?1:0;}
            elseif($m==='notifications')$where[]='t.read_at IS '.($status==='read'?'NOT NULL':'NULL');
        }
        $datecol=$m==='reservations'?'t.arrival':($m==='readings'?'t.received_at':'t.created_at');
        self::validateRange($q);
        foreach(['from'=>'>=','to'=>'<='] as $k=>$op)if(!empty($q[$k])) {$where[]="DATE($datecol) $op ?";$args[]=$q[$k];}
        $sqlWhere=implode(' AND ',$where);$total=(int)DB::one("SELECT COUNT(*) n FROM $from WHERE $sqlWhere",$args)['n'];
        $summary=null;
        if(($q['summary']??'')==='1'&&in_array($m,['reservations','payments'])){
            $expression=$m==='payments'?"COALESCE(SUM(IF(t.voided_at IS NULL,t.amount,0)),0) amount":"COALESCE(SUM(t.total),0) total,COALESCE(SUM(COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.reservation_id=t.id AND p.voided_at IS NULL),0)),0) paid";
            $summary=DB::one("SELECT $expression FROM $from WHERE $sqlWhere",$args);
        }
        $sort=in_array($q['sort']??'',$sorts,true)?$q['sort']:'id';$dir=($q['direction']??'desc')==='asc'?'ASC':'DESC';
        $size=$export?10000:min(100,max(1,(int)($q['per_page']??10)));$page=min(max(1,(int)($q['page']??1)),max(1,(int)ceil($total/$size)));$offset=($page-1)*$size;
        $rows=DB::all("SELECT $cols FROM $from WHERE $sqlWhere ORDER BY t.$sort $dir LIMIT $size OFFSET $offset",$args);
        foreach($rows as &$r){
            if($m==='reservations'){ $r['balance']=decimal(money_cents($r['total'])-money_cents($r['paid']));$r['payment_status']=(float)$r['balance']<=0?'paid':((float)$r['paid']>0?'partial':'unpaid'); }
            if($m==='cash_requests')$r['balance']=decimal(money_cents($r['booking_total'])-money_cents($r['paid']));
            if($m==='devices')$r['alert']=$r['temperature']!==null&&($r['temperature']<$r['min_temp']||$r['temperature']>$r['max_temp']);
        }
        return ['rows'=>$rows,'total'=>$total,'page'=>$page,'per_page'=>$size,'pages'=>max(1,(int)ceil($total/$size)),'summary'=>$summary];
    }
    private static function validateRange(array $q): void {
        foreach(['from','to'] as $key)if(!empty($q[$key])){
            $value=field($q,$key,10);$date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
            if(!$date||$date->format('Y-m-d')!==$value)throw new HttpError('Choose valid filter dates.');
        }
        if(!empty($q['from'])&&!empty($q['to'])&&$q['from']>$q['to'])throw new HttpError('The end date must not precede the start date.');
    }
    public static function trend(array $u,array $q): array {
        Auth::require($u,'monitoring.read');
        $q['from']=empty($q['from'])?date('Y-m-d',strtotime('-6 days')):$q['from'];
        $q['to']=empty($q['to'])?date('Y-m-d'):$q['to'];self::validateRange($q);
        if((new DateTimeImmutable($q['from']))->diff(new DateTimeImmutable($q['to']))->days>30)throw new HttpError('Choose up to 31 days for the temperature trend.');
        $where=['t.received_at>=?','t.received_at<DATE_ADD(?,INTERVAL 1 DAY)'];$args=[$q['from'],$q['to']];
        if(!empty($q['device_id'])){$where[]='t.device_id=?';$args[]=integer($q,'device_id');}
        if(!empty($q['search'])){$where[]='(d.name LIKE ? OR f.name LIKE ? OR t.sample_id LIKE ?)';$term='%'.field($q,'search',100).'%';array_push($args,$term,$term,$term);}
        $filter=implode(' AND ',$where);
        $rows=DB::all("SELECT t.device_id,d.name device,f.name facility,DATE_FORMAT(t.received_at,'%Y-%m-%d %H:00:00') bucket,AVG(t.temperature) temperature,MIN(t.temperature) minimum,MAX(t.temperature) maximum,COUNT(*) samples FROM readings t JOIN devices d ON d.id=t.device_id JOIN facilities f ON f.id=d.facility_id WHERE $filter GROUP BY t.device_id,d.name,f.name,bucket ORDER BY bucket,t.device_id LIMIT 10001",$args);
        if(count($rows)>10000)throw new HttpError('Narrow the temperature date range or select a device.');
        return ['rows'=>$rows,'from'=>$q['from'],'to'=>$q['to']];
    }
    public static function lookups(array $u): array {
        $r=['facilities'=>DB::all('SELECT id,name,type,capacity,nightly_rate,status,image_path FROM facilities WHERE archived_at IS NULL ORDER BY name')];
        if(Auth::can($u,'guests.manage'))$r['guests']=DB::all('SELECT id,name,email FROM guests WHERE archived_at IS NULL ORDER BY name');
        if(Auth::can($u,'maintenance.manage'))$r['staff']=DB::all('SELECT id,name FROM users WHERE active=1 AND role_id IN (1,3) ORDER BY name');
        if(Auth::can($u,'payments.manage'))$r['reservations']=DB::all("SELECT r.id,r.total,COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.reservation_id=r.id AND p.voided_at IS NULL),0) paid,CONCAT(r.reference,' · ',g.name) name FROM reservations r JOIN guests g ON g.id=r.guest_id WHERE r.status IN ('pending','confirmed','checked_in','checked_out') AND r.total>COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.reservation_id=r.id AND p.voided_at IS NULL),0) ORDER BY r.id DESC");
        return $r;
    }
    public static function availability(array $q,?array $u=null): array {
        [$a,$b]=dates($q);$count=integer($q,'guests_count',1,1000);
        $exclude=0;
        if(isset($q['reservation_id'])){
            if(!$u)throw new HttpError('Sign in to check an existing reservation.',401);
            $exclude=integer($q,'reservation_id');$r=DB::one('SELECT * FROM reservations WHERE id=?',[$exclude]);
            if(!$r)throw new HttpError('Reservation not found.',404);self::owner($u,$r);
            if($r['status']!=='pending')throw new HttpError('Only pending reservations can be edited.');
        }
        return DB::all("SELECT f.id,f.name,f.type,f.capacity,f.nightly_rate,f.description,f.image_path FROM facilities f WHERE f.archived_at IS NULL AND f.status='available' AND f.type IN ('room','cottage') AND f.capacity>=? AND NOT EXISTS(SELECT 1 FROM reservations r WHERE r.facility_id=f.id AND r.status IN ".self::ACTIVE." AND r.arrival<? AND r.departure>? AND r.id<>?) ORDER BY f.nightly_rate",[$count,$b,$a,$exclude]);
    }
    private static function owner(array $u,array $r): void {if($u['role']==='guest'){ $g=DB::one('SELECT user_id FROM guests WHERE id=?',[$r['guest_id']]);if((int)$g['user_id']!==$u['id'])throw new HttpError('Reservation not found.',404); }else Auth::require($u,'reservations.manage');}
    public static function reserve(array $u,array $d,?int $id=null): array {
        if($u['role']!=='guest')Auth::require($u,'reservations.manage');
        [$a,$b,$n]=dates($d);$fid=integer($d,'facility_id');$count=integer($d,'guests_count',1,1000);$notes=field($d,'notes',1000,false);
        return DB::transaction(function()use($u,$d,$id,$a,$b,$n,$fid,$count,$notes){
            // Serialize allocation changes across the small local resort inventory.
            DB::all('SELECT id FROM facilities ORDER BY id FOR UPDATE');
            $old=$id?DB::one('SELECT * FROM reservations WHERE id=? FOR UPDATE',[$id]):null;
            if($id&&!$old)throw new HttpError('Reservation not found.',404);
            if($old){self::owner($u,$old);if($old['status']!=='pending')throw new HttpError('Only pending reservations can be edited.');}
            $g=$u['role']==='guest'?DB::one('SELECT * FROM guests WHERE user_id=? AND archived_at IS NULL FOR UPDATE',[$u['id']]):DB::one('SELECT * FROM guests WHERE id=? AND archived_at IS NULL FOR UPDATE',[integer($d,'guest_id')]);
            if(!$g)throw new HttpError('Choose an active guest.');
            $f=DB::one("SELECT * FROM facilities WHERE id=? AND archived_at IS NULL AND status='available' AND type IN ('room','cottage')",[$fid]);
            if(!$f||$count>$f['capacity'])throw new HttpError('This unit is unavailable or exceeds its capacity.');
            if(DB::one("SELECT id FROM reservations WHERE facility_id=? AND status IN ".self::ACTIVE." AND arrival<? AND departure>? AND id<>? LIMIT 1",[$fid,$b,$a,$id??0]))throw new HttpError('The selected unit is already reserved for these dates.',409);
            $rate=$old&&$old['facility_id']===$fid?$old['nightly_rate']:$f['nightly_rate'];$total=decimal(money_cents($rate)*$n);
            if(isset($d['expected_nightly_rate'])&&money_cents($d['expected_nightly_rate'])!==money_cents($rate))throw new HttpError('The rate has changed. Review the current price before submitting again.',409);
            if($old){$paid=DB::one('SELECT COALESCE(SUM(amount),0) paid FROM payments WHERE reservation_id=? AND voided_at IS NULL',[$id]);if(money_cents($total)<money_cents($paid['paid']))throw new HttpError('The new total cannot be below recorded payments.');DB::run('UPDATE reservations SET guest_id=?,facility_id=?,arrival=?,departure=?,guests_count=?,nightly_rate=?,total=?,notes=? WHERE id=?',[$g['id'],$fid,$a,$b,$count,$rate,$total,$notes,$id]);}
            else {DB::run('INSERT INTO reservations(reference,guest_id,facility_id,arrival,departure,guests_count,nightly_rate,total,notes,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)',['R360-'.strtoupper(bin2hex(random_bytes(5))),$g['id'],$fid,$a,$b,$count,$rate,$total,$notes,$u['id']]);$id=DB::id();}
            if($old)CashPayments::closePending($id,'The booking was edited. Please request an updated slip.');
            audit($old?'update':'create','reservations',$id);$r=DB::one('SELECT reference FROM reservations WHERE id=?',[$id]);notify_team($r['reference'].($old?' updated.':' awaiting confirmation.'));if($g['user_id'])notify_user((int)$g['user_id'],$r['reference'].' saved. Status: pending.');return ['id'=>$id,'reference'=>$r['reference'],'status'=>'pending','total'=>$total];
        });
    }
    public static function reservationStatus(array $u,int $id,array $d): void {
        $status=choice($d,'status',['confirmed','checked_in','checked_out','cancelled','rejected']);
        DB::transaction(function()use($u,$id,$status){
            DB::all('SELECT id FROM facilities ORDER BY id FOR UPDATE');$r=DB::one('SELECT * FROM reservations WHERE id=? FOR UPDATE',[$id]);if(!$r)throw new HttpError('Reservation not found.',404);self::owner($u,$r);
            if($u['role']==='guest'&&$status!=='cancelled')throw new HttpError('Guests can only cancel a reservation.',403);
            $allowed=['pending'=>['confirmed','cancelled','rejected'],'confirmed'=>['checked_in','cancelled'],'checked_in'=>['checked_out']];
            if(!in_array($status,$allowed[$r['status']]??[],true))throw new HttpError('This status transition is not allowed.',409);
            if($status==='checked_in' && ($r['arrival']>date('Y-m-d')||$r['departure']<=date('Y-m-d')))throw new HttpError('Check-in must occur during the reserved stay.');
            if($status==='checked_in'&&DB::one("SELECT id FROM reservations WHERE facility_id=? AND status='checked_in' AND id<>?",[$r['facility_id'],$id]))throw new HttpError('The previous occupant must check out first.',409);
            $paid=DB::one('SELECT COALESCE(SUM(amount),0) paid FROM payments WHERE reservation_id=? AND voided_at IS NULL',[$id]);
            if($status==='checked_out'&&money_cents($paid['paid'])!==money_cents($r['total']))throw new HttpError('Settle the outstanding balance before check-out.');
            DB::run('UPDATE reservations SET status=? WHERE id=?',[$status,$id]);audit('status_changed','reservations',$id,$r['status'].' → '.$status);
            $g=DB::one('SELECT user_id FROM guests WHERE id=?',[$r['guest_id']]);if($g['user_id'])notify_user((int)$g['user_id'],$r['reference'].' is now '.str_replace('_',' ',$status).'.');
            if(in_array($status,['cancelled','rejected']))CashPayments::closePending($id,'The booking is '.$status.'.');
            if(in_array($status,['cancelled','rejected'])&&money_cents($paid['paid'])>0)notify_team($r['reference'].' closed with recorded payments. Review manual settlement.');
        });
    }
    public static function payment(array $u,array $d): array {
        Auth::require($u,'payments.manage');$rid=integer($d,'reservation_id');$amount=money_cents($d['amount']??'');if($amount<=0)throw new HttpError('Payment must be greater than zero.');
        $method=choice($d,'method',['cash']);$ref=field($d,'reference',100,false);$notes=field($d,'notes',500,false);
        return DB::transaction(function()use($u,$rid,$amount,$method,$ref,$notes){$r=DB::one('SELECT * FROM reservations WHERE id=? FOR UPDATE',[$rid]);if(!$r||!in_array($r['status'],['pending','confirmed','checked_in','checked_out']))throw new HttpError('Choose a non-cancelled reservation with an outstanding balance.');$paid=DB::one('SELECT COALESCE(SUM(amount),0) paid FROM payments WHERE reservation_id=? AND voided_at IS NULL',[$rid]);if($amount>money_cents($r['total'])-money_cents($paid['paid']))throw new HttpError('Payment exceeds the outstanding balance.');DB::run('INSERT INTO payments(reservation_id,amount,method,reference,notes,recorded_by) VALUES(?,?,?,?,?,?)',[$rid,decimal($amount),$method,$ref,$notes,$u['id']]);$id=DB::id();CashPayments::closePending($rid,'A separate payment was recorded. Please request a slip for the remaining balance, if needed.');audit('create','payments',$id,'Reservation '.$r['reference']);$g=DB::one('SELECT user_id FROM guests WHERE id=?',[$r['guest_id']]);if($g['user_id'])notify_user((int)$g['user_id'],'Payment recorded for '.$r['reference'].': PHP '.decimal($amount));return ['id'=>$id];});
    }
    public static function voidPayment(array $u,int $id,array $d): void {
        Auth::require($u,'payments.void');$reason=field($d,'reason',500);
        DB::transaction(function()use($u,$id,$reason){$p=DB::one('SELECT reservation_id FROM payments WHERE id=?',[$id]);if(!$p)throw new HttpError('Payment not found.',404);DB::one('SELECT id FROM reservations WHERE id=? FOR UPDATE',[$p['reservation_id']]);$p=DB::one('SELECT * FROM payments WHERE id=? FOR UPDATE',[$id]);if($p['voided_at'])throw new HttpError('Already voided.',409);DB::run('UPDATE payments SET voided_at=NOW(),void_reason=?,voided_by=? WHERE id=?',[$reason,$u['id'],$id]);audit('void','payments',$id,$reason);notify_team('Payment #'.$id.' voided. Review the reservation balance.');});
    }
    public static function save(array $u,string $m,array $d,?int $id=null): array {
        if($m==='reservations')return self::reserve($u,$d,$id);
        if($m==='cash_requests'){if($id)throw new HttpError('Payment slips cannot be edited. Cancel and generate a new slip.',405);return CashPayments::create($u,$d);}
        if($m==='payments'){if($id)throw new HttpError('Payment records cannot be edited.',405);return self::payment($u,$d);}
        $perm=['guests'=>'guests.manage','facilities'=>'facilities.manage','maintenance'=>'maintenance.manage','users'=>'users.manage','devices'=>'devices.manage'];
        if(!isset($perm[$m]))throw new HttpError('Unsupported operation.',405);Auth::require($u,$perm[$m]);
        return DB::transaction(function()use($u,$m,$d,$id){
            // Lock the role row before user mutation to protect the last administrator.
            if($m==='users')DB::one('SELECT id FROM roles WHERE id=1 FOR UPDATE');
            $old=$id?DB::one("SELECT * FROM $m WHERE id=? FOR UPDATE",[$id]):null;if($id&&!$old)throw new HttpError('Record not found.',404);
            if($old&&!empty($old['archived_at']))throw new HttpError('Restore the record before editing.');
            $extra=[];
            switch($m){
                case 'guests':
                    $v=['name'=>field($d,'name',100),'email'=>email_field($d),'phone'=>field($d,'phone',40),'address'=>field($d,'address',255,false)];
                    if($old&&$old['user_id']) {if(DB::one('SELECT id FROM users WHERE email=? AND id<>?',[$v['email'],$old['user_id']]))throw new HttpError('Email already in use.');DB::run('UPDATE users SET name=?,email=? WHERE id=?',[$v['name'],$v['email'],$old['user_id']]);}break;
                case 'facilities':
                    $imagePath=field($d,'image_path',255,false);
                    if($imagePath!==''&&(!preg_match('~\Aassets/img/[A-Za-z0-9][A-Za-z0-9._-]*\.(?:png|jpe?g|webp)\z~i',$imagePath)||!is_file(__DIR__.'/../'.$imagePath)))throw new HttpError('Choose an existing image from assets/img.',422,['image_path'=>'Use a valid image path under assets/img.']);
                    $v=['name'=>field($d,'name',100),'type'=>choice($d,'type',['room','cottage','facility']),'capacity'=>integer($d,'capacity',1,1000),'nightly_rate'=>decimal(money_cents($d['nightly_rate']??'')),'status'=>choice($d,'status',['available','maintenance']),'image_path'=>$imagePath,'description'=>field($d,'description',3000,false)];
                    if($old){$r=DB::one('SELECT COUNT(*) n,MAX(guests_count) capacity FROM reservations WHERE facility_id=? AND status IN '.self::ACTIVE,[$id]);if($r['n']&&($v['status']!=='available'||$v['type']!==$old['type']||$v['capacity']<$r['capacity']))throw new HttpError('Resolve active reservations before changing type, blocking availability or reducing capacity.');}break;
                case 'maintenance':
                    $v=['facility_id'=>integer($d,'facility_id'),'title'=>field($d,'title',150),'description'=>field($d,'description',2000),'priority'=>choice($d,'priority',['low','normal','high','urgent']),'status'=>choice($d,'status',['open','in_progress','completed','cancelled']),'assigned_to'=>empty($d['assigned_to'])?null:integer($d,'assigned_to'),'resolution'=>field($d,'resolution',2000,false)];
                    if(!DB::one('SELECT id FROM facilities WHERE id=? AND archived_at IS NULL',[$v['facility_id']]))throw new HttpError('Choose an active facility.');
                    if($v['assigned_to']&&!DB::one('SELECT id FROM users WHERE id=? AND active=1 AND role_id IN (1,3)',[$v['assigned_to']]))throw new HttpError('Choose an active staff member.');
                    if($v['status']==='completed'&&$v['resolution']==='')throw new HttpError('Record the resolution before completing work.');
                    $v['completed_at']=$v['status']==='completed'?($old['completed_at']??date('Y-m-d H:i:s')):null;if(!$id)$v['created_by']=$u['id'];
                    if($v['assigned_to'])notify_user($v['assigned_to'],'Maintenance assigned: '.$v['title']);break;
                case 'users':
                    $v=['name'=>field($d,'name',100),'email'=>email_field($d),'role_id'=>integer($d,'role_id',1,4),'active'=>integer($d,'active',0,1)];
                    if(DB::one('SELECT id FROM users WHERE email=? AND id<>?',[$v['email'],$id??0]))throw new HttpError('Email already in use.');
                    if($old&&$old['role_id']===1&&$old['active']&&($v['role_id']!==1||!$v['active'])){if(DB::one('SELECT COUNT(*) n FROM users WHERE role_id=1 AND active=1')['n']<=1)throw new HttpError('Keep at least one active administrator.');}
                    if(!$id)$v['password_hash']=password_hash(Auth::password($d),PASSWORD_DEFAULT);
                    if($old&&($old['role_id']!==$v['role_id']||$old['active']!==$v['active']))$v['session_version']=$old['session_version']+1;
                    break;
                case 'devices':
                    $min=filter_var($d['min_temp']??null,FILTER_VALIDATE_FLOAT);$max=filter_var($d['max_temp']??null,FILTER_VALIDATE_FLOAT);if($min===false||$max===false||$min< -55||$max>125||$min>=$max)throw new HttpError('Use temperature thresholds between -55 and 125°C, minimum below maximum.');
                    $v=['facility_id'=>integer($d,'facility_id'),'name'=>field($d,'name',100),'active'=>integer($d,'active',0,1),'min_temp'=>$min,'max_temp'=>$max,'stale_minutes'=>integer($d,'stale_minutes',1,1440)];
                    if(!DB::one('SELECT id FROM facilities WHERE id=? AND archived_at IS NULL',[$v['facility_id']]))throw new HttpError('Choose an active facility.');
                    if(!$id){$key=bin2hex(random_bytes(32));$v['key_hash']=hash('sha256',$key);$extra['device_key']=$key;}break;
            }
            if($id){$set=implode(',',array_map(fn($k)=>"$k=?",array_keys($v)));DB::run("UPDATE $m SET $set WHERE id=?",[...array_values($v),$id]);}
            else {$cols=implode(',',array_keys($v));$qs=implode(',',array_fill(0,count($v),'?'));DB::run("INSERT INTO $m($cols) VALUES($qs)",array_values($v));$id=DB::id();}
            if($m==='users'){
                $g=DB::one('SELECT id FROM guests WHERE user_id=?',[$id]);
                if($g)DB::run('UPDATE guests SET name=?,email=? WHERE user_id=?',[$v['name'],$v['email'],$id]);
                elseif($v['role_id']===4)DB::run('INSERT INTO guests(user_id,name,email,phone) VALUES(?,?,?,?)',[$id,$v['name'],$v['email'],field($d,'phone',40)]);
            }
            audit($old?'update':'create',$m,$id,$m==='users'?'Role '.$v['role_id'].'; active '.$v['active']:'');return ['id'=>$id,...$extra];
        });
    }
    public static function archive(array $u,string $m,int $id,bool $restore): void {
        $p=['guests'=>'guests.manage','facilities'=>'facilities.manage'];if(!isset($p[$m]))throw new HttpError('This record cannot be archived.',405);Auth::require($u,$p[$m]);
        DB::transaction(function()use($m,$id,$restore){$r=DB::one("SELECT * FROM $m WHERE id=? FOR UPDATE",[$id]);if(!$r)throw new HttpError('Record not found.',404);$col=$m==='guests'?'guest_id':'facility_id';if(!$restore&&DB::one("SELECT id FROM reservations WHERE $col=? AND status IN ".self::ACTIVE.' LIMIT 1',[$id]))throw new HttpError('Resolve active reservations before archiving.');if(!$restore&&$m==='facilities'&&DB::one("SELECT id FROM maintenance WHERE facility_id=? AND status IN ('open','in_progress')",[$id]))throw new HttpError('Resolve open maintenance before archiving.');DB::run("UPDATE $m SET archived_at=? WHERE id=?",[$restore?null:date('Y-m-d H:i:s'),$id]);audit($restore?'restore':'archive',$m,$id);});
    }
    public static function dashboard(array $u): array {
        $isGuest=$u['role']==='guest';$isStaff=$u['role']==='staff';$args=[];$where='1=1';if($isGuest){$where='g.user_id=?';$args[]=$u['id'];}
        $r=$isStaff?[]:DB::one("SELECT COUNT(*) reservations,COALESCE(SUM(r.status='pending'),0) pending,COALESCE(SUM(r.status='checked_in'),0) checked_in,COALESCE(SUM(r.status IN ('pending','confirmed','checked_in')),0) active FROM reservations r JOIN guests g ON g.id=r.guest_id WHERE $where",$args);
        $fac=DB::one("SELECT COUNT(*) total,COALESCE(SUM(status='available'),0) available,COALESCE(SUM(status='maintenance'),0) maintenance FROM facilities WHERE archived_at IS NULL AND type IN ('room','cottage')");
        $occupied=(int)DB::one("SELECT COUNT(DISTINCT facility_id) n FROM reservations WHERE status='checked_in'")['n'];
        $maintenance=Auth::can($u,'maintenance.manage')?DB::one("SELECT COUNT(*) n FROM maintenance WHERE status IN ('open','in_progress')")['n']:null;
        $cash=$isStaff?null:DB::one("SELECT COALESCE(SUM(p.amount),0) total FROM payments p JOIN reservations r ON r.id=p.reservation_id JOIN guests g ON g.id=r.guest_id WHERE p.voided_at IS NULL AND $where",$args)['total'];
        $series=$isStaff?[]:DB::all("SELECT DATE_FORMAT(r.arrival,'%Y-%m') label,COUNT(*) value FROM reservations r JOIN guests g ON g.id=r.guest_id WHERE $where AND r.arrival>=DATE_SUB(CURDATE(),INTERVAL 6 MONTH) GROUP BY label ORDER BY label",$args);
        $notifications=self::list($u,'notifications',['status'=>'unread','per_page'=>6]);
        $operations=[];
        if(Auth::can($u,'reservations.manage'))foreach(['arrivals','departures','overdue','outstanding'] as $operation){
            $result=self::list($u,'reservations',['operation'=>$operation,'per_page'=>5]);
            $operations[$operation]=['total'=>$result['total'],'rows'=>$result['rows']];
        }
        return ['updated_at'=>date('Y-m-d H:i:s'),'today'=>date('Y-m-d'),'operations'=>$operations,'unread_count'=>$notifications['total'],'summary'=>$r,'facilities'=>$fac,'occupied'=>$occupied,'occupancy'=>$fac['total']?round($occupied/$fac['total']*100):0,'maintenance'=>$maintenance,'payments'=>$cash,'series'=>$series,'recent'=>$isStaff?self::list($u,'maintenance',['per_page'=>5])['rows']:self::list($u,'reservations',['per_page'=>5])['rows'],'devices'=>Auth::can($u,'monitoring.read')?self::list($u,'devices',['per_page'=>100])['rows']:[],'notifications'=>$notifications['rows']];
    }
}
