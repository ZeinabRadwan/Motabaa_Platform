<?php

namespace App\Http\Requests\Hr;

use App\Models\Hr\Shift;

class EmployeeShiftRequest extends HrFormRequest
{
    public function rules(): array
    {
        return [
            'shift_id' => ['required', 'integer'],
            'effective_from' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
            // Employee-specific schedule: cycle start day for rotating shifts, own days for fixed ones.
            'rotation_offset' => ['nullable', 'integer', 'between:0,'.(Shift::MAX_CYCLE_DAYS - 1)],
            'working_days' => ['nullable', 'array', 'min:1', 'max:7'],
            'working_days.*' => ['integer', 'between:0,6', 'distinct'],
            'rest_days' => ['nullable', 'array', 'max:6'],
            'rest_days.*' => ['integer', 'between:0,6', 'distinct'],
        ];
    }
}
