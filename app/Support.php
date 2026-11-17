<?php
declare(strict_types=1);
final class HttpError extends RuntimeException {
    public function __construct(string $message,public int $status=422, public array $errors=[]) { parent::__construct($message); }
}
function json_response(mixed $data=[],string $message='OK',int $status=200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>true,'message'=>$message,'data'=>$data],JSON_THROW_ON_ERROR); exit; }
function fail_response(Throwable $e): never {
    $known=$e instanceof HttpError; $status=$known?$e->status:500;
    if(!$known) error_log((string)$e);
    http_response_code($status); header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false,'message'=>$known?$e->getMessage():'The operation could not be completed. Please contact your administrator.','errors'=>$known?(object)$e->errors:(object)[]]); exit;
}
function body(): array {
    if((int)($_SERVER['CONTENT_LENGTH']??0)>32768) throw new HttpError('Request too large.',413);
    try {$d=json_decode(file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR);} catch(Throwable) {throw new HttpError('Invalid JSON body.',400);}
    if(!is_array($d))throw new HttpError('Expected a JSON object.',400); return $d;
}
function csrf(): void { if(!hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??''))throw new HttpError('Security token expired. Reload the page.',403); }
function field(array $d,string $key,int $max=255,bool $required=true): string {
    if(isset($d[$key])&&!is_scalar($d[$key]))throw new HttpError('Invalid field.',422,[$key=>'Use text.']);
    $v=trim((string)($d[$key]??'')); if(($required && $v==='')||mb_strlen($v)>$max)throw new HttpError('Please check the form.',422,[$key=>"Required text, maximum $max characters."]); return $v;
}
function integer(array $d,string $k,int $min=1,int $max=2147483647): int { $v=filter_var($d[$k]??null,FILTER_VALIDATE_INT); if($v===false||$v<$min||$v>$max)throw new HttpError('Please check the form.',422,[$k=>"Enter an integer from $min to $max."]); return $v; }
function choice(array $d,string $k,array $choices): string { $v=field($d,$k,60); if(!in_array($v,$choices,true))throw new HttpError('Invalid selection.',422,[$k=>'Choose a listed value.']); return $v; }
function email_field(array $d): string { $v=strtolower(field($d,'email',190)); if(!filter_var($v,FILTER_VALIDATE_EMAIL))throw new HttpError('A valid email is required.',422,['email'=>'Invalid email.']); return $v; }
function money_cents(mixed $v): int { if(!is_scalar($v)||!preg_match('/^\d{1,8}(\.\d{1,2})?$/',(string)$v))throw new HttpError('Use a non-negative amount with at most two decimal places.'); $p=explode('.',(string)$v); return (int)$p[0]*100+(int)str_pad($p[1]??'',2,'0'); }
function decimal(int $cents): string { return sprintf('%d.%02d',intdiv($cents,100),$cents%100); }
function dates(array $d,bool $future=true): array { $a=field($d,'arrival',10); $b=field($d,'departure',10); foreach([$a,$b] as $s) { $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$s); if(!$dt||$dt->format('Y-m-d')!==$s)throw new HttpError('Invalid arrival or departure date.'); } $n=(int)(new DateTimeImmutable($a))->diff(new DateTimeImmutable($b))->format('%r%a'); if($n<1||$n>365||($future&&$a<date('Y-m-d')))throw new HttpError('Choose a future stay between 1 and 365 nights.'); return [$a,$b,$n]; }
function audit(string $action,string $entity,?int $id=null,string $details=''): void { DB::run('INSERT INTO audit_logs(user_id,action,entity,entity_id,details,ip) VALUES(?,?,?,?,?,?)',[$_SESSION['user_id']??null,$action,$entity,$id,mb_substr($details,0,1000),$_SERVER['REMOTE_ADDR']??'cli']); }
function notify_user(int $id,string $message): void { DB::run('INSERT INTO notifications(user_id,message) VALUES(?,?)',[$id,$message]); }
function notify_team(string $message): void { DB::run('INSERT INTO notifications(user_id,message) SELECT id,? FROM users WHERE active=1 AND role_id IN (1,2)',[$message]); }
function e(mixed $v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
