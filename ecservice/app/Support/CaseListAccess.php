<?php

namespace App\Support;

use App\Models\User;

class CaseListAccess
{
    public const PERM_MINE = 'access_cases_mine';

    public const PERM_ALL = 'access_cases_all';

    /** @deprecated Replaced by PERM_MINE / PERM_ALL; kept for middleware during migration */
    public const PERM_LEGACY = 'access_cases';

    public static function canAccessList(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->can('admin_cases')
            || $user->can(self::PERM_ALL)
            || $user->can(self::PERM_MINE)
            || $user->can(self::PERM_LEGACY);
    }

    /**
     * Full center case list (staff filter allowed). Not based on job title.
     */
    public static function canViewAllCenterCases(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->can('admin_cases')
            || $user->can(self::PERM_ALL);
    }

    public static function listPermissionMiddleware(): string
    {
        return implode('|', [
            self::PERM_MINE,
            self::PERM_ALL,
            self::PERM_LEGACY,
            'admin_cases',
        ]);
    }

    public static function allScopePermissionMiddleware(): string
    {
        return self::PERM_ALL.'|admin_cases';
    }
}
