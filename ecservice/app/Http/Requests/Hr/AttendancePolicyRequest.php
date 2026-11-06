<?php

namespace App\Http\Requests\Hr;

use App\Models\Hr\AttendancePolicy;
use Illuminate\Validation\Rule;

/**
 * Rules missing on create take the center defaults (HrAttendanceRules::centerDefaults). effective_from /
 * reason apply when the rules of a policy in use change (HrAttendancePolicyService).
 */
class AttendancePolicyRequest extends HrFormRequest
{
    public function rules(): array
    {
        $required = $this->requiredOnCreate('policy');

        return [
            'code' => [
                $required, 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique((new AttendancePolicy())->getTable(), 'code')
                    ->where('center_id', $this->centerId())
                    ->ignore($this->route('policy')),
            ],
            'name_ar' => [$required, 'string', 'max:150'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'check_in_before_minutes' => ['sometimes', 'integer', 'between:0,720'],
            'check_in_after_minutes' => ['sometimes', 'nullable', 'integer', 'between:0,720'],
            'check_out_before_minutes' => ['sometimes', 'nullable', 'integer', 'between:0,720'],
            'check_out_after_minutes' => ['sometimes', 'integer', 'between:0,720'],
            'late_grace_minutes' => ['sometimes', 'integer', 'between:0,240'],
            'early_leave_grace_minutes' => ['sometimes', 'integer', 'between:0,240'],
            'late_counting' => ['sometimes', Rule::in(AttendancePolicy::COUNTING_MODES)],
            'late_action' => ['sometimes', Rule::in(AttendancePolicy::DEVIATION_ACTIONS)],
            'absence_after_minutes' => ['sometimes', 'nullable', 'integer', 'between:1,1440'],
            'early_leave_counting' => ['sometimes', Rule::in(AttendancePolicy::COUNTING_MODES)],
            'early_leave_action' => ['sometimes', Rule::in(AttendancePolicy::DEVIATION_ACTIONS)],
            'missing_checkout_action' => ['sometimes', Rule::in(AttendancePolicy::MISSING_CHECKOUT_ACTIONS)],
            'duplicate_punch_action' => ['sometimes', Rule::in(AttendancePolicy::DUPLICATE_ACTIONS)],
            'duplicate_window_minutes' => ['sometimes', 'integer', 'between:1,120'],
            'gps_requirement' => ['sometimes', Rule::in(AttendancePolicy::GPS_REQUIREMENTS)],
            'allow_manual_attendance' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'effective_from' => ['nullable', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'effective_from' => __('tr.hr.fields.effective_from'),
        ];
    }
}
