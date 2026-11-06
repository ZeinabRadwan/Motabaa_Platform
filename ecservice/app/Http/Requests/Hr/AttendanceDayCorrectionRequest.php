<?php

namespace App\Http\Requests\Hr;

use App\Models\Hr\AttendanceDay;
use Illuminate\Validation\Rule;

class AttendanceDayCorrectionRequest extends HrFormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(AttendanceDay::OVERRIDE_STATUSES)],
            'late_minutes' => ['nullable', 'integer', 'between:0,1440'],
            'early_departure_minutes' => ['nullable', 'integer', 'between:0,1440'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
