<?php

namespace App\Http\Requests\Hr;

/**
 * Approve / reject / cancel. Who may act is decided per request by ApprovalService, not by a
 * permission, because approvers can be direct or department managers without any HR permission.
 */
class ApprovalActionRequest extends HrFormRequest
{
    public function rules(): array
    {
        return [
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
