<?php

namespace App\Http\Requests\Hr;

use App\Models\Hr\ShiftException;
use Illuminate\Validation\Rule;

class ShiftExceptionRequest extends HrFormRequest
{
    public function rules(): array
    {
        $custom = 'required_if:exception_type,'.ShiftException::TYPE_CUSTOM_HOURS;

        return [
            'scope' => ['required', Rule::in([ShiftException::SCOPE_EMPLOYEE, ShiftException::SCOPE_BRANCH, ShiftException::SCOPE_CENTER])],
            'employee_id' => ['required_if:scope,'.ShiftException::SCOPE_EMPLOYEE, 'nullable', 'integer'],
            'branch_id' => ['required_if:scope,'.ShiftException::SCOPE_BRANCH, 'nullable', 'integer'],
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'exception_type' => ['required', Rule::in(ShiftException::TYPES)],
            'shift_id' => ['required_if:exception_type,'.ShiftException::TYPE_SHIFT, 'nullable', 'integer'],
            'start_time' => [$custom, 'nullable', 'date_format:H:i'],
            'end_time' => [$custom, 'nullable', 'date_format:H:i', 'different:start_time'],
            'break_minutes' => ['nullable', 'integer', 'between:0,480'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
