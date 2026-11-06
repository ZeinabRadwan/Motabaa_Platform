<?php

namespace App\Http\Resources\Hr;

use App\Models\Hr\ShiftException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftExceptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $employee = $this->employee;

        return [
            'id' => $this->id,
            'scope' => $this->targetScope(),
            'employee_id' => $this->employee_id,
            'employee_name' => $employee?->user?->name,
            'employee_number' => $employee?->employee_number,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->branch?->name,
            'date_from' => $this->date_from->toDateString(),
            'date_to' => $this->date_to->toDateString(),
            'days' => $this->date_from->diffInDays($this->date_to) + 1,
            'exception_type' => $this->exception_type,
            'exception_type_label' => ShiftException::typeLabels()[$this->exception_type] ?? $this->exception_type,
            'shift_id' => $this->shift_id,
            'shift_name' => $this->shift?->name,
            'shift_code' => $this->shift?->code,
            'shift_color' => $this->shift?->color,
            'time_range' => $this->exception_type === ShiftException::TYPE_SHIFT
                ? $this->shift?->timeRange()
                : ($this->start_time ? substr((string) $this->start_time, 0, 5).' - '.substr((string) $this->end_time, 0, 5) : null),
            'start_time' => $this->start_time ? substr((string) $this->start_time, 0, 5) : null,
            'end_time' => $this->end_time ? substr((string) $this->end_time, 0, 5) : null,
            'break_minutes' => $this->break_minutes,
            'reason' => $this->reason,
            'is_past' => $this->date_to->lt(today()),
            'created_by' => $this->creator?->name,
            'created_at' => $this->created_at,
        ];
    }
}
