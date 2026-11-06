<?php

namespace App\Http\Requests\Hr;

use Illuminate\Validation\Rule;

class DepartmentRequest extends HrFormRequest
{
    public function rules(): array
    {
        $required = $this->requiredOnCreate('department');

        return [
            'code' => [
                $required, 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('hr_departments', 'code')->where('center_id', $this->centerId())->ignore($this->route('department')),
            ],
            'name_ar' => [$required, 'string', 'max:150'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'parent_id' => ['nullable', 'integer'],
            'sort_order' => ['sometimes', 'integer', 'between:0,65535'],
            'is_active' => ['sometimes', 'boolean'],
            'manager_employee_id' => ['nullable', 'integer'],
        ];
    }
}
