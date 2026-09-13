<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessagesResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $urlFile = $this->skipsAttachmentLookup() ? null : $this->urlFile();
        return [
            'id' => $this->id,
            'goal_id' => $this->goal_id,
            'user_id' => $this->user_id,
            'type' => $this->type,
            'log_work' => $this->log_work,
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
            'goal' => $this->whenLoaded('goal', function () {
                return [
                    'id' => $this->goal->id,
                    'title' => $this->goal->title,
                ];
            }),
            'created_at' => $this->created_at,

        ];
    }
}
