<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoalStepsResource extends JsonResource
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
            'procedural_objectives' => $this->procedural_objectives,
            'attempts' => $this->attempts,
            'successful_attempts' => $this->successful_attempts,
            'performance_evaluation' => $this->performance_evaluation,
            'reinforcement' => $this->reinforcement,
            'order' => $this->order,
            'created_by' => $this->createdBy,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
