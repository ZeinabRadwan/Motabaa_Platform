<?php

namespace App\Support;

use App\Models\SCase;
use App\Models\Term;
use App\Models\User;

/**
 * Case-profile endpoints (files, image, PDFs, statistics, case messages) for parent-only accounts:
 * the case must be linked to the parent. Staff/admin behavior is left to the existing checks.
 */
class ParentCaseAccess
{
    public static function shouldDeny(bool $isParent, bool $caseExists, bool $isLinked): bool
    {
        if (! $isParent) {
            return false;
        }

        return ! $caseExists || ! $isLinked;
    }

    /**
     * @param  SCase|int|string|null  $case
     * @return \Illuminate\Http\JsonResponse|null
     */
    public static function denyIfParentCannotAccessCase($case, ?User $user = null)
    {
        $user = $user ?? auth()->user();
        $isParent = $user !== null && isParentUser($user);

        if (! $isParent) {
            return null;
        }

        if ($case !== null && ! $case instanceof SCase) {
            $case = SCase::withTrashed()->find($case);
        }

        $caseExists = $case instanceof SCase;
        $isLinked = $caseExists && SCase::userCanAccessCase($user, $case);

        return self::shouldDeny($isParent, $caseExists, $isLinked)
            ? Term::forbiddenResponse()
            : null;
    }
}
