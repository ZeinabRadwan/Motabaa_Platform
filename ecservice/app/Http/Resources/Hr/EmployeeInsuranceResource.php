<?php

namespace App\Http\Resources\Hr;

use App\Models\Hr\EmployeeInsurance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeInsuranceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'effective_from' => $this->effective_from->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'category' => $this->category,
            'category_label' => EmployeeInsurance::categoryLabels()[$this->category] ?? $this->category,
            'employee_rate' => $this->employee_rate,
            'employer_rate' => $this->employer_rate,
            'total_rate' => $this->totalRate(),
            'contribution_start_date' => $this->contribution_start_date?->toDateString(),
            'subscription_number' => $this->subscription_number,
            'reason' => $this->reason,
            'is_current' => $this->isEffectiveOn(today()),
            'is_scheduled' => $this->isScheduled(),
            'created_by' => $this->creator?->name,
            'created_at' => $this->created_at,
        ];
    }
}
