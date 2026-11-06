<?php

namespace App\Http\Requests\Hr;

use App\Models\Hr\AttendanceRecord;
use Illuminate\Validation\Rule;

/** Bulk manual entry: up to 200 records sharing the reason for manual entry. */
class BulkManualAttendanceRequest extends HrFormRequest
{
    public const MAX_ENTRIES = 200;

    public function rules(): array
    {
        return [
            'entry_reason' => ['required', Rule::in(AttendanceRecord::ENTRY_REASONS)],
            'entry_reason_notes' => ['nullable', 'string', 'max:500', 'required_if:entry_reason,other'],
            'entries' => ['required', 'array', 'min:1', 'max:'.self::MAX_ENTRIES],
            'entries.*.employee_id' => ['required', 'integer'],
            'entries.*.attendance_date' => ['required', 'date_format:Y-m-d'],
            'entries.*.shift_id' => ['nullable', 'integer'],
            'entries.*.check_in' => ['nullable', 'date_format:H:i', 'required_without:entries.*.check_out'],
            'entries.*.check_in_date' => ['nullable', 'date_format:Y-m-d'],
            'entries.*.check_out' => ['nullable', 'date_format:H:i'],
            'entries.*.check_out_date' => ['nullable', 'date_format:Y-m-d'],
            'entries.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function common(): array
    {
        return [
            'entry_reason' => $this->validated('entry_reason'),
            'entry_reason_notes' => $this->validated('entry_reason_notes'),
        ];
    }
}
