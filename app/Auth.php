<?php
declare(strict_types=1);
final class Auth {
    public static function user(): array {
        $u=DB::one('SELECT u.id,u.name,u.email,u.active,u.role_id,u.session_version,r.name role FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?',[$_SESSION['user_id']??0]);
        if(!$u||!$u['active']||$u['session_version']!==($_SESSION['version']??null)) {unset($_SESSION['user_id']); throw new HttpError('Your session has expired. Please sign in.',401);}
        $u['permissions']=array_column(DB::all('SELECT p.name FROM permissions p JOIN role_permissions rp ON rp.permission_id=p.id WHERE rp.role_id=?',[$u['role_id']]),'name'); return $u;
    }
    public static function can(array $u,string $p): bool {return in_array($p,$u['permissions'],true);}
    public static function require(array $u,string $p): void {if(!self::can($u,$p))throw new HttpError('You do not have permission for this action.',403);}
    public static function password(array $d,string $key='password'): string { $s=field($d,$key,72); if(strlen($s)<12||strlen($s)>72)throw new HttpError('Use a password between 12 and 72 bytes.',422,[$key=>'At least 12 characters; maximum 72 bytes.']); return $s; }
    public static function throttle(string $bucket): void { $n=DB::one('SELECT COUNT(*) n FROM auth_attempts WHERE bucket=? AND created_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)',[$bucket]); if($n['n']>=5)throw new HttpError('Too many attempts. Try again in 15 minutes.',429); }
    public static function login(array $d): array {
        $email=email_field($d); $pw=field($d,'password',72); $bucket=hash('sha256',($_SERVER['REMOTE_ADDR']??'').':'.$email);
        $ipbucket=hash('sha256','login-ip:'.($_SERVER['REMOTE_ADDR']??''));
        $ipcount=DB::one('SELECT COUNT(*) n FROM auth_attempts WHERE bucket=? AND created_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)',[$ipbucket]);
        if($ipcount['n']>=30)throw new HttpError('Too many attempts. Try again in 15 minutes.',429);
        self::throttle($bucket); DB::run('INSERT INTO auth_attempts(bucket) VALUES(?),(?)',[$bucket,$ipbucket]);
        $u=DB::one('SELECT * FROM users WHERE email=?',[$email]);
        $valid=password_verify($pw,$u['password_hash']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if(!$u||!$valid||!$u['active']) {audit('login_failed','authentication');throw new HttpError('Invalid email or password.',401);}
        session_regenerate_id(true); $_SESSION['user_id']=$u['id']; $_SESSION['version']=$u['session_version']; $_SESSION['csrf']=bin2hex(random_bytes(32));
        DB::run('DELETE FROM auth_attempts WHERE bucket=?',[$bucket]); audit('login','users',(int)$u['id']); return ['csrf'=>$_SESSION['csrf']];
    }
    public static function register(array $d): array {
        $bucket=hash('sha256','register:'.($_SERVER['REMOTE_ADDR']??'')); self::throttle($bucket); DB::run('INSERT INTO auth_attempts(bucket) VALUES(?)',[$bucket]);
        $name=field($d,'name',100);$email=email_field($d);$phone=field($d,'phone',40);$hash=password_hash(self::password($d),PASSWORD_DEFAULT);
        return DB::transaction(function()use($name,$email,$phone,$hash){
            if(DB::one('SELECT id FROM users WHERE email=?',[$email]))throw new HttpError('This email cannot be registered. Try signing in or contact reception.');
            DB::run('INSERT INTO users(role_id,name,email,password_hash) VALUES(4,?,?,?)',[$name,$email,$hash]);$id=DB::id();
            DB::run('INSERT INTO guests(user_id,name,email,phone) VALUES(?,?,?,?)',[$id,$name,$email,$phone]); audit('register','users',$id); return ['id'=>$id];
        });
    }
    public static function issueReset(int $id): string {
        if(!DB::one('SELECT id FROM users WHERE id=? AND active=1',[$id]))throw new HttpError('Active account not found.',404);
        $token=bin2hex(random_bytes(32)); DB::run('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL',[$id]);
        DB::run('INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))',[$id,hash('sha256',$token)]);audit('reset_issued','users',$id);return $token;
    }
    public static function reset(array $d): void {
        $hash=hash('sha256',field($d,'token',64));$pw=password_hash(self::password($d),PASSWORD_DEFAULT);
        DB::transaction(function()use($hash,$pw){$r=DB::one('SELECT * FROM password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() FOR UPDATE',[$hash]);if(!$r)throw new HttpError('Reset code is invalid or expired.');DB::run('UPDATE users SET password_hash=?,session_version=session_version+1 WHERE id=? AND active=1',[$pw,$r['user_id']]);DB::run('UPDATE password_resets SET used_at=NOW() WHERE id=?',[$r['id']]);audit('password_reset','users',(int)$r['user_id']);});
    }
    public static function profile(array $u,array $d): void {
        $name=field($d,'name',100);$email=email_field($d);$phone=field($d,'phone',40,false);$address=field($d,'address',255,false);
        DB::transaction(function()use($u,$d,$name,$email,$phone,$address){
            if(DB::one('SELECT id FROM users WHERE email=? AND id<>?',[$email,$u['id']]))throw new HttpError('Email is already in use.');
            DB::run('UPDATE users SET name=?,email=? WHERE id=?',[$name,$email,$u['id']]);
            if($u['role']==='guest') {if($phone==='')throw new HttpError('Phone is required.'); DB::run('UPDATE guests SET name=?,email=?,phone=?,address=? WHERE user_id=?',[$name,$email,$phone,$address,$u['id']]);}
            if(!empty($d['new_password'])) {$old=DB::one('SELECT password_hash FROM users WHERE id=?',[$u['id']]);if(!password_verify(field($d,'current_password',72),$old['password_hash']))throw new HttpError('Current password is incorrect.');DB::run('UPDATE users SET password_hash=?,session_version=session_version+1 WHERE id=?',[password_hash(self::password($d,'new_password'),PASSWORD_DEFAULT),$u['id']]);$_SESSION['version']++;session_regenerate_id(true);audit('password_changed','users',(int)$u['id']);}
            audit('profile_updated','users',(int)$u['id']);
        });
    }
}
