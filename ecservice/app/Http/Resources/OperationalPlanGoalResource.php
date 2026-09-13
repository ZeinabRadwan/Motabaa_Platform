<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperationalPlanGoalResource extends JsonResource
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
            'operational_plan_id' => $this->operational_plan_id,
            'department' => $this->department,
            'general_goal' => $this->general_goal,
            'activities_and_programs' => $this->activities_and_programs,
            'targeted_by' => $this->targeted_by,
            'implemented_by' => $this->implemented_by,
            'goals_services' => $this->goals_services,
            'performance_indicator' => $this->performance_indicator,
            'reference_feed' => $this->reference_feed,
            'status' => $this->status,
            'implemented_at' => $this->implemented_at,
            'action_by' => $this->actionBy ? $this->actionBy->name : '',
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
