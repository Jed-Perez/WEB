<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../app/bootstrap.php';
try {
    $name='Demo · Garden Cottage 01';
    $before=DB::one("SELECT capacity,nightly_rate,image_path FROM facilities WHERE name=? AND type='cottage'",[$name]);
    if (!$before) throw new RuntimeException('Expected Garden Cottage record not found.');
    $migration=file_get_contents(__DIR__.'/../database/migrations/005_garden_cottage_details.sql');
    if ($migration===false) throw new RuntimeException('Unable to read the Garden Cottage details migration.');
    DB::connection()->exec($migration);
    $expected=implode("\n",[
        'Room Inclusions:',
        'Kubo Rooms (Fan/AC): Day Tour & Overnight Available',
        'With WiFi',
        'Free use of Griller',
        'Common CR',
        'Free Tent Pitch (Pwede mag dala ng sariling Tent)',
        'Free Use of Swimming Pool',
        'Bed Capacity good for 5 persons',
        'Extra mattress Available',
        'Additional ₱250 if magluluto',
        'Overnight Time: 10 AM Check-in | 9 AM Check-Out',
        'Details:',
        'Beach Front + Swimming Pool Access',
        'No Entrance Fee',
        'No Corkage Fee',
        'Highlight:',
        'Perfect for Family, Barkada & Team Outing!',
    ]);
    $after=DB::one("SELECT description,capacity,nightly_rate,image_path FROM facilities WHERE name=? AND type='cottage'",[$name]);
    if (!$after||$after['description']!==$expected) throw new RuntimeException('Garden Cottage details did not verify.');
    foreach (['capacity','nightly_rate','image_path'] as $field) {
        if ($after[$field]!==$before[$field]) throw new RuntimeException('Garden Cottage '.$field.' changed unexpectedly.');
    }
    echo "Garden Cottage details updated; capacity, rate and photo retained.\n";
} catch (Throwable $error) {
    fwrite(STDERR,$error->getMessage()."\n"); exit(1);
}
