<?php

namespace App\Http\Resources\Hr;

use App\Models\Hr\AttendancePeriodSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendancePeriodSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $values = [
            'id' => $this->id,
            'attendance_period_id' => $this->attendance_period_id,
            'employee' => EmployeeReference::make($this->employee),
            'branch_id' => $this->branch_id,
            'branch_name' => $this->branch?->name,
        ];
        foreach (AttendancePeriodSummary::COUNTERS as $counter) {
            $values[$counter] = $this->{$counter};
        }

        return $values + [
            'leave_breakdown' => $this->leave_breakdown ?? [],
            'leave_requests' => $this->leave_requests ?? [],
            'exception_details' => $this->exception_details ?? [],
        ];
    }
}
