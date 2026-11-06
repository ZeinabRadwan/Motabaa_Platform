<?php

namespace App\Http\Requests\Hr;

/** Actions that only need a reason (cancel leave, reset a correction, reopen a period). */
class HrReasonRequest extends HrFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
