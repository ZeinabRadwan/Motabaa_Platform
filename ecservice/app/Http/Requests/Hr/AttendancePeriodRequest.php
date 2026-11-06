<?php

namespace App\Http\Requests\Hr;

class AttendancePeriodRequest extends HrFormRequest
{
    public function rules(): array
    {
        $required = $this->requiredOnCreate('period');

        return [
            'name' => [$required, 'string', 'max:100'],
            'branch_id' => ['nullable', 'integer'],
            'period_start' => [$required, 'date_format:Y-m-d'],
            'period_end' => [$required, 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
