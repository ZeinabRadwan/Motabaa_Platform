<?php

/**
 * POST /api/auth/login through the HTTP kernel (shows status + JSON body).
 *
 *   php scripts/diagnose-login-post.php user@example.com 'password'
 */

declare(strict_types=1);

$email = $argv[1] ?? null;
$password = $argv[2] ?? null;

if (! $email || ! $password) {
    fwrite(STDERR, "Usage: php scripts/diagnose-login-post.php <email> <password>\n");
    exit(1);
}

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$body = json_encode(['email' => $email, 'password' => $password], JSON_THROW_ON_ERROR);

$request = Illuminate\Http\Request::create(
    '/api/auth/login',
    'POST',
    [],
    [],
    [],
    [
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ORIGIN' => 'https://platform.motabaah.com',
    ],
    $body
);

try {
    $response = $kernel->handle($request);
    echo 'Status: '.$response->getStatusCode()."\n";
    echo "Body:\n".$response->getContent()."\n";
    $kernel->terminate($request, $response);
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e).': '.$e->getMessage()."\n");
    fwrite(STDERR, $e->getFile().':'.$e->getLine()."\n");
    exit(1);
}
