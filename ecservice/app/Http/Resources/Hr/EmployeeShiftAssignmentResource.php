<?php

namespace App\Http\Resources\Hr;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeShiftAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $shift = $this->shift;
        $workingOverride = $this->workingDaysOverride();
        $restOverride = $this->restDaysOverride();

        return [
            'id' => $this->id,
            'shift_id' => $this->shift_id,
            'shift_name' => $shift?->name,
            'shift_code' => $shift?->code,
            'shift_type' => $shift?->shift_type,
            'color' => $shift?->color,
            'is_rotating' => (bool) $shift?->isRotating(),
            'cycle_length' => $shift?->cycleLength() ?? 0,
            'time_range' => $shift?->timeRange(),
            'working_days' => $workingOverride ?? $shift?->working_days_list ?? [],
            'rest_days' => $restOverride ?? array_values(array_diff($shift?->rest_days_list ?? [], $workingOverride ?? [])),
            'has_day_overrides' => $workingOverride !== null || $restOverride !== null,
            'working_days_override' => $workingOverride,
            'rest_days_override' => $restOverride,
            'rotation_offset' => $this->rotation_offset,
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
