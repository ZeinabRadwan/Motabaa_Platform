<?php

namespace App\Http\Resources;

use App\Support\CenterActivityAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CenterActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'center_id' => $this->center_id,
            'term_id' => $this->term_id,
            'created_by' => $this->created_by,
            'entries_count' => $this->when(isset($this->entries_count), $this->entries_count),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'term' => $this->whenLoaded('term', fn () => [
                'id' => $this->term->id,
                'title' => $this->term->name,
            ]),
            'visible_roles' => $this->when(
                $request->user()
                    && $this->relationLoaded('visibleRoles')
                    && CenterActivityAccess::userCanEditActivity($request->user(), $this->resource),
                fn () => $this->visibleRoles->map(fn ($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                ])->values()
            ),
            'created_at' => $this->created_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
