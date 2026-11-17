<?php
declare(strict_types=1);
$config = require __DIR__.'/../config/config.php';
date_default_timezone_set($config['timezone']);
ini_set('display_errors','0');
ini_set('log_errors','1');
ini_set('error_log',__DIR__.'/../storage/php-errors.log');
require_once __DIR__.'/Database.php';
require_once __DIR__.'/Support.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/Resort.php';
require_once __DIR__.'/CashPayments.php';
if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    header('Cache-Control: no-store');
    ini_set('session.use_strict_mode','1');
    $sessionPath=__DIR__.'/../storage/sessions';
    if(!is_dir($sessionPath)&&!mkdir($sessionPath,0700,true)&&!is_dir($sessionPath))throw new RuntimeException('Unable to create secure session storage.');
    if(!is_writable($sessionPath)||session_save_path($sessionPath)===false)throw new RuntimeException('Secure session storage is not writable.');
    session_name('resort360');
    session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','path'=>'/']);
    session_start();
    if (isset($_SESSION['last_seen']) && time()-$_SESSION['last_seen'] > $config['session_timeout']) {
        $_SESSION=[]; session_regenerate_id(true);
    }
    $_SESSION['last_seen']=time();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
