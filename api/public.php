<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw new HttpError('GET is required.',405);
    if (($_GET['action']??'catalog') === 'availability') {
        json_response(Resort::availability($_GET));
    }
    if (($_GET['action']??'catalog') !== 'catalog') throw new HttpError('Endpoint not found.',404);
    json_response(DB::all("SELECT id,name,type,capacity,nightly_rate,description FROM facilities WHERE archived_at IS NULL AND status='available' AND type IN ('room','cottage') ORDER BY nightly_rate,name"));
} catch (Throwable $error) { fail_response($error); }
