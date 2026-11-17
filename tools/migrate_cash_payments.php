<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../app/bootstrap.php';
try {
    DB::connection()->exec(file_get_contents(__DIR__.'/../database/migrations/001_cash_requests.sql'));
    echo "Cash payment slips ready. Existing records retained.\n";
} catch (Throwable $error) {
    fwrite(STDERR,$error->getMessage()."\n"); exit(1);
}
