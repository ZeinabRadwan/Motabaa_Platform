<?php

namespace App\Http\Requests\Hr;

use App\Support\Hr\HrAdditionalWorkDays;

/**
 * Adding an additional working day (for one or more employees at once) or editing a draft / pending one.
 * Days are whole or half days only: payroll never pays overtime hours.
 */
class AdditionalWorkDayRequest extends HrFormRequest
{
    public function rules(): array
    {
        $create = $this->isCreate('record');
        $required = $this->requiredOnCreate('record');

        return array_merge($create ? [
            'employee_ids' => ['required', 'array', 'min:1', 'max:'.HrAdditionalWorkDays::MAX_EMPLOYEES_PER_REQUEST],
            'employee_ids.*' => ['integer', 'distinct'],
            'submit' => ['sometimes', 'boolean'],
        ] : [], [
            'work_date' => [$required, 'date_format:Y-m-d'],
            'days' => [$required, 'numeric', 'in:0.5,1'],
            'reason' => [$required, 'string', 'min:3', 'max:1000'],
        ]);
    }

    public function messages(): array
    {
        return [
            'days.in' => __('validation.hr.additional_day_invalid_days'),
        ];
    }
}
