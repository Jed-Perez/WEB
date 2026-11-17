<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/bootstrap.php';
echo "Resort360 local administrator setup / recovery\n";
function prompt(string $label): string {echo $label;return trim((string)fgets(STDIN));}
try {
    $mode=$argv[1]??'create-admin';
    if($mode==='reset'){
        $email=prompt('Account email (verify identity first): ');$u=DB::one('SELECT id FROM users WHERE email=? AND active=1',[$email]);if(!$u)throw new RuntimeException('Active account not found.');
        $token=DB::transaction(fn()=>Auth::issueReset((int)$u['id']));echo "One-use reset code (30 minutes): $token\nUse Reset password on the login page.\n";exit;
    }
    if(DB::one('SELECT id FROM users WHERE role_id=1 AND active=1'))throw new RuntimeException('An administrator already exists. Use the Users module or: php tools/setup.php reset');
    $name=prompt('Administrator full name: ');$email=prompt('Administrator email: ');
    if($mode==='invite-admin') {
        email_field(['email'=>$email]);field(['name'=>$name],'name',100);
        $token=DB::transaction(function()use($name,$email){
            DB::one('SELECT id FROM roles WHERE id=1 FOR UPDATE');
            if(DB::one('SELECT id FROM users WHERE role_id=1 AND active=1'))throw new RuntimeException('Administrator already exists.');
            DB::run('INSERT INTO users(role_id,name,email,password_hash) VALUES(1,?,?,?)',[$name,strtolower($email),password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT)]);
            $id=DB::id();audit('initial_admin','users',$id);return Auth::issueReset($id);
        });
        echo "Administrator created with a random undisclosed password.\nOne-use reset code (30 minutes): $token\n";exit;
    }
    echo "Password input is visible in this console. Use a private terminal.\n";$pw=prompt('New password (12–72 bytes): ');$confirm=prompt('Repeat password: ');if($pw!==$confirm)throw new RuntimeException('Passwords do not match.');
    Auth::password(['password'=>$pw]);email_field(['email'=>$email]);field(['name'=>$name],'name',100);
    DB::transaction(function()use($name,$email,$pw){DB::one('SELECT id FROM roles WHERE id=1 FOR UPDATE');if(DB::one('SELECT id FROM users WHERE role_id=1 AND active=1'))throw new RuntimeException('Administrator already exists.');DB::run('INSERT INTO users(role_id,name,email,password_hash) VALUES(1,?,?,?)',[$name,strtolower($email),password_hash($pw,PASSWORD_DEFAULT)]);audit('initial_admin','users',DB::id());});echo "Administrator created. No default credentials are shipped.\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
