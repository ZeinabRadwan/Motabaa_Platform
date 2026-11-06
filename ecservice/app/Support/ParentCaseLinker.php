<?php

namespace App\Support;

use App\Models\CenterUser;
use App\Models\SCase;
use App\Models\System\System;
use App\Models\User;

class ParentCaseLinker
{
    /**
     * Attach a parent user to a case without removing other parents. Ensures center membership.
     */
    public static function linkParentToCase(User $parent, SCase $case): void
    {
        if (! isParentUser($parent)) {
            return;
        }

        $alreadyLinked = $case->parents()->where('users.id', $parent->id)->exists();
        if (! $alreadyLinked) {
            $case->parents()->attach($parent->id, ['relationship_type' => System::USER_TYPE_PARENT]);
        }

        if ($case->center_id && ! $parent->isInCenter($case->center_id)) {
            CenterUser::firstOrCreate([
                'center_id' => $case->center_id,
                'user_id' => $parent->id,
            ]);
        }
    }
}
