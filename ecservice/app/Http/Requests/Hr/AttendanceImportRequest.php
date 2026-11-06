<?php

namespace App\Http\Requests\Hr;

use App\Models\Hr\AttendanceRecord;
use Illuminate\Validation\Rule;

/** Manual attendance import file (xlsx, xls or csv, 5 MB) with the reason shared by every row. */
class AttendanceImportRequest extends HrFormRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
            'dry_run' => ['sometimes', 'boolean'],
            'entry_reason' => ['required', Rule::in(AttendanceRecord::ENTRY_REASONS)],
            'entry_reason_notes' => ['nullable', 'string', 'max:500', 'required_if:entry_reason,other'],
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
