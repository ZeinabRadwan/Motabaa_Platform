<?php

namespace App\Http\Requests\Hr;

use App\Models\Hr\EmployeeInsurance;
use Illuminate\Validation\Rule;

class EmployeeInsuranceRequest extends HrFormRequest
{
    public function rules(): array
    {
        $rate = ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'];

        return [
            'effective_from' => ['required', 'date'],
            'category' => ['required', Rule::in(EmployeeInsurance::CATEGORIES)],
            'employee_rate' => $rate,
            'employer_rate' => $rate,
            'contribution_start_date' => ['nullable', 'date', 'required_unless:category,'.EmployeeInsurance::CATEGORY_EXEMPT],
            'subscription_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9A-Za-z-]+$/'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
