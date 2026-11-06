<?php

namespace App\Http\Requests\Hr;

use App\Models\Hr\AttendanceRecord;
use Illuminate\Validation\Rule;

/**
 * Manual attendance form: create (POST /attendance-records), correct (PUT /attendance-records/{record}, `reason`
 * required) and preview (POST /attendance-records/preview, times optional, `record_id` for a correction).
 * Times are "H:i" in the branch timezone; check_in_date / check_out_date only when the automatic placement
 * (nearest the shift, check-out after check-in) must be overridden. Business rules are checked by HrManualAttendance.
 */
class ManualAttendanceRequest extends HrFormRequest
{
    public function rules(): array
    {
        $update = $this->route('record') !== null;
        $preview = str_ends_with($this->path(), 'attendance-records/preview');
        $correction = $update || ($preview && (int) $this->input('record_id') > 0);

        $rules = [
            'shift_id' => ['sometimes', 'nullable', 'integer'],
            'check_in' => ['sometimes', 'nullable', 'date_format:H:i'],
            'check_in_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'check_out' => ['sometimes', 'nullable', 'date_format:H:i'],
            'check_out_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'entry_reason' => [$correction || $preview ? 'sometimes' : 'required', 'nullable', Rule::in(AttendanceRecord::ENTRY_REASONS)],
            'entry_reason_notes' => ['nullable', 'string', 'max:500', 'required_if:entry_reason,other'],
        ];

        if (! $correction) {
            $rules['employee_id'] = ['required', 'integer'];
            $rules['attendance_date'] = ['required', 'date_format:Y-m-d'];
        }
        if (! $preview && ! $update) {
            $rules['check_in'] = ['nullable', 'date_format:H:i', 'required_without:check_out'];
        }
        if ($update) {
            $rules['reason'] = ['required', 'string', 'min:5', 'max:1000'];
        }
        if ($preview) {
            $rules['record_id'] = ['sometimes', 'nullable', 'integer'];
        }

        return $rules;
    }

    /** The validated values the service reads (keys that were not sent stay absent). */
    public function entry(): array
    {
        return collect($this->validated())->except('record_id')->all();
    }
}
