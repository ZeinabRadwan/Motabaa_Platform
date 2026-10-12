<?php

namespace App\Support;

use App\Models\MeetingRoom;
use App\Models\User;
use App\Notifications\MeetingRoomAvailableNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MeetingRoomNotifier
{
    public static function syncForRoom(MeetingRoom $room): void
    {
        if (! MeetingRoomAccess::isParentSpecificAudience($room) || ! Schema::hasTable('notifications')) {
            return;
        }

        $room->loadMissing('assignedParents');
        $assignedIds = $room->assignedParents->pluck('id')->map(fn ($id) => (int) $id);
        if ($assignedIds->isEmpty()) {
            return;
        }

        $alreadyNotified = collect();
        if (Schema::hasTable('meeting_room_notified_users')) {
            $alreadyNotified = DB::table('meeting_room_notified_users')
                ->where('meeting_room_id', $room->id)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id);
        }

        $toNotify = $assignedIds->diff($alreadyNotified)->values();
        if ($toNotify->isEmpty()) {
            return;
        }

        $title = __('tr.meeting_rooms.notification_new_title');
        $body = __('tr.meeting_rooms.notification_new_body', ['room' => $room->getNameAttribute()]);
        $url = rtrim((string) config('app.front_url'), '/').'/meeting-rooms/view/'.$room->id;

        User::query()
            ->whereIn('id', $toNotify->all())
            ->get()
            ->each(function (User $user) use ($room, $title, $body, $url) {
                $user->notify(new MeetingRoomAvailableNotification($room, $title, $body, $url));

                if (Schema::hasTable('meeting_room_notified_users')) {
                    DB::table('meeting_room_notified_users')->insertOrIgnore([
                        'meeting_room_id' => $room->id,
                        'user_id' => $user->id,
                        'notified_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }
}
