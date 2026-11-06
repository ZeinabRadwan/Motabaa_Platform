<?php

namespace App\Http\Requests\Hr;

class LeaveBalanceAdjustRequest extends HrFormRequest
{
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer'],
            'leave_type_id' => ['required', 'integer'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'days' => ['required', 'numeric', 'between:-365,365', 'not_in:0'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
