<?php

namespace App\Http\Resources\Hr;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeWorkAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $location = $this->workLocation;

        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->branch?->name,
            'work_location_id' => $this->work_location_id,
            'work_location_name' => $location?->name,
            'work_location' => $location ? [
                'id' => $location->id,
                'name' => $location->name,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'radius_meters' => $location->radius_meters,
                'has_coordinates' => $location->hasCoordinates(),
            ] : null,
            'effective_from' => $this->effective_from->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'reason' => $this->reason,
            'is_current' => $this->isEffectiveOn(today()),
            'is_scheduled' => $this->isScheduled(),
            'created_by' => $this->creator?->name,
            'created_at' => $this->created_at,
        ];
    }
}
