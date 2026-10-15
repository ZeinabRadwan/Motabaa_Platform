<?php

namespace App\Http\Resources;

use App\Models\MeetingRoom;
use App\Support\MeetingRoomAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingRoomsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $canAccess = $user && MeetingRoomAccess::userCanAccess($user, $this->resource);
        $canManage = $user && ($user->can('edit_meetings') || $user->can('admin_meetings'));

        return [
            'id' => $this->id,
            'title' => $this->name,
            'type' => $this->when($canManage, $this->type),
            'type_name' => $this->when($canManage, fn () => $this->typeName()),
            'audience_type' => $this->audience_type ?? \App\Models\MeetingRoom::AUDIENCE_GENERAL,
            'audience_type_name' => $this->audienceTypeName(),
            'meeting_url' => $canAccess && MeetingRoomAccess::userCanViewMeetingUrl($user, $this->resource)
                ? $this->meetingUrl()
                : null,
            'assigned_parent_ids' => $this->when($canManage, function () {
                if ($this->relationLoaded('assignedParents')) {
                    return $this->assignedParents->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
                }

                return $this->assignedParents()->pluck('users.id')->map(fn ($id) => (int) $id)->values()->all();
            }),
            'assigned_parents' => $this->when(
                $canManage && $this->relationLoaded('assignedParents'),
                fn () => $this->assignedParents->map(fn ($p) => [
                    'id' => (int) $p->id,
                    'name' => $p->name,
                ])->values()->all()
            ),
            'assigned_parents_count' => $this->when(
                $canManage && ($this->audience_type ?? MeetingRoom::AUDIENCE_GENERAL) === MeetingRoom::AUDIENCE_PARENTS,
                function () {
                    if ($this->relationLoaded('assignedParents')) {
                        return $this->assignedParents->count();
                    }

                    return (int) $this->assignedParents()->count();
                }
            ),
            'user' => new \App\Http\Resources\Admin\User\UserGeneralResource($this->user),
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
