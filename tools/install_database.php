<?php
declare(strict_types=1);
// Explicit local CLI installation. Never drops, truncates or overwrites existing tables.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../app/bootstrap.php';
try {
    $name=$config['db_name'];
    if (!preg_match('/^[a-zA-Z0-9_]+$/',$name)) throw new RuntimeException('Invalid database name.');
    $pdo=new PDO("mysql:host={$config['db_host']};port={$config['db_port']};charset=utf8mb4",$config['db_user'],$config['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $query=$pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=?');
    $query->execute([$name]);
    if ($query->fetchColumn()) throw new RuntimeException('Database already contains tables. Nothing was imported or overwritten.');
    $sql=file_get_contents(__DIR__.'/../database/database.sql');
    $sql=str_replace(['CREATE DATABASE IF NOT EXISTS resort360','USE resort360;'],["CREATE DATABASE IF NOT EXISTS `$name`","USE `$name`;"],$sql);
    $pdo->exec($sql);
    echo "Installed database $name.\n";
    echo 'Tables: '.DB::one('SELECT COUNT(*) n FROM information_schema.TABLES WHERE TABLE_SCHEMA=?',[$name])['n']."\n";
    echo 'Roles: '.DB::one('SELECT COUNT(*) n FROM roles')['n']."\n";
    echo 'Sample facilities: '.DB::one('SELECT COUNT(*) n FROM facilities')['n']."\n";
} catch (Throwable $error) { fwrite(STDERR,$error->getMessage()."\n"); exit(1); }
