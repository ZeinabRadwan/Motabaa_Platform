<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
    echo 'DB_OK '.Illuminate\Support\Facades\DB::connection()->getDatabaseName().PHP_EOL;
    echo 'HAS_MIGRATIONS='.(Illuminate\Support\Facades\Schema::hasTable('migrations') ? 'yes' : 'no').PHP_EOL;
    echo 'HAS_MESSAGES='.(Illuminate\Support\Facades\Schema::hasTable('messages') ? 'yes' : 'no').PHP_EOL;
    echo 'HAS_AUDIT='.(Illuminate\Support\Facades\Schema::hasTable('media_attachment_audit_logs') ? 'yes' : 'no').PHP_EOL;
} catch (Throwable $e) {
    echo 'DB_FAIL '.$e->getMessage().PHP_EOL;
    exit(1);
}
