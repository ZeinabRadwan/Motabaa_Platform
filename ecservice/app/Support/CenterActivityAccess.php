<?php

namespace App\Support;

use App\Models\CenterActivity;
use App\Models\CenterActivityEntry;
use App\Models\Goal;
use App\Models\System\System;
use App\Models\Term;
use App\Models\User;

class CenterActivityAccess
{
    public const MODULE = 'center-activities';

    public const PERM_ACCESS = 'access_center-activities';

    public const PERM_ADD = 'add_center-activities';

    public const PERM_EDIT = 'edit_center-activities';

    public const PERM_DELETE = 'delete_center-activities';

    public const PERM_MANAGE_CONTENT = 'manage_center-activities_content';

    /** @var string[] Legacy permission names removed from the catalog UI */
    public const LEGACY_PERMISSIONS = [
        'admin_center-activities',
        'add_center-activities_entry',
        'upload_center-activities_image',
        'upload_center-activities_video',
        'upload_center-activities_file',
    ];

    public static function viewPermissions(): array
    {
        return [self::PERM_ACCESS];
    }

    public static function userCanViewModule(User $user): bool
    {
        return can(self::PERM_ACCESS, $user);
    }

    public static function userCanCreateActivity(User $user): bool
    {
        return can(self::PERM_ADD, $user);
    }

    public static function userCanEditActivity(User $user, CenterActivity $activity): bool
    {
        if (! can(self::PERM_EDIT, $user)) {
            return false;
        }

        return self::userCanViewActivity($user, $activity);
    }

    public static function userCanDeleteActivity(User $user): bool
    {
        return can(self::PERM_DELETE, $user);
    }

    public static function userCanManageContent(User $user): bool
    {
        return can(self::PERM_MANAGE_CONTENT, $user);
    }

    public static function userCanDeleteEntry(User $user, CenterActivityEntry $entry): bool
    {
        if (! self::userCanManageContent($user)) {
            return false;
        }

        if (can(self::PERM_DELETE, $user)) {
            return true;
        }

        return (int) $entry->user_id === (int) $user->id;
    }

    public static function userCanViewActivity(User $user, CenterActivity $activity, $requestedCenterId = null): bool
    {
        if (! self::userCanViewModule($user)) {
            return false;
        }

        $centerId = Term::resolveCenterId($user, $requestedCenterId ?? $activity->center_id);
        if (! $centerId || (int) $activity->center_id !== (int) $centerId) {
            return false;
        }

        if (can(self::PERM_DELETE, $user)) {
            return true;
        }

        if ((int) $activity->created_by === (int) $user->id) {
            return true;
        }

        $activity->loadMissing('visibleRoles:id');
        $visibleRoleIds = $activity->visibleRoles->pluck('id')->all();

        if ($visibleRoleIds === []) {
            return true;
        }

        $userRoleIds = $user->roles->pluck('id')->all();

        return count(array_intersect($visibleRoleIds, $userRoleIds)) > 0;
    }

    public static function caseIdsEnrolledInTerm(?int $termId): array
    {
        if (! $termId) {
            return [];
        }

        return Goal::query()
            ->where('term_id', $termId)
            ->distinct()
            ->pluck('case_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public static function isParentAccount(User $user): bool
    {
        return isParentUser($user) || isHasRole(System::USER_TYPE_PARENT_ROLE_NAME, $user);
    }

    public static function parentIsInActivityTermScope(User $user, CenterActivity $activity): bool
    {
        if (! self::isParentAccount($user) || ! $activity->term_id) {
            return false;
        }

        $centerId = Term::resolveCenterId($user, null);
        if (! $centerId || (int) $activity->center_id !== (int) $centerId) {
            return false;
        }

        $caseIds = self::caseIdsEnrolledInTerm((int) $activity->term_id);
        if ($caseIds === []) {
            return false;
        }

        return $user->scaseParent()->whereIn('scases.id', $caseIds)->exists();
    }

    public static function parentCanAccessEntry(User $user, CenterActivityEntry $entry): bool
    {
        $entry->loadMissing('activity');

        if (! $entry->activity || ! $entry->parents_can_see) {
            return false;
        }

        return self::parentIsInActivityTermScope($user, $entry->activity);
    }

    public static function parentVisibleTermIds(User $user): array
    {
        if (! self::isParentAccount($user)) {
            return [];
        }

        $caseIds = $user->scaseParent()->pluck('scases.id')->map(fn ($id) => (int) $id)->all();
        if ($caseIds === []) {
            return [];
        }

        return Goal::query()
            ->whereIn('case_id', $caseIds)
            ->distinct()
            ->pluck('term_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
