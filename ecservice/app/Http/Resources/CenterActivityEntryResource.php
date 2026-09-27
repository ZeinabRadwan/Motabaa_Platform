<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CenterActivityEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $urlFile = $this->skipsAttachmentLookup() ? null : $this->urlFile();

        return [
            'id' => $this->id,
            'center_activity_id' => $this->center_activity_id,
            'user_id' => $this->user_id,
            'log_work' => 0,
            'parents_can_see' => $this->parents_can_see,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'picture' => $this->user->urlImage(),
                'roles' => collect($this->user->roles)->map(fn ($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'default_name' => $role->default_name ?? null,
                ])->values(),
            ] : null,
            'content' => $this->content,
            'file' => $urlFile ? $urlFile['file'] : null,
            'is_file_video' => $urlFile ? $urlFile['is_video'] : null,
            'image' => $this->skipsAttachmentLookup() ? null : $this->urlImage(),
            'video' => $this->skipsAttachmentLookup() ? null : $this->urlVideo(),
            'activity' => $this->whenLoaded('activity', fn () => [
                'id' => $this->activity->id,
                'title' => $this->activity->title,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
