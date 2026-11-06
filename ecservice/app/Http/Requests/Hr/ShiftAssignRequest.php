<?php

namespace App\Http\Requests\Hr;

use App\Models\Hr\Shift;

/**
 * Bulk assignment of one shift: to the listed employees, or to every employed member of a branch.
 */
class ShiftAssignRequest extends HrFormRequest
{
    public function rules(): array
    {
        return [
            'employee_ids' => ['required_without:branch_id', 'nullable', 'array', 'max:500'],
            'employee_ids.*' => ['integer', 'distinct'],
            'branch_id' => ['required_without:employee_ids', 'nullable', 'integer'],
            'set_as_branch_default' => ['sometimes', 'boolean'],
            'effective_from' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
            'rotation_offset' => ['nullable', 'integer', 'between:0,'.(Shift::MAX_CYCLE_DAYS - 1)],
        ];
    }
}
