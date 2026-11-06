<?php

namespace App\Http\Resources\Hr;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'city' => $this->city,
            'country' => $this->country,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'cr_number' => $this->cr_number,
            'mol_establishment_number' => $this->mol_establishment_number,
            'gosi_registration_number' => $this->gosi_registration_number,
            'timezone' => $this->timezone,
            'is_headquarters' => (bool) $this->is_headquarters,
            'is_active' => (bool) $this->is_active,
            'manager_employee_id' => $this->manager_employee_id,
            'manager' => $this->whenLoaded('manager', fn () => EmployeeReference::make($this->manager)),
            'default_shift_id' => $this->default_shift_id,
            'default_shift' => $this->whenLoaded('defaultShift', fn () => $this->defaultShift ? [
                'id' => $this->defaultShift->id,
                'code' => $this->defaultShift->code,
                'name' => $this->defaultShift->name,
                'time_range' => $this->defaultShift->timeRange(),
                'color' => $this->defaultShift->color,
            ] : null),
            'work_locations_count' => $this->whenCounted('workLocations'),
            'employees_count' => $this->whenCounted('employees'),
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
