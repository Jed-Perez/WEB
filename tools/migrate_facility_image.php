<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../app/bootstrap.php';
try {
    if (!DB::one("SHOW COLUMNS FROM facilities LIKE 'image_path'")) {
        $migration=file_get_contents(__DIR__.'/../database/migrations/002_facility_image_path.sql');
        if ($migration===false) throw new RuntimeException('Unable to read the facility image migration.');
        DB::connection()->exec($migration);
    }
    foreach ([
        ['Ocean Room 2','assets/img/ocean-room-2-clean.png'],
        ['Family Room 1','assets/img/family-room-1-clean.png'],
        ['Demo · Garden Cottage 01','assets/img/garden-cottage-01.jpeg'],
    ] as [$name,$path]) {
        DB::run("UPDATE facilities SET image_path=? WHERE name=? AND image_path=''",[$path,$name]);
    }
    echo "Facility image paths ready. Existing facility records retained.\n";
} catch (Throwable $error) {
    fwrite(STDERR,$error->getMessage()."\n"); exit(1);
}
