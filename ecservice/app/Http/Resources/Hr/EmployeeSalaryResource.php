<?php

namespace App\Http\Resources\Hr;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeSalaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'effective_from' => $this->effective_from->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'basic_salary' => $this->basic_salary,
            'housing_allowance' => $this->housing_allowance,
            'transportation_allowance' => $this->transportation_allowance,
            'other_allowances' => $this->other_allowances,
            'other_allowances_note' => $this->other_allowances_note,
            'gross_salary' => $this->grossSalary(),
            'currency' => $this->currency,
            'reason' => $this->reason,
            'is_current' => $this->isEffectiveOn(today()),
            'is_scheduled' => $this->isScheduled(),
            'created_by' => $this->creator?->name,
            'created_at' => $this->created_at,
        ];
    }
}
