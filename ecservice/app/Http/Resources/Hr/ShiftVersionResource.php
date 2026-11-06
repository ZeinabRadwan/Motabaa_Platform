<?php

namespace App\Http\Resources\Hr;

use App\Support\Hr\HrShiftMath;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $timing = $this->timingAttributes();
        $hasHours = $timing['rotation_pattern'] === null && $timing['start_time'] !== null;

        return [
            'id' => $this->id,
            'shift_id' => $this->shift_id,
            'start_time' => $hasHours ? $timing['start_time'] : null,
            'end_time' => $hasHours ? $timing['end_time'] : null,
            'is_overnight' => $hasHours && HrShiftMath::isOvernight($timing['start_time'], $timing['end_time']),
            'break_start_time' => $timing['break_start_time'],
            'break_minutes' => $timing['break_minutes'],
            'expected_minutes' => $timing['expected_minutes'],
            'effective_expected_minutes' => $hasHours
                ? HrShiftMath::expectedMinutes($timing['start_time'], $timing['end_time'], $timing['break_minutes'], $timing['expected_minutes'])
                : null,
            'grace_minutes' => $timing['grace_minutes'],
            'early_leave_grace_minutes' => $timing['early_leave_grace_minutes'],
            'working_days' => $this->working_days_list,
            'rest_days' => $this->rest_days_list,
            'rotation_pattern' => $timing['rotation_pattern'] ?? [],
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
