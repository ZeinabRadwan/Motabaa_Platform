<?php

/**
 * Compare session-related permissions and isParentUser() for two accounts (LIVE/local).
 *
 * Usage:
 *   php scripts/compare-user-session-access.php "user1@example.com" "user2@example.com"
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

$identifiers = array_slice($argv, 1);
if (count($identifiers) < 1) {
    fwrite(STDERR, "Usage: php scripts/compare-user-session-access.php <email-or-phone> [<email-or-phone>]\n");
    exit(1);
}

$sessionPerms = [
    'access_education-sessions',
    'edit_education-sessions',
    'admin_education-sessions',
    'access_treatment-sessions',
    'edit_treatment-sessions',
    'admin_treatment-sessions',
    'access_independent-sessions',
    'edit_independent-sessions',
    'admin_independent-sessions',
];

foreach ($identifiers as $id) {
    $user = User::query()
        ->where('email', $id)
        ->orWhere('phone', $id)
        ->with('roles:id,name,default_name')
        ->first();

    if (!$user) {
        echo "User not found: {$id}\n\n";
        continue;
    }

    echo "=== {$user->name} (id {$user->id}, job_title: {$user->job_title}) ===\n";
    echo 'isParentUser: '.(isParentUser($user) ? 'true' : 'false')."\n";
    echo 'roles: '.json_encode($user->roles->map(fn ($r) => [
        'name' => $r->name,
        'default_name' => $r->default_name,
    ])->values(), JSON_UNESCAPED_UNICODE)."\n";

    $granted = [];
    foreach ($sessionPerms as $perm) {
        if ($user->checkPermissionTo($perm, 'web')) {
            $granted[] = $perm;
        }
    }
    echo 'session permissions: '.(count($granted) ? implode(', ', $granted) : '(none)')."\n\n";
}
