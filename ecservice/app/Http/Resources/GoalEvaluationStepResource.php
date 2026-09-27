<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoalEvaluationStepResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = [
            'id' => $this->id,
            'goal_id' => $this->goal_id,
            'goal' => $this->goal,
            'value' => $this->value,
            'service_type' => $this->service_type,
            'date' => $this->date,
            'time' => $this->time ? substr((string) $this->time, 0, 5) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if (isParentUser($request->user())) {
            unset($payload['date'], $payload['time']);
        }

        return $payload;
    }
}
