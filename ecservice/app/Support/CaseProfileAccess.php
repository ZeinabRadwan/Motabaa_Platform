<?php

namespace App\Support;

use App\Models\SCase;
use App\Models\Term;
use App\Models\User;

/**
 * Case-profile tabs (session clips, case documents) for every viewer, not only parents.
 *
 * Parents: the case must be linked to them. Staff: the case must belong to a center the user
 * works in (platform admins may open any center, as in `Term::resolveCenterId`) and pass the
 * same case visibility rule as the case profile itself (`SCase::userCanAccessCase`).
 * The center comes from the case, not the request, because uploads are sent as FormData
 * without the client's center_id.
 */
class CaseProfileAccess
{
    public const FILE_MANAGE_PERMISSIONS = ['edit_cases', 'admin_cases'];

    public static function canView(?User $user, ?SCase $case): bool
    {
        if (! $user || ! $case) {
            return false;
        }

        if (isParentUser($user)) {
            return SCase::userCanAccessCase($user, $case);
        }

        $caseCenter = (int) $case->center_id;
        if ($caseCenter <= 0 || (int) Term::resolveCenterId($user, $caseCenter) !== $caseCenter) {
            return false;
        }

        return SCase::userCanAccessCase($user, $case);
    }

    public static function canManageFiles(?User $user, ?SCase $case): bool
    {
        return $user !== null
            && ! isParentUser($user)
            && canAny(self::FILE_MANAGE_PERMISSIONS, $user)
            && self::canView($user, $case);
    }

    /**
     * @param  SCase|int|string|null  $case
     * @return \Illuminate\Http\JsonResponse|null
     */
    public static function denyView($case, ?User $user = null)
    {
        $user = $user ?? auth()->user();

        return self::canView($user, self::resolveCase($case)) ? null : Term::forbiddenResponse();
    }

    /**
     * @return \Illuminate\Http\JsonResponse|null
     */
    public static function denyManageFiles(?SCase $case, ?User $user = null)
    {
        $user = $user ?? auth()->user();

        return self::canManageFiles($user, $case) ? null : Term::forbiddenResponse();
    }

    /**
     * @param  SCase|int|string|null  $case
     */
    private static function resolveCase($case): ?SCase
    {
        if ($case instanceof SCase) {
            return $case;
        }

        if ($case === null || $case === '' || ! is_numeric($case)) {
            return null;
        }

        return SCase::withTrashed()->find((int) $case);
    }
}
