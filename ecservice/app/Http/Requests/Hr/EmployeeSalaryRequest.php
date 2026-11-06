<?php

namespace App\Http\Requests\Hr;

class EmployeeSalaryRequest extends HrFormRequest
{
    public const MAX_AMOUNT = 9999999999.99;

    public function rules(): array
    {
        $amount = ['numeric', 'min:0', 'max:'.self::MAX_AMOUNT, 'decimal:0,2'];

        return [
            'effective_from' => ['required', 'date'],
            'basic_salary' => array_merge(['required', 'gt:0'], $amount),
            'housing_allowance' => array_merge(['nullable'], $amount),
            'transportation_allowance' => array_merge(['nullable'], $amount),
            'other_allowances' => array_merge(['nullable'], $amount),
            'other_allowances_note' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
