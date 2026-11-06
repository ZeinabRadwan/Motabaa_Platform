<?php

namespace App\Http\Resources\Hr;

use App\Models\Hr\Employee;

/**
 * Compact embedded employee (managers, approval subjects): id, number and display name only.
 */
class EmployeeReference
{
    public static function make(?Employee $employee): ?array
    {
        if (! $employee) {
            return null;
        }

        return [
            'id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'user_id' => $employee->user_id,
            'name' => $employee->user?->name,
        ];
    }
}
