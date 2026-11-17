<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../app/bootstrap.php';
try {
    foreach (['Ocean Room 1','Family Room 2'] as $name) {
        if (!DB::one("SELECT id FROM facilities WHERE name=? AND type='room' LIMIT 1",[$name])) {
            throw new RuntimeException('Expected room record not found: '.$name);
        }
    }
    $migration=file_get_contents(__DIR__.'/../database/migrations/003_room_photo_assignments.sql');
    if ($migration===false) throw new RuntimeException('Unable to read the room photo assignment migration.');
    DB::connection()->exec($migration);
    echo "Room 1 and Room 2 photos assigned to Family Room 2 and Ocean Room 1.\n";
} catch (Throwable $error) {
    fwrite(STDERR,$error->getMessage()."\n"); exit(1);
}
