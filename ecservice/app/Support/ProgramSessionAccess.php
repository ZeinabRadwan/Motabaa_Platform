<?php

namespace App\Support;

use App\Models\Goal;
use App\Models\Term;

class ProgramSessionAccess
{
    /**
     * Qualifying programme (educational) or support services treatment goals — not independent skills.
     */
    public static function isProgramSessionGoal(?Goal $goal): bool
    {
        if (! $goal) {
            return false;
        }

        if ($goal->category === 'educational') {
            return true;
        }

        return TreatmentSessionAccess::isTreatmentSupportGoal($goal);
    }

    /**
     * @return \Illuminate\Http\JsonResponse|null
     */
    public static function denyParentProgramSessionManagement(?Goal $goal = null)
    {
        if (! isParentUser()) {
            return null;
        }

        if ($goal !== null && ! self::isProgramSessionGoal($goal)) {
            return null;
        }

        return Term::forbiddenResponse();
    }
}
