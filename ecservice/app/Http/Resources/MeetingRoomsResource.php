<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingRoomsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->name,
            'type' => $this->type,
            'type_name' => $this->typeName(),
            'meeting_url' => 'https://meet.jit.si/tahiledu.com.'.$this->id.'?lang='.app()->getLocale(),
            'user' => new \App\Http\Resources\Admin\User\UserGeneralResource($this->user) ,
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

        ];
    }
}
