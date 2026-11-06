<?php

namespace App\Http\Requests\Hr;

use App\Models\Hr\LeaveType;
use Illuminate\Validation\Rule;

class LeaveTypeRequest extends HrFormRequest
{
    public function rules(): array
    {
        $required = $this->requiredOnCreate('leaveType');

        return [
            'code' => [$required, 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/'],
            'name_ar' => [$required, 'string', 'max:150'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'category' => ['sometimes', Rule::in(LeaveType::CATEGORIES)],
            'is_paid' => ['sometimes', 'boolean'],
            'balance_mode' => [$required, Rule::in(LeaveType::BALANCE_MODES)],
            'days_per_year' => ['required_if:balance_mode,'.LeaveType::BALANCE_FIXED, 'nullable', 'numeric', 'between:0,365'],
            'carry_over_max_days' => ['sometimes', 'numeric', 'between:0,365'],
            'max_days_per_request' => ['nullable', 'integer', 'between:1,365'],
            'day_counting' => [$required, Rule::in(LeaveType::DAY_COUNTINGS)],
            'requires_attachment' => ['sometimes', 'boolean'],
            'requires_hr_approval' => ['sometimes', 'boolean'],
            'allow_self_request' => ['sometimes', 'boolean'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['sometimes', 'integer', 'between:0,1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
