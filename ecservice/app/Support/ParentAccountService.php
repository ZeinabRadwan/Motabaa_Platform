<?php

namespace App\Support;

use App\Models\CenterUser;
use App\Models\User;

class ParentAccountService
{
    /**
     * Reuse an existing parent-only account (center membership, optional profile touch-ups).
     */
    public static function reuseExistingParent(User $parent, ?int $centerId = null, array $input = []): User
    {
        if ($centerId && ! $parent->isInCenter($centerId)) {
            CenterUser::firstOrCreate([
                'center_id' => $centerId,
                'user_id' => $parent->id,
            ]);
        }

        if (! empty($input['name']) && (! $parent->name || trim($parent->name) === '')) {
            $parent->name = $input['name'];
            $parent->save();
        }

        return $parent->fresh(['roles', 'centers']);
    }

    /**
     * @return array{blocked: bool, reason: string|null, user_id: int|null}
     */
    public static function nonParentContactBlock(?string $email, ?string $phone): array
    {
        $users = ParentAccountResolver::nonParentUsersMatchingContact($email, $phone);
        if ($users->isEmpty()) {
            return ['blocked' => false, 'reason' => null, 'user_id' => null];
        }

        $blocker = $users->first();

        return [
            'blocked' => true,
            'reason' => 'contact_belongs_to_non_parent_account',
            'user_id' => (int) $blocker->id,
        ];
    }
}
