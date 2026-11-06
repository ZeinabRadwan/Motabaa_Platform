<?php

namespace App\Http\Requests\Hr;

use App\Models\Hr\Employee;
use Illuminate\Validation\Rule;

/**
 * PATCH /employees/{id}/deactivate and /activate.
 */
class EmployeeLifecycleRequest extends HrFormRequest
{
    public function rules(): array
    {
        if ($this->routeIs('hr.employees.deactivate')) {
            return [
                'end_date' => ['required', 'date'],
                'termination_reason' => ['required', Rule::in(Employee::TERMINATION_REASONS)],
                'notes' => ['required', 'string', 'max:500'],
            ];
        }

        return [
            'start_date' => ['required', 'date'],
            'employment_status' => ['nullable', Rule::in([Employee::STATUS_ACTIVE, Employee::STATUS_PROBATION])],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
