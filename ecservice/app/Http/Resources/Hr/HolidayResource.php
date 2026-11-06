<?php

namespace App\Http\Resources\Hr;

use App\Models\Hr\Holiday;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HolidayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->branch?->name,
            'date_from' => $this->date_from->toDateString(),
            'date_to' => $this->date_to->toDateString(),
            'days' => $this->daysCount(),
            'holiday_type' => $this->holiday_type,
            'holiday_type_label' => Holiday::typeLabels()[$this->holiday_type] ?? $this->holiday_type,
            'is_paid' => $this->is_paid,
            'notes' => $this->notes,
            'is_past' => $this->date_to->lt(today()),
            'created_by' => $this->creator?->name,
            'created_at' => $this->created_at,
        ];
    }
}
