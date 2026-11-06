<?php

namespace App\Http\Requests\Hr;

use App\Support\Hr\HrLeaveRequests;

/**
 * Leave request (store) or its preview (`preview` route: no reason or attachments). employee_id is required on
 * the HR endpoints and ignored on the self-service ones.
 */
class LeaveRequestRequest extends HrFormRequest
{
    public function rules(): array
    {
        $preview = str_ends_with((string) $this->route()?->uri(), '/preview');
        $self = str_contains((string) $this->route()?->uri(), 'my-leave');

        return [
            'employee_id' => [$self ? 'prohibited' : 'required', 'integer'],
            'leave_type_id' => ['required', 'integer'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason' => [$preview ? 'nullable' : 'required', 'string', 'min:3', 'max:1000'],
            'record_as_approved' => [$self ? 'prohibited' : 'sometimes', 'boolean'],
            'attachments' => ['sometimes', 'array', 'max:'.HrLeaveRequests::MAX_ATTACHMENTS],
            'attachments.*' => [
                'file',
                'mimes:'.implode(',', HrLeaveRequests::ATTACHMENT_MIMES),
                'max:'.HrLeaveRequests::ATTACHMENT_MAX_KB,
            ],
        ];
    }
}
