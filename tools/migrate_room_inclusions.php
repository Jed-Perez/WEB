<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../app/bootstrap.php';
try {
    foreach (['Ocean Room 1','Ocean Room 2','Family Room 1','Family Room 2'] as $name) {
        if (!DB::one("SELECT id FROM facilities WHERE name=? AND type='room' LIMIT 1",[$name])) {
            throw new RuntimeException('Expected room record not found: '.$name);
        }
    }
    $migration=file_get_contents(__DIR__.'/../database/migrations/004_room_inclusions.sql');
    if ($migration===false) throw new RuntimeException('Unable to read the room inclusions migration.');
    DB::connection()->exec($migration);
    $expected=implode("\n",[
        'Room Inclusions:',
        'Airconditioned Rooms',
        'Bed + Extra Mattress',
        'Own/ Common CR',
        'Own Kitchen',
        'Free use of Kitchen Utensils',
        'Free WiFi',
        'Free open cottage / Tambayan Area',
        'Free use of Videoke until 10 PM',
        'Details:',
        'Beach Front + Swimming Pool Access',
        'No Entrance Fee',
        'No Corkage Fee',
        'Highlight:',
        'PERFECT FOR FAMILY, BARKADA & TEAM OUTING!',
    ]);
    $rooms=DB::all("SELECT name,description FROM facilities WHERE name IN ('Ocean Room 1','Ocean Room 2','Family Room 1','Family Room 2') AND type='room'");
    if (count($rooms)!==4) throw new RuntimeException('Expected exactly four rooms after migration.');
    foreach ($rooms as $room) {
        if ($room['description']!==$expected) throw new RuntimeException('Room inclusions did not verify for '.$room['name'].'.');
    }
    echo "Room inclusions updated for Ocean Room 1, Ocean Room 2, Family Room 1 and Family Room 2.\n";
} catch (Throwable $error) {
    fwrite(STDERR,$error->getMessage()."\n"); exit(1);
}
