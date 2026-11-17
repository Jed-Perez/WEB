<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../app/bootstrap.php';
if(!str_starts_with($config['db_name'],'resort360_test'))throw new RuntimeException('Test database required.');
$password=getenv('R360_TEST_PASSWORD');if(!$password)throw new RuntimeException('Supply ephemeral test password.');
foreach([1=>'admin',2=>'reception',3=>'staff',4=>'guest',5=>'guest2'] as $i=>$name){$role=min($i,4);DB::run('INSERT INTO users(role_id,name,email,password_hash) VALUES(?,?,?,?)',[$role,'Test '.ucfirst($name),$name.'@example.test',password_hash($password,PASSWORD_DEFAULT)]);$id=DB::id();if($role===4)DB::run('INSERT INTO guests(user_id,name,email,phone) VALUES(?,?,?,?)',[$id,'Test '.ucfirst($name),$name.'@example.test','09000000000']);}
echo "Ephemeral test accounts created.\n";
