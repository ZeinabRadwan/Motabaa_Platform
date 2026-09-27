<?php

/**
 * Boot through public/index.php like LiteSpeed (not artisan/CLI kernel only).
 *
 *   php scripts/simulate-public-options.php
 */

declare(strict_types=1);

$public = dirname(__DIR__).'/public';
$uri = '/api/auth/login';

chdir($public);

$_SERVER = array_merge($_SERVER, [
    'REQUEST_METHOD' => 'OPTIONS',
    'REQUEST_URI' => $uri,
    'QUERY_STRING' => '',
    'HTTP_HOST' => 'api.motabaah.com',
    'SERVER_NAME' => 'api.motabaah.com',
    'SERVER_PORT' => '443',
    'HTTPS' => 'on',
    'HTTP_ORIGIN' => 'https://motabaah.com',
    'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,accept',
    'SCRIPT_NAME' => '/index.php',
    'SCRIPT_FILENAME' => $public.'/index.php',
    'PHP_SELF' => '/index.php',
    'DOCUMENT_ROOT' => $public,
]);

$_GET = [];
$_POST = [];

ob_start();
try {
    include $public.'/index.php';
} catch (Throwable $e) {
    ob_end_clean();
    fwrite(STDERR, get_class($e).': '.$e->getMessage()."\n");
    fwrite(STDERR, $e->getFile().':'.$e->getLine()."\n");
    exit(1);
}
$body = ob_get_clean();

echo "Simulated OPTIONS {$uri} via public/index.php\n";
echo 'Body length: '.strlen($body)."\n";
if ($body !== '' && strlen($body) < 2000) {
    echo "Body:\n{$body}\n";
}
