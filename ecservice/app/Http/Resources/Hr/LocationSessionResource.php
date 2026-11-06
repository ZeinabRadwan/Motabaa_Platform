<?php

namespace App\Http\Resources\Hr;

use App\Models\Hr\AttendanceRecord;
use App\Support\Hr\HrElectronicAttendance;
use App\Support\Hr\HrLocationSchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An attendance session on the location monitoring dashboard: who, where they should be, the latest location and the
 * current verification status (`pending` while a due check has no location; `not_monitored` when checks do not apply).
 */
class LocationSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var AttendanceRecord $record */
        $record = $this->resource;
        $employee = $record->employee;
        $timezone = HrElectronicAttendance::timezoneFor($employee);
        $local = fn (?CarbonInterface $at) => $at?->copy()->setTimezone($timezone);
        $location = $record->workLocation;
        $last = $record->lastLocationCheck;
        $next = $record->next_location_check_at;

        return [
            'id' => $record->id,
            'employee' => [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'name' => $employee->user?->name,
                'branch' => $employee->branch ? ['id' => $employee->branch->id, 'name' => $employee->branch->name] : null,
            ],
            'attendance_date' => $record->attendance_date->toDateString(),
            'status' => $record->status,
            'check_in_time' => $local($record->check_in_at)->format('H:i'),
            'check_out_time' => $local($record->check_out_at)?->format('H:i'),
            'scheduled_end_time' => $local($record->scheduled_end_at)?->format('H:i'),
            'monitored' => $record->isMonitored(),
            'interval_minutes' => $record->location_check_interval_minutes,
            'location_status' => $record->isMonitored() ? $record->location_status : 'not_monitored',
            'next_check_time' => $record->isOpen() ? $local($next)?->format('H:i') : null,
            'overdue' => $record->isOpen() && $record->location_status === 'pending' && $record->latest_pending_due_at !== null
                && HrLocationSchedule::isOverdue(CarbonImmutable::parse((string) $record->latest_pending_due_at, config('app.timezone')), CarbonImmutable::now()),
            'work_location' => $location ? [
                'id' => $location->id,
                'name' => $location->name,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'radius_meters' => $record->location_radius_meters ?? $location->radius_meters,
            ] : null,
            'last_check' => $last ? [
                'id' => $last->id,
                'status' => $last->status,
                'source' => $last->source,
                'time' => $local($last->checked_at)?->format('H:i'),
                'at' => $local($last->checked_at)?->toIso8601String(),
                'latitude' => $last->latitude,
                'longitude' => $last->longitude,
                'accuracy_meters' => $last->accuracy_meters,
                'distance_meters' => $last->distance_meters,
            ] : null,
            'checks_count' => (int) ($record->location_checks_count ?? 0),
            'missed_count' => (int) ($record->missed_checks_count ?? 0),
            'open_exceptions_count' => (int) ($record->open_exceptions_count ?? 0),
        ];
    }
}
