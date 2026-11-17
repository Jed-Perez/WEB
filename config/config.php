<?php
declare(strict_types=1);
$config = [
    'db_host' => getenv('RESORT_DB_HOST') ?: '127.0.0.1',
    'db_port' => getenv('RESORT_DB_PORT') ?: '3306',
    'db_name' => getenv('RESORT_DB_NAME') ?: 'resort360',
    'db_user' => getenv('RESORT_DB_USER') ?: 'root',
    'db_pass' => getenv('RESORT_DB_PASS') ?: '',
    'timezone' => 'Asia/Manila', 'session_timeout' => 1800,
];
if (is_file(__DIR__.'/local.php')) $config = array_replace($config, require __DIR__.'/local.php');
return $config;
