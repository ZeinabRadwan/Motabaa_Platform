<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentsResource extends JsonResource
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
            'center_id' => $this->center_id,
            'title' => $this->assessment_title,
            'type' => $this->type_name,
            'category' => $this->category,
            'evaluation_method_id' => $this->evaluation_method_id,
            'evaluation_method' => $this->whenLoaded('evaluation_method'),
            'answer' => $this->whenLoaded('assessment_evaluation'),
            'total_goals'=>  $this->when($this->total_goals !== null, $this->total_goals),
            'total_weak_goals'=>  $this->when($this->total_weak_goals !== null, $this->total_weak_goals),
            'total_power_goals'=>  $this->when($this->total_power_goals !== null, $this->total_power_goals),
            'children' => self::collection($this->whenLoaded('recursiveChildren')),
            'deleted_at' => $this->deleted_at,
        ];
    }
}
