<?php

namespace App\Http\Requests\Hr;

/** Submitting several draft additional working days for approval at once. */
class AdditionalWorkDaySubmitRequest extends HrFormRequest
{
    public const MAX_IDS = 100;

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_IDS],
            'ids.*' => ['integer', 'distinct'],
        ];
    }
}
