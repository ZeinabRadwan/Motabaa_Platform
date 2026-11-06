<?php

namespace App\Http\Resources\Hr;

use App\Models\Hr\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $labels = LeaveType::optionLabels();

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'category' => $this->category,
            'category_label' => $labels['categories'][$this->category] ?? $this->category,
            'is_system' => $this->is_system,
            'is_paid' => $this->is_paid,
            'balance_mode' => $this->balance_mode,
            'balance_mode_label' => $labels['balance_modes'][$this->balance_mode] ?? $this->balance_mode,
            'days_per_year' => $this->days_per_year,
            'carry_over_max_days' => $this->carry_over_max_days,
            'max_days_per_request' => $this->max_days_per_request,
            'day_counting' => $this->day_counting,
            'day_counting_label' => $labels['day_countings'][$this->day_counting] ?? $this->day_counting,
            'requires_attachment' => $this->requires_attachment,
            'requires_hr_approval' => $this->requires_hr_approval,
            'allow_self_request' => $this->allow_self_request,
            'color' => $this->color,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
