<?php

namespace App\Http\Resources\Hr;

use App\Models\Hr\AttendancePeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendancePeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->branch?->name,
            'period_start' => $this->period_start->toDateString(),
            'period_end' => $this->period_end->toDateString(),
            'days' => $this->period_start->diffInDays($this->period_end) + 1,
            'status' => $this->status,
            'status_label' => AttendancePeriod::statusLabels()[$this->status] ?? $this->status,
            'employees_count' => $this->employees_count,
            'totals' => $this->totals,
            'notes' => $this->notes,
            'processed_at' => $this->processed_at,
            'processed_by' => minimalUserReference($this->processor),
            'approved_at' => $this->approved_at,
            'approved_by' => minimalUserReference($this->approver),
            'reopened_at' => $this->reopened_at,
            'reopened_by' => minimalUserReference($this->reopener),
            'reopen_reason' => $this->reopen_reason,
            'created_by' => minimalUserReference($this->creator),
            'created_at' => $this->created_at,
        ];
    }
}
