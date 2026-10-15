<?php

namespace App\Support;

use App\Models\MeetingRoom;
use App\Models\MeetingRoomAttendance;
use App\Models\MeetingRoomLinkGrant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class MeetingRoomAttendanceReport
{
    public static function tablesReady(): bool
    {
        try {
            return Schema::hasTable('meeting_room_attendances');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return Collection<int, User>
     */
    public static function expectedAttendeeUsers(MeetingRoom $room): Collection
    {
        $room->loadMissing('assignedParents');

        if (MeetingRoomAccess::isParentSpecificAudience($room)) {
            return $room->assignedParents->values();
        }

        if (MeetingRoomAccess::isLinkInviteAudience($room)) {
            if (! Schema::hasTable('meeting_room_link_grants')) {
                return collect();
            }

            $grantUserIds = MeetingRoomLinkGrant::query()
                ->where('meeting_room_id', $room->id)
                ->pluck('user_id');

            if ($grantUserIds->isEmpty()) {
                return collect();
            }

            return User::query()
                ->whereIn('id', $grantUserIds)
                ->get(['id', 'name', 'phone']);
        }

        $parentIds = MeetingRoomAccess::eligibleCenterParentIds((int) $room->center_id);

        if ($parentIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $parentIds)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);
    }

    /**
     * @return array<string, mixed>
     */
    public static function build(MeetingRoom $room): array
    {
        if (! self::tablesReady()) {
            return [
                'audience_type' => $room->audience_type ?? MeetingRoom::AUDIENCE_GENERAL,
                'expected_limited' => false,
                'summary' => ['expected_count' => 0, 'attended_count' => 0, 'absent_count' => 0],
                'attendees' => [],
            ];
        }

        $sessions = MeetingRoomAttendance::query()
            ->with('user:id,name,phone')
            ->where('meeting_room_id', $room->id)
            ->orderByDesc('joined_at')
            ->get();

        $sessionsByUser = $sessions->groupBy('user_id');

        $expected = self::expectedAttendeeUsers($room);
        $isPublic = MeetingRoomAccess::isPublicAudience($room);
        $expectedLimited = $isPublic && $expected->count() > 100;

        $attendedUserIds = $sessionsByUser->keys()->map(fn ($id) => (int) $id);

        if ($expectedLimited) {
            $attendees = self::buildAttendeeRowsFromSessionsOnly($sessionsByUser);
            $attendedCount = $attendees->count();
            $summary = [
                'expected_count' => null,
                'attended_count' => $attendedCount,
                'absent_count' => null,
            ];
        } else {
            $attendees = self::buildAttendeeRowsWithExpected($expected, $sessionsByUser);
            $attendedCount = $attendees->where('attended', true)->count();
            $summary = [
                'expected_count' => $expected->count(),
                'attended_count' => $attendedCount,
                'absent_count' => max(0, $expected->count() - $attendedCount),
            ];
        }

        $staffSessions = $sessions->filter(function (MeetingRoomAttendance $row) use ($expected, $isPublic, $expectedLimited) {
            if ($expectedLimited) {
                return true;
            }
            if ($isPublic) {
                return ! $expected->contains('id', $row->user_id);
            }

            return ! $expected->contains('id', $row->user_id);
        });

        return [
            'audience_type' => $room->audience_type ?? MeetingRoom::AUDIENCE_GENERAL,
            'expected_limited' => $expectedLimited,
            'summary' => $summary,
            'attendees' => $attendees->values()->all(),
            'other_participants' => self::mapSessionsForUsers($staffSessions->groupBy('user_id'))->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, Collection<int, MeetingRoomAttendance>>  $sessionsByUser
     */
    private static function buildAttendeeRowsWithExpected(Collection $expected, Collection $sessionsByUser): Collection
    {
        return $expected->map(function (User $user) use ($sessionsByUser) {
            $userSessions = $sessionsByUser->get($user->id, collect());

            return self::attendeeRow($user, $userSessions);
        });
    }

    /**
     * @param  Collection<int, Collection<int, MeetingRoomAttendance>>  $sessionsByUser
     */
    private static function buildAttendeeRowsFromSessionsOnly(Collection $sessionsByUser): Collection
    {
        return $sessionsByUser->map(function ($userSessions, $userId) {
            $user = $userSessions->first()?->user ?? User::query()->find($userId);

            return self::attendeeRow($user, $userSessions);
        })->filter(fn ($row) => $row !== null);
    }

    /**
     * @param  Collection<int, MeetingRoomAttendance>  $userSessions
     * @return array<string, mixed>|null
     */
    private static function attendeeRow(?User $user, Collection $userSessions): ?array
    {
        if (! $user) {
            return null;
        }

        $sessions = self::formatSessions($userSessions);
        $last = $sessions[0] ?? null;

        return [
            'user_id' => (int) $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'attended' => $sessions !== [],
            'last_joined_at' => $last['joined_at'] ?? null,
            'last_left_at' => $last['left_at'] ?? null,
            'in_meeting' => $userSessions->contains(fn (MeetingRoomAttendance $row) => $row->left_at === null),
            'sessions' => $sessions,
        ];
    }

    /**
     * @param  Collection<int, Collection<int, MeetingRoomAttendance>>  $sessionsByUser
     */
    private static function mapSessionsForUsers(Collection $sessionsByUser): Collection
    {
        return $sessionsByUser->map(function ($userSessions, $userId) {
            $user = $userSessions->first()?->user ?? User::query()->find($userId);

            return self::attendeeRow($user, $userSessions);
        })->filter(fn ($row) => $row !== null);
    }

    /**
     * @param  Collection<int, MeetingRoomAttendance>  $userSessions
     * @return list<array{joined_at: string, left_at: ?string}>
     */
    private static function formatSessions(Collection $userSessions): array
    {
        return $userSessions
            ->sortByDesc('joined_at')
            ->map(fn (MeetingRoomAttendance $row) => [
                'id' => (int) $row->id,
                'joined_at' => $row->joined_at?->toIso8601String(),
                'left_at' => $row->left_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
