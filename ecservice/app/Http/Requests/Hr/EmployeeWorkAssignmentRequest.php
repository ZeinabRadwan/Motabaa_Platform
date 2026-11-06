<?php

namespace App\Http\Requests\Hr;

class EmployeeWorkAssignmentRequest extends HrFormRequest
{
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer'],
            'work_location_id' => ['nullable', 'integer'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
