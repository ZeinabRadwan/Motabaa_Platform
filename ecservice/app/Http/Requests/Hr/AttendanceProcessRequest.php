<?php

namespace App\Http\Requests\Hr;

class AttendanceProcessRequest extends HrFormRequest
{
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'employee_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
        ];
    }
}
