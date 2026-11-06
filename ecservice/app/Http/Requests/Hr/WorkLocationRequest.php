<?php

namespace App\Http\Requests\Hr;

class WorkLocationRequest extends HrFormRequest
{
    public function rules(): array
    {
        $creating = $this->isCreate('workLocation');
        $required = $this->requiredOnCreate('workLocation');

        return [
            'branch_id' => [$required, 'integer'],
            'name_ar' => [$required, 'string', 'max:150'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'radius_meters' => ['sometimes', 'integer', 'between:10,5000'],
            // Status changes after creation go through PATCH activate / deactivate (with their guards).
            'is_active' => $creating ? ['sometimes', 'boolean'] : ['exclude'],
        ];
    }
}
