<?php

namespace App\Console\Commands;

use App\Http\Resources\Admin\User\UserResource;
use App\Models\Log;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class DiagnoseImpersonateUserCommand extends Command
{
    protected $signature = 'users:diagnose-impersonate {user : Target user id}
                            {--token : Also create and delete a test Sanctum token}
                            {--admin= : Admin user id; replays the full web request path inside a rolled-back transaction}';

    protected $description = 'Reproduce impersonation session payload building for a user (read-only; --admin runs in a rolled-back transaction)';

    public function handle(): int
    {
        $userId = (int) $this->argument('user');
        $user = User::withTrashed()->find($userId);

        if (! $user) {
            $this->error("User #{$userId} not found.");

            return self::FAILURE;
        }

        $this->info("User #{$user->id} {$user->name} status={$user->status} deleted=".($user->trashed() ? 'yes' : 'no'));

        $payload = null;
        $steps = [
            'load roles' => fn () => $user->loadMissing('roles'),
            'load centers' => fn () => $user->loadMissing('centers'),
            'isParentUser' => fn () => isParentUser($user),
            'load scaseParent' => function () use ($user) {
                if (isParentUser($user)) {
                    $user->loadMissing('scaseParent');
                }
            },
            'UserResource::forSession resolve' => function () use ($user, &$payload) {
                $payload = UserResource::forSession($user)->resolve(request());

                return $payload;
            },
            'json_encode session payload (strict UTF-8)' => fn () => $this->strictJsonEncode($payload),
        ];

        if (! $this->runSteps($steps)) {
            return self::FAILURE;
        }

        if ($this->option('token')) {
            try {
                $plain = $user->createToken('diagnose-impersonate')->plainTextToken;
                $this->info('OK: Sanctum createToken (length '.strlen($plain).')');
                $user->tokens()->where('name', 'diagnose-impersonate')->delete();
            } catch (Throwable $e) {
                $this->reportFailure('Sanctum createToken', $e);

                return self::FAILURE;
            }
        }

        if ($adminId = (int) $this->option('admin')) {
            if (! $this->replayAdminPath($adminId, $user)) {
                return self::FAILURE;
            }
        }

        $this->info('Impersonation path looks healthy for this user.');

        return self::SUCCESS;
    }

    private function replayAdminPath(int $adminId, User $user): bool
    {
        $admin = User::find($adminId);
        if (! $admin) {
            $this->error("Admin #{$adminId} not found.");

            return false;
        }

        $this->info("Replaying web path as Admin #{$admin->id} {$admin->name} (rolled back)");

        DB::beginTransaction();
        try {
            Auth::setUser($admin);
            $admin->loadMissing(['centers', 'roles']);

            $ok = $this->runSteps([
                'authorize: isHasRole(admin) || canAny(...)' => function () use ($admin) {
                    $allowed = isHasRole('admin', $admin)
                        || canAny(['admin_users', 'access_centers', 'admin_centers'], $admin);
                    if (! $allowed) {
                        throw new \RuntimeException('Admin is not authorized to impersonate (would be 422, not 500).');
                    }

                    return true;
                },
                'isImpersonating(admin)' => fn () => isImpersonating($admin),
                'target eligibility' => function () use ($admin, $user) {
                    $user->loadMissing('roles');
                    if ($user->id === $admin->id || isHasRole('admin', $user)) {
                        throw new \RuntimeException('Target is self or admin (would be 422, not 500).');
                    }
                    if ($user->trashed() || $user->status != User::STATUS_CAN_LOGIN) {
                        throw new \RuntimeException('Target cannot log in (would be 422, not 500).');
                    }

                    return true;
                },
                'createToken (impersonation)' => fn () => (bool) $user->createToken('impersonation:'.$admin->id.':'.now()->timestamp)->plainTextToken,
                'Log::add impersonate_start' => function () use ($user) {
                    $user->loadMissing('centers');
                    Log::add('impersonate_start', $user, $user->id, 'diagnose', $user->centers[0]->id ?? 0);

                    return true;
                },
                'json_encode full response (strict UTF-8)' => fn () => $this->strictJsonEncode([
                    'user' => UserResource::forSession($user)->resolve(request()),
                    'token' => 'x',
                    'status' => true,
                ]),
            ]);
        } finally {
            DB::rollBack();
            Auth::forgetUser();
        }

        return $ok;
    }

    /**
     * @param  array<string, callable>  $steps
     */
    private function runSteps(array $steps): bool
    {
        foreach ($steps as $label => $step) {
            try {
                $result = $step();
                if (is_array($result)) {
                    $this->info("OK: {$label} (keys: ".implode(', ', array_keys($result)).')');
                } else {
                    $this->info("OK: {$label}".(is_bool($result) ? ' = '.($result ? 'true' : 'false') : ''));
                }
            } catch (Throwable $e) {
                $this->reportFailure($label, $e);

                return false;
            }
        }

        return true;
    }

    private function strictJsonEncode($data): bool
    {
        try {
            json_encode($data, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $fields = $this->findInvalidUtf8Fields($data);
            throw new \RuntimeException(
                $e->getMessage().($fields ? ' — invalid UTF-8 in: '.implode(', ', $fields) : '')
            );
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function findInvalidUtf8Fields($data, string $prefix = ''): array
    {
        $bad = [];
        foreach ((array) $data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
                $bad[] = $path;
            } elseif (is_array($value) || $value instanceof \Traversable) {
                $bad = array_merge($bad, $this->findInvalidUtf8Fields(
                    $value instanceof \Traversable ? iterator_to_array($value) : $value,
                    $path
                ));
            }
        }

        return $bad;
    }

    private function reportFailure(string $label, Throwable $e): void
    {
        $this->error("FAIL: {$label}");
        $this->line(get_class($e).': '.$e->getMessage());
        $this->line($e->getFile().':'.$e->getLine());
    }
}
