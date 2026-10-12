<?php

namespace App\Http\Resources;

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
            'type' => $this->type,
            'type_name' => $this->typeName(),
            'audience_type' => $this->audience_type ?? \App\Models\MeetingRoom::AUDIENCE_GENERAL,
            'audience_type_name' => $this->audienceTypeName(),
            'meeting_url' => $canAccess && MeetingRoomAccess::userCanViewMeetingUrl($user, $this->resource)
                ? $this->meetingUrl()
                : null,
            'assigned_parent_ids' => $this->when($canManage, function () {
                return $this->assignedParents()->pluck('users.id')->map(fn ($id) => (int) $id)->values()->all();
            }),
            'user' => new \App\Http\Resources\Admin\User\UserGeneralResource($this->user),
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
