<?php

namespace App\Http\Requests\Hr;

use App\Models\Hr\Holiday;
use Illuminate\Validation\Rule;

class HolidayRequest extends HrFormRequest
{
    public function rules(): array
    {
        $required = $this->requiredOnCreate('holiday');

        return [
            'name_ar' => [$required, 'string', 'max:150'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'branch_id' => ['nullable', 'integer'],
            'date_from' => [$required, 'date_format:Y-m-d'],
            'date_to' => [$required, 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'holiday_type' => [$required, Rule::in(Holiday::TYPES)],
            'is_paid' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
