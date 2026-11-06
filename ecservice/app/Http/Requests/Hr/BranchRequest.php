<?php

namespace App\Http\Requests\Hr;

use Illuminate\Validation\Rule;

class BranchRequest extends HrFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('country')) {
            $this->merge(['country' => strtoupper((string) $this->input('country'))]);
        }
    }

    public function rules(): array
    {
        $creating = $this->isCreate('branch');
        $required = $this->requiredOnCreate('branch');

        return [
            'code' => [
                $required, 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('hr_branches', 'code')->where('center_id', $this->centerId())->ignore($this->route('branch')),
            ],
            'name_ar' => [$required, 'string', 'max:150'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['sometimes', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s-]{5,28}$/'],
            'email' => ['nullable', 'string', 'email', 'max:191'],
            'cr_number' => ['nullable', 'digits:10'],
            'mol_establishment_number' => ['nullable', 'string', 'max:30'],
            'gosi_registration_number' => ['nullable', 'string', 'max:30'],
            'timezone' => ['sometimes', 'timezone'],
            'is_headquarters' => ['sometimes', 'boolean'],
            // Status changes after creation go through PATCH activate / deactivate (with their guards).
            'is_active' => $creating ? ['sometimes', 'boolean'] : ['exclude'],
            'manager_employee_id' => ['nullable', 'integer'],
            'default_shift_id' => ['nullable', 'integer'],
        ];
    }
}
