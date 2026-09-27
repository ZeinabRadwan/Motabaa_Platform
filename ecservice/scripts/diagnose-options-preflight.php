<?php

/**
 * Simulate browser CORS preflight without the web server (SSH on production).
 *
 *   php scripts/diagnose-options-preflight.php
 *   php scripts/diagnose-options-preflight.php /api/auth/login
 */

declare(strict_types=1);

$path = $argv[1] ?? '/api/auth/login';

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create($path, 'OPTIONS', [], [], [], [
    'HTTP_ORIGIN' => 'https://motabaah.com',
    'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,accept',
]);

try {
    $response = $kernel->handle($request);
    echo "Path: {$path}\n";
    echo 'Status: '.$response->getStatusCode()."\n";
    echo 'Access-Control-Allow-Origin: '.($response->headers->get('Access-Control-Allow-Origin') ?? '(none)')."\n";
    echo 'Access-Control-Allow-Methods: '.($response->headers->get('Access-Control-Allow-Methods') ?? '(none)')."\n";
    $kernel->terminate($request, $response);
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e).': '.$e->getMessage()."\n");
    fwrite(STDERR, $e->getFile().':'.$e->getLine()."\n");
    exit(1);
}
