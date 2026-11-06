<?php

namespace App\Http\Requests\Hr;

use App\Models\Hr\Employee;
use Illuminate\Validation\Rule;

/**
 * Employee attendance configuration change. effective_from / reason replace the older effective_date /
 * change_reason keys, which are still accepted. Past dates are limited by the payroll lock (service).
 */
class EmployeeAttendanceRequest extends HrFormRequest
{
    public function rules(): array
    {
        return [
            'attendance_mode' => ['required', Rule::in(Employee::ATTENDANCE_MODES)],
            'gps_policy' => ['nullable', Rule::in(Employee::GPS_POLICIES)],
            'grace_minutes' => ['nullable', 'integer', 'between:0,240'],
            'attendance_policy_id' => ['nullable', 'integer'],
            'shift_id' => ['nullable', 'integer'],
            'work_location_id' => ['sometimes', 'nullable', 'integer'],
            'effective_from' => ['nullable', 'date_format:Y-m-d'],
            'effective_date' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
            'change_reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'effective_from' => __('tr.hr.fields.effective_from'),
            'attendance_mode' => __('tr.hr.fields.attendance_mode'),
        ];
    }

    /** Validated data in the shape HrEmployeeAttendance::configure() expects. */
    public function configuration(): array
    {
        $data = $this->validated();
        $data['effective_from'] = $data['effective_from'] ?? (isset($data['effective_date'])
            ? substr((string) $data['effective_date'], 0, 10)
            : today()->toDateString());
        $data['reason'] = $data['reason'] ?? $data['change_reason'] ?? null;
        unset($data['effective_date'], $data['change_reason']);

        return $data;
    }
}
