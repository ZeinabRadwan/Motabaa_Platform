<?php

namespace App\Http\Resources\Hr;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'parent_id' => $this->parent_id,
            'parent_name' => $this->whenLoaded('parent', fn () => $this->parent?->name),
            'sort_order' => $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'manager_employee_id' => $this->manager_employee_id,
            'manager' => $this->whenLoaded('manager', fn () => EmployeeReference::make($this->manager)),
            'employees_count' => $this->whenCounted('employees'),
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
