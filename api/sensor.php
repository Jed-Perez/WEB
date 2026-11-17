<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
// Device ingestion uses a bearer credential, never browser session authorization.
try {
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new HttpError('POST is required.',405);
    $bearer=$_SERVER['HTTP_AUTHORIZATION']??'';
    if(!preg_match('/^Bearer ([a-f0-9]{64})$/',$bearer,$m))throw new HttpError('Device credential required.',401);
    $d=body();$temp=filter_var($d['temperature']??null,FILTER_VALIDATE_FLOAT);
    if($temp===false||$temp< -55||$temp>125)throw new HttpError('Temperature must be between -55 and 125°C.');
    $sample=field($d,'sample_id',80);if(!preg_match('/^[A-Za-z0-9._:-]+$/',$sample))throw new HttpError('Invalid sample ID.');
    $result=DB::transaction(function()use($m,$temp,$sample){
        $dev=DB::one('SELECT * FROM devices WHERE key_hash=? AND active=1 FOR UPDATE',[hash('sha256',$m[1])]);if(!$dev)throw new HttpError('Invalid device credential.',401);
        $existing=DB::one('SELECT id,temperature FROM readings WHERE device_id=? AND sample_id=?',[$dev['id'],$sample]);
        if($existing){if((float)$existing['temperature']!==round($temp,2))throw new HttpError('Sample ID already has a different temperature.',409);return ['id'=>$existing['id'],'duplicate'=>true];}
        if($dev['last_seen']&&time()-strtotime($dev['last_seen'])<5)throw new HttpError('Send at most one new sample every five seconds.',429);
        DB::run('INSERT INTO readings(device_id,sample_id,temperature) VALUES(?,?,?)',[$dev['id'],$sample,$temp]);$id=DB::id();DB::run('UPDATE devices SET last_seen=NOW() WHERE id=?',[$dev['id']]);return ['id'=>$id,'duplicate'=>false];
    });json_response($result,'Temperature received.');
}catch(Throwable $e){fail_response($e);}
