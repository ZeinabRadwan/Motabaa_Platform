<?php

namespace App\Http\Requests\Hr;

use App\Models\Hr\LocationException;

class LocationExceptionReviewRequest extends HrFormRequest
{
    public function rules(): array
    {
        return [
            'review_status' => ['required', 'string', 'in:'.LocationException::REVIEW_ACKNOWLEDGED.','.LocationException::REVIEW_DISMISSED],
            'notes' => ['nullable', 'string', 'max:1000', 'required_if:review_status,'.LocationException::REVIEW_DISMISSED],
        ];
    }
}
