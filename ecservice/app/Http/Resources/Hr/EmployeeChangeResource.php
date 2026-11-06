<?php

namespace App\Http\Resources\Hr;

use App\Models\Hr\EmployeeChange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeChangeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'change_type' => $this->change_type,
            'change_type_label' => EmployeeChange::typeLabels()[$this->change_type] ?? $this->change_type,
            'action' => $this->action,
            'old_value' => $this->old_value,
            'new_value' => $this->new_value,
            'old_display' => $this->display('old'),
            'new_display' => $this->display('new'),
            'effective_date' => $this->effective_date->toDateString(),
            'reason' => $this->reason,
            'changed_by' => $this->changer?->name,
            'created_at' => $this->created_at,
        ];
    }
}
