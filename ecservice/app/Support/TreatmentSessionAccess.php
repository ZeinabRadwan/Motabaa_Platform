<?php

namespace App\Support;

use App\Models\Goal;
use App\Models\Term;

class TreatmentSessionAccess
{
    /**
     * Support Services treatment goals (non-educational, non-independent).
     */
    public static function isTreatmentSupportGoal(?Goal $goal): bool
    {
        if (! $goal) {
            return false;
        }

        $category = $goal->category;

        return $category !== 'educational' && $category !== 'independent';
    }

    public static function denyParentTreatmentSessionManagement(?Goal $goal = null)
    {
        $user = auth()->user();
        if (! isParentUser($user)) {
            return null;
        }

        if ($goal !== null && ! self::isTreatmentSupportGoal($goal)) {
            return null;
        }

        return Term::forbiddenResponse();
    }
}
