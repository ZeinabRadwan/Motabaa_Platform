<?php

namespace App\Http\Requests\Hr;

use App\Models\Hr\Shift;
use Illuminate\Validation\Rule;

/**
 * Shift definition. Hours and days are required for fixed shifts, the cycle for rotating ones; business
 * rules (break inside the shift, weekly rest day, cycle contents) are checked by HrShiftService.
 * effective_from / reason only apply when the timing of a shift in use changes.
 */
class ShiftRequest extends HrFormRequest
{
    public function rules(): array
    {
        $creating = $this->isCreate('shift');
        $required = $this->requiredOnCreate('shift');
        $fixedOnCreate = $creating ? 'required_unless:shift_type,'.Shift::TYPE_ROTATING : 'sometimes';

        return [
            'code' => [
                $required, 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique((new Shift())->getTable(), 'code')
                    ->where('center_id', $this->centerId())
                    ->ignore($this->route('shift')),
            ],
            'shift_type' => ['sometimes', 'string', Rule::in(Shift::TYPES)],
            'name_ar' => [$required, 'string', 'max:150'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'branch_id' => ['nullable', 'integer'],
            'start_time' => [$fixedOnCreate, 'nullable', 'date_format:H:i'],
            'end_time' => [$fixedOnCreate, 'nullable', 'date_format:H:i', 'different:start_time'],
            'break_start_time' => ['nullable', 'date_format:H:i'],
            'break_minutes' => ['nullable', 'integer', 'between:0,480'],
            'expected_minutes' => ['nullable', 'integer', 'between:1,1440'],
            'grace_minutes' => ['nullable', 'integer', 'between:0,240'],
            'early_leave_grace_minutes' => ['nullable', 'integer', 'between:0,240'],
            'working_days' => [$fixedOnCreate, 'nullable', 'array', 'max:7'],
            'working_days.*' => ['integer', 'between:0,6', 'distinct'],
            'rest_days' => ['sometimes', 'nullable', 'array', 'max:6'],
            'rest_days.*' => ['integer', 'between:0,6', 'distinct'],
            'rotation_pattern' => [
                $creating ? 'required_if:shift_type,'.Shift::TYPE_ROTATING : 'sometimes',
                'nullable', 'array', 'max:'.Shift::MAX_CYCLE_DAYS,
            ],
            'rotation_pattern.*' => ['nullable', 'integer'],
            'effective_from' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
            // Status changes after creation go through PATCH activate / deactivate (with their guards).
            'is_active' => $creating ? ['sometimes', 'boolean'] : ['exclude'],
        ];
    }
}
