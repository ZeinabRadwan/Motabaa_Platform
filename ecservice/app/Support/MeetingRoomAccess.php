<?php

namespace App\Support;

use App\Models\MeetingRoom;
use App\Models\System\System;
use App\Models\User;

class MeetingRoomAccess
{
    public static function isParentSpecificAudience(MeetingRoom $room): bool
    {
        return ($room->audience_type ?? MeetingRoom::AUDIENCE_GENERAL) === MeetingRoom::AUDIENCE_PARENTS;
    }

    public static function userCanAccess(?User $user, MeetingRoom $room): bool
    {
        if (! $user) {
            return false;
        }

        if ($user instanceof User && (int) $user->getAttribute('status') !== User::STATUS_CAN_LOGIN) {
            return false;
        }

        if (! $user->isInCenter((int) $room->center_id)) {
            return false;
        }

        if ($user->can('admin_meetings')) {
            return true;
        }

        if (self::isParentSpecificAudience($room)) {
            if (self::assignedParentCanAccess($user, $room)) {
                return true;
            }

            if ($user->can('edit_meetings') && (int) $room->created_by_id === (int) $user->id) {
                return true;
            }

            if ($user->can('show_meetings') || $user->can('access_meetings')) {
                return (int) $room->created_by_id === (int) $user->id
                    || (int) $room->type === MeetingRoom::TYPE_ADMINISTRATIVE;
            }

            return false;
        }

        if (self::userHasParentRole($user)) {
            return self::parentCanAccessGeneralAudienceRoom($user, $room);
        }

        if ($user->can('edit_meetings') || $user->can('show_meetings') || $user->can('access_meetings')) {
            return (int) $room->created_by_id === (int) $user->id
                || (int) $room->type === MeetingRoom::TYPE_ADMINISTRATIVE;
        }

        return false;
    }

    public static function userCanViewMeetingUrl(?User $user, MeetingRoom $room): bool
    {
        return self::userCanAccess($user, $room);
    }

    public static function parentCanAccessGeneralAudienceRoom(User $user, MeetingRoom $room): bool
    {
        if ((int) $room->type === MeetingRoom::TYPE_GENERAL) {
            return true;
        }

        $user->loadMissing(['scaseParent.teacher:id', 'scaseParent.specialists:id']);
        $teacherIds = $user->scaseParent->pluck('teacher.*.id')->flatten()->all();
        $specialistIds = $user->scaseParent->pluck('specialists.*.id')->flatten()->all();
        $staffIds = array_merge($teacherIds, $specialistIds);

        return in_array((int) $room->created_by_id, array_map('intval', $staffIds), true);
    }

    /**
     * @param  array<int, int>  $parentIds
     * @return array{valid: bool, response: ?\Illuminate\Http\JsonResponse}
     */
    public static function validateParentAssignments(int $centerId, array $parentIds): array
    {
        $parentIds = array_values(array_unique(array_map('intval', $parentIds)));
        if ($parentIds === []) {
            return ['valid' => false, 'response' => response()->json([
                'errors' => ['parent_ids' => [__('validation.meeting_rooms.Parents required')]],
            ], 422)];
        }

        $eligible = User::query()
            ->whereIn('id', $parentIds)
            ->whereHas('centers', fn ($q) => $q->where('centers.id', $centerId))
            ->whereHas('roles', fn ($q) => $q->where('roles.default_name', System::USER_TYPE_PARENT_ROLE_NAME))
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($eligible->count() !== count($parentIds)) {
            return ['valid' => false, 'response' => response()->json([
                'errors' => ['parent_ids' => [__('validation.meeting_rooms.Invalid parent selection')]],
            ], 422)];
        }

        return ['valid' => true, 'response' => null];
    }

    public static function userHasParentRole(User $user): bool
    {
        return isHasRole(System::USER_TYPE_PARENT_ROLE_NAME, $user);
    }

    public static function assignedParentCanAccess(User $user, MeetingRoom $room): bool
    {
        if (! self::userHasParentRole($user)) {
            return false;
        }

        return $room->assignedParents()->where('users.id', $user->id)->exists();
    }
}
