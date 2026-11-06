<?php

namespace App\Http\Requests\Hr;

use App\Models\Hr\ApprovalRequest;
use App\Models\Hr\ApprovalRequestStep;
use Illuminate\Validation\Rule;

class ApprovalWorkflowRequest extends HrFormRequest
{
    public function authorize(): bool
    {
        return canAny(['edit_hr-approvals', 'admin_hr-approvals']);
    }

    public function rules(): array
    {
        return [
            'request_type' => ['required', Rule::in(ApprovalRequest::TYPES)],
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'is_active' => ['sometimes', 'boolean'],
            'steps' => ['required', 'array', 'min:1', 'max:10'],
            'steps.*.approver_type' => ['required', Rule::in(ApprovalRequestStep::APPROVER_TYPES)],
            'steps.*.approver_value' => [
                'nullable', 'string', 'max:100',
                'required_if:steps.*.approver_type,'.implode(',', ApprovalRequestStep::APPROVER_TYPES_WITH_VALUE),
            ],
        ];
    }
}
