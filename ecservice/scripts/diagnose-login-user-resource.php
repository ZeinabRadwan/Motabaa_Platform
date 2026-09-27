<?php

/**
 * Reproduce login JSON payload building (after auth succeeds).
 *
 *   php scripts/diagnose-login-user-resource.php
 *   php scripts/diagnose-login-user-resource.php user@example.com
 */

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Resources\Admin\User\UserResource;
use App\Models\User;

$email = $argv[1] ?? null;

$query = User::query()->where('status', User::STATUS_CAN_LOGIN);
$user = $email
    ? $query->where('email', $email)->first()
    : $query->orderBy('id')->first();

if (! $user) {
    fwrite(STDERR, "No active user found".($email ? " for email {$email}" : '').".\n");
    exit(1);
}

echo "User id={$user->id} email={$user->email}\n";

try {
    $resource = UserResource::forSession($user);
    $payload = $resource->resolve(request());
    echo "UserResource OK (keys: ".implode(', ', array_keys($payload)).")\n";
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e).': '.$e->getMessage()."\n");
    fwrite(STDERR, $e->getFile().':'.$e->getLine()."\n");
    exit(1);
}

try {
    $token = $user->createToken('diagnose-auth')->plainTextToken;
    echo "Sanctum token OK (length ".strlen($token).")\n";
    $user->tokens()->where('name', 'diagnose-auth')->delete();
} catch (Throwable $e) {
    fwrite(STDERR, 'Sanctum: '.get_class($e).': '.$e->getMessage()."\n");
    exit(1);
}

echo "Login response path looks healthy for this user.\n";
