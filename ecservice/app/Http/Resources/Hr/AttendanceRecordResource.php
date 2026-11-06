<?php

namespace App\Http\Resources\Hr;

use App\Models\Hr\AttendanceRecord;
use App\Support\Hr\HrElectronicAttendance;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An attendance record, electronic or manual. Times are ISO 8601 with offset, plus HH:MM in the employee's branch
 * timezone. `duration_minutes` is the time between check-in and check-out (until now while checked in);
 * `worked_minutes` is the part inside the shift minus breaks. A side not recorded yet is null.
 */
class AttendanceRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var AttendanceRecord $record */
        $record = $this->resource;
        $timezone = HrElectronicAttendance::timezoneFor($record->employee);
        $time = fn (?CarbonInterface $at) => $at?->copy()->setTimezone($timezone);
        $end = $record->check_out_at ?? ($record->isOpen() ? now() : null);
        $location = $record->workLocation;
        $shift = $record->shift;
        $side = fn (string $prefix) => $record->{"{$prefix}_at"} === null ? null : [
            'at' => $time($record->{"{$prefix}_at"})->toIso8601String(),
            'time' => $time($record->{"{$prefix}_at"})->format('H:i'),
            'date' => $time($record->{"{$prefix}_at"})->toDateString(),
            'latitude' => $record->{"{$prefix}_latitude"},
            'longitude' => $record->{"{$prefix}_longitude"},
            'accuracy_meters' => $record->{"{$prefix}_accuracy_meters"},
            'distance_meters' => $record->{"{$prefix}_distance_meters"},
            'verification' => $record->{"{$prefix}_verification"},
            'ip' => $record->{"{$prefix}_ip"},
        ];

        return [
            'id' => $record->id,
            'employee_id' => $record->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $record->employee->id,
                'employee_number' => $record->employee->employee_number,
                'name' => $record->employee->relationLoaded('user') ? $record->employee->user?->name : null,
                'branch' => $record->employee->branch ? ['id' => $record->employee->branch->id, 'name' => $record->employee->branch->name] : null,
            ]),
            'attendance_date' => $record->attendance_date->toDateString(),
            'attendance_method' => $record->attendance_method,
            'status' => $record->status,
            'attendance_status' => $record->attendance_status ?? AttendanceRecord::attendanceStatusFor($record),
            'shift' => $shift ? ['id' => $shift->id, 'code' => $shift->code, 'name' => $shift->name, 'color' => $shift->color] : null,
            'shift_overridden' => (bool) $record->shift_overridden,
            'scheduled_start_at' => $time($record->scheduled_start_at)?->toIso8601String(),
            'scheduled_end_at' => $time($record->scheduled_end_at)?->toIso8601String(),
            'scheduled_start_time' => $time($record->scheduled_start_at)?->format('H:i'),
            'scheduled_end_time' => $time($record->scheduled_end_at)?->format('H:i'),
            'gps_requirement' => $record->gps_requirement,
            'attendance_policy_id' => $record->attendance_policy_id,
            'attendance_policy_version_id' => $record->attendance_policy_version_id,
            'work_location' => $location ? ['id' => $location->id, 'name' => $location->name] : null,
            'location_radius_meters' => $record->location_radius_meters,
            'check_in' => $side('check_in'),
            'check_out' => $side('check_out'),
            'check_out_deadline_at' => $time($record->check_out_deadline_at)?->toIso8601String(),
            'duration_minutes' => $end !== null && $record->check_in_at !== null ? max(0, intdiv($end->getTimestamp() - $record->check_in_at->getTimestamp(), 60)) : null,
            'worked_minutes' => $record->worked_minutes,
            'late_minutes' => $record->late_minutes,
            'early_departure_minutes' => $record->early_departure_minutes,
            'needs_review' => $record->needs_review,
            'review_reasons' => $record->review_reasons ?? [],
            'entry_source' => $record->entry_source,
            'entry_reason' => $record->entry_reason,
            'entry_reason_notes' => $record->entry_reason_notes,
            'notes' => $record->notes,
            'is_corrected' => $record->modified_at !== null,
            'created_by' => $this->whenLoaded('creator', fn () => minimalUserReference($record->creator)),
            'updated_by' => $this->whenLoaded('updater', fn () => minimalUserReference($record->updater)),
            'created_at' => $record->created_at?->toIso8601String(),
            'modified_at' => $time($record->modified_at)?->toIso8601String(),
        ];
    }
}
