<?php

namespace App\Http\Resources\Hr;

use App\Models\Hr\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeAttendanceConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $policy = $this->attendancePolicy;

        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'attendance_mode' => $this->attendance_mode,
            'attendance_mode_label' => Employee::attendanceModeLabels()[$this->attendance_mode] ?? $this->attendance_mode,
            'gps_policy' => $this->gps_policy,
            'gps_policy_label' => Employee::gpsPolicyLabels()[$this->gps_policy] ?? $this->gps_policy,
            'attendance_policy_id' => $this->attendance_policy_id,
            'attendance_policy' => $policy ? [
                'id' => $policy->id,
                'code' => $policy->code,
                'name' => $policy->name,
                'deleted_at' => $policy->deleted_at,
            ] : null,
            'grace_minutes' => $this->grace_minutes,
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
