<?php

namespace App\Notifications;

use App\Models\MeetingRoom;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MeetingRoomAvailableNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly MeetingRoom $room,
        private readonly string $title,
        private readonly string $body,
        private readonly string $url,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->body,
            'url' => $this->url,
            'meeting_room_id' => (int) $this->room->id,
            'room_title' => $this->room->getNameAttribute(),
        ];
    }
}
