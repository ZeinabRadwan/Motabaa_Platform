<?php

namespace App\Http\Resources\Hr;

use App\Models\Hr\AttendanceDay;
use App\Models\Hr\AttendanceDayCorrection;
use App\Support\Hr\HrAttendanceProcessor;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceDayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $statuses = AttendanceDay::statusLabels();
        $exceptions = AttendanceDay::exceptionLabels();
        $timezone = $this->branch?->timezone ?: HrAttendanceProcessor::timezone($this->center_id);
        $time = fn (?CarbonInterface $at) => $at?->copy()->setTimezone($timezone)->format('H:i');
        $correctable = in_array($this->computed_status, AttendanceDay::CORRECTABLE_STATUSES, true);

        return [
            'id' => $this->id,
            'attendance_date' => $this->attendance_date->toDateString(),
            'employee' => EmployeeReference::make($this->employee),
            'branch_id' => $this->branch_id,
            'branch_name' => $this->branch?->name,
            'day_type' => $this->day_type,
            'status' => $this->status,
            'status_label' => $statuses[$this->status] ?? $this->status,
            'computed_status' => $this->computed_status,
            'computed_status_label' => $statuses[$this->computed_status] ?? $this->computed_status,
            'status_reason' => $this->status_reason,
            'status_reason_label' => $this->status_reason ? __('tr.hr.attendance_day_reasons.'.$this->status_reason) : null,
            'shift' => $this->shift ? ['id' => $this->shift->id, 'code' => $this->shift->code, 'name' => $this->shift->name, 'color' => $this->shift->color] : null,
            'scheduled_start' => $time($this->scheduled_start_at),
            'scheduled_end' => $time($this->scheduled_end_at),
            'expected_minutes' => $this->expected_minutes,
            'late_grace_minutes' => $this->late_grace_minutes,
            'attendance_record_id' => $this->attendance_record_id,
            'attendance_method' => $this->attendance_method,
            'check_in' => $time($this->check_in_at),
            'check_out' => $time($this->check_out_at),
            'check_in_at' => $this->check_in_at?->toIso8601String(),
            'check_out_at' => $this->check_out_at?->toIso8601String(),
            'worked_minutes' => $this->worked_minutes,
            'late_minutes' => $this->late_minutes,
            'early_departure_minutes' => $this->early_departure_minutes,
            'computed_late_minutes' => $this->computed_late_minutes,
            'computed_early_departure_minutes' => $this->computed_early_departure_minutes,
            'absence_days' => $this->absence_days,
            'leave' => $this->leave_type_id ? [
                'leave_request_id' => $this->leave_request_id,
                'leave_type' => $this->leaveType?->name,
                'color' => $this->leaveType?->color,
                'is_paid' => $this->leave_is_paid,
            ] : null,
            'holiday' => $this->holiday_id ? ['id' => $this->holiday_id, 'name' => $this->holiday?->name] : null,
            'exception_reasons' => collect($this->exception_reasons ?? [])->map(fn ($reason) => [
                'key' => $reason,
                'label' => $exceptions[$reason] ?? $reason,
            ])->values(),
            'review_flags' => collect($this->review_flags ?? [])->map(fn ($flag) => [
                'key' => $flag,
                'label' => trans()->has('tr.hr.attendance_day_flags.'.$flag) ? __('tr.hr.attendance_day_flags.'.$flag) : $flag,
            ])->values(),
            'review_status' => $this->review_status,
            'override' => $this->overridden_at ? [
                'status' => $this->override_status,
                'late_minutes' => $this->override_late_minutes,
                'early_departure_minutes' => $this->override_early_departure_minutes,
                'reason' => $this->override_reason,
                'by' => minimalUserReference($this->overrider),
                'at' => $this->overridden_at,
            ] : null,
            'can_correct_values' => $correctable,
            'is_locked' => $this->locked_at !== null,
            'attendance_period_id' => $this->attendance_period_id,
            'processed_at' => $this->processed_at,
            'corrections' => $this->whenLoaded('corrections', fn () => $this->corrections->map(fn (AttendanceDayCorrection $correction) => [
                'id' => $correction->id,
                'action' => $correction->action,
                'old_values' => $correction->old_values,
                'new_values' => $correction->new_values,
                'reason' => $correction->reason,
                'by' => minimalUserReference($correction->corrector),
                'via_impersonation' => $correction->via_impersonation,
                'at' => $correction->created_at,
            ])->values()),
        ];
    }
}
