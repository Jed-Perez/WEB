<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
try {
    $route=$_GET['route']??'session';$method=$_SERVER['REQUEST_METHOD'];
    if(!in_array($method,['GET','POST','PATCH','DELETE'],true))throw new HttpError('Method not allowed.',405);
    if($method!=='GET')csrf();
    if($route==='login'&&$method==='POST')json_response(Auth::login(body()),'Signed in.');
    if($route==='register'&&$method==='POST')json_response(Auth::register(body()),'Account created. You can sign in now.',201);
    if($route==='reset'&&$method==='POST'){Auth::reset(body());json_response([],'Password reset. Sign in with your new password.');}
    $u=Auth::user();
    if($route==='session'&&$method==='GET')json_response(['user'=>$u,'csrf'=>$_SESSION['csrf'],'guest'=>DB::one('SELECT phone,address FROM guests WHERE user_id=?',[$u['id']])]);
    if($route==='logout'&&$method==='POST'){audit('logout','users',$u['id']);$_SESSION=[];session_destroy();setcookie(session_name(),'',time()-3600,'/');json_response([],'Signed out.');}
    if($route==='profile'&&$method==='PATCH'){Auth::profile($u,body());json_response([],'Profile updated.');}
    if($route==='dashboard'&&$method==='GET')json_response(Resort::dashboard($u));
    if($route==='lookups'&&$method==='GET')json_response(Resort::lookups($u));
    if($route==='availability'&&$method==='GET'){if($u['role']!=='guest')Auth::require($u,'reservations.manage');json_response(Resort::availability($_GET,$u));}
    if($route==='trend'&&$method==='GET')json_response(Resort::trend($u,$_GET));
    if($route==='report'&&$method==='GET'){
        Auth::require($u,'reports.read');$m=choice($_GET,'module',['reservations','payments','maintenance','readings','facilities']);
        $full=($_GET['full']??'')==='1';$q=array_diff_key($_GET,['page'=>1]);$q['summary']='1';$q['per_page']=100;
        $result=Resort::list($u,$m,$q,$full);
        if($full&&$result['total']>10000)throw new HttpError('Narrow your report to 10,000 records or fewer before printing.');
        json_response($result);
    }
    if($route==='reset-issue'&&$method==='POST'){Auth::require($u,'users.manage');$d=body();$token=DB::transaction(fn()=>Auth::issueReset(integer($d,'id')));json_response(['token'=>$token],'Reset code valid for 30 minutes. Hand it only to the verified account holder.');}
    if($route==='device-key'&&$method==='POST'){Auth::require($u,'devices.manage');$id=integer(body(),'id');$key=bin2hex(random_bytes(32));DB::transaction(function()use($id,$key){if(!DB::one('SELECT id FROM devices WHERE id=? FOR UPDATE',[$id]))throw new HttpError('Device not found.',404);DB::run('UPDATE devices SET key_hash=? WHERE id=?',[hash('sha256',$key),$id]);audit('key_rotated','devices',$id);});json_response(['device_key'=>$key],'Copy this key now. The previous key is revoked.');}
    if($route==='notifications-read'&&$method==='POST'){
        $d=body();$where='user_id=? AND read_at IS NULL';$args=[$u['id']];
        if(isset($d['id'])){$where.=' AND id=?';$args[]=integer($d,'id');}
        DB::run("UPDATE notifications SET read_at=NOW() WHERE $where",$args);json_response([],'Notifications marked as read.');
    }
    if($route==='records'){
        $m=field($_GET,'module',30);Resort::access($u,$m);$id=isset($_GET['id'])?integer($_GET,'id'):null;
        if($method==='GET'){
            $result=Resort::list($u,$m,$_GET);
            if($id){if(!$result['rows'])throw new HttpError('Record not found.',404);$row=$result['rows'][0];if($m==='guests')$row['history']=Resort::list($u,'reservations',['guest_id'=>$id,'per_page'=>100])['rows'];if($m==='reservations')$row['ledger']=DB::all('SELECT id,amount,method,reference,voided_at,void_reason,created_at FROM payments WHERE reservation_id=? ORDER BY id DESC',[$id]);json_response($row);}
            json_response($result);
        }
        $d=body();
        if($method==='POST')json_response(Resort::save($u,$m,$d),'Record created.',201);
        if(!$id)throw new HttpError('Record ID required.',400);
        if($method==='DELETE'){Resort::archive($u,$m,$id,false);json_response([],'Record archived.');}
        if($method==='PATCH'){
            $action=$d['action']??'save';
            if($action==='restore'){Resort::archive($u,$m,$id,true);json_response([],'Record restored.');}
            if($m==='reservations'&&$action==='status'){Resort::reservationStatus($u,$id,$d);json_response([],'Reservation status updated.');}
            if($m==='cash_requests')json_response(CashPayments::act($u,$id,$d),'Cash payment slip updated.');
            if($m==='payments'&&$action==='void'){Resort::voidPayment($u,$id,$d);json_response([],'Payment voided.');}
            json_response(Resort::save($u,$m,$d,$id),'Record updated.');
        }
    }
    throw new HttpError('Endpoint or method not found.',404);
} catch(Throwable $e){fail_response($e);}
