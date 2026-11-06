<?php

namespace App\Support\Hr;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Resolves the center an HR request operates on, using the same rule as the existing staff modules
 * (EmployeeLeaveController, StatisticsController::hr_dashboard): an explicit center_id the user belongs
 * to, otherwise the user's first center.
 */
class HrContext
{
    public static function centerId(Request $request, ?User $user = null): ?int
    {
        $user = $user ?? $request->user();
        if (! $user) {
            return null;
        }

        $requested = $request->input('center_id');
        if ($requested && $user->isInCenter($requested)) {
            return (int) $requested;
        }

        $user->loadMissing('centers');
        $first = $user->centers->first();

        return $first ? (int) $first->id : null;
    }

    /** Salary and insurance values are returned only to holders of an hr-compensation permission. */
    public static function canViewCompensation(?User $user = null): bool
    {
        return canAny(['access_hr-compensation', 'edit_hr-compensation', 'admin_hr-compensation'], $user);
    }

    public static function requireCenterId(Request $request, ?User $user = null): int
    {
        $centerId = self::centerId($request, $user);
        if (! $centerId) {
            throw new HrException('no_center', 'error', 403);
        }

        return $centerId;
    }
}
