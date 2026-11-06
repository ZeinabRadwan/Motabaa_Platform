<?php

namespace App\Http\Resources\Hr;

use App\Models\Hr\LocationCheck;
use App\Support\Hr\HrElectronicAttendance;
use App\Support\Hr\HrLocationSchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A location check. `timestamp` is when the location was taken, or the due time of a pending check; `missed` marks a
 * pending check that never received a location (overdue, or its session has ended).
 */
class LocationCheckResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var LocationCheck $check */
        $check = $this->resource;
        $employee = $check->employee;
        $timezone = HrElectronicAttendance::timezoneFor($employee);
        $local = fn (?CarbonInterface $at) => $at?->copy()->setTimezone($timezone);
        $at = $check->checked_at ?? $check->due_at;
        $record = $check->relationLoaded('record') ? $check->record : null;
        $open = $record?->isOpen() ?? false;
        $device = $check->relationLoaded('device') ? $check->device : null;

        return [
            'id' => $check->id,
            'employee' => [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'name' => $employee->user?->name,
                'branch' => $employee->branch ? ['id' => $employee->branch->id, 'name' => $employee->branch->name] : null,
            ],
            'attendance_record_id' => $check->attendance_record_id,
            'attendance_date' => $record?->attendance_date?->toDateString(),
            'work_location' => $check->workLocation ? ['id' => $check->workLocation->id, 'name' => $check->workLocation->name] : null,
            'kind' => $check->kind,
            'source' => $check->source,
            'status' => $check->status,
            'reason' => $check->reason,
            'timestamp' => $local($at)?->toIso8601String(),
            'date' => $local($at)?->toDateString(),
            'time' => $local($at)?->format('H:i'),
            'due_at' => $local($check->due_at)?->toIso8601String(),
            'due_time' => $local($check->due_at)?->format('H:i'),
            'checked_at' => $local($check->checked_at)?->toIso8601String(),
            'received_at' => $local($check->received_at)?->toIso8601String(),
            'latitude' => $check->latitude,
            'longitude' => $check->longitude,
            'accuracy_meters' => $check->accuracy_meters,
            'distance_meters' => $check->distance_meters,
            'radius_meters' => $check->radius_meters,
            'is_mocked' => $check->is_mocked,
            'device' => $device ? ['id' => $device->id, 'platform' => $device->platform, 'name' => $device->device_name ?: $device->device_model] : null,
            'ip_address' => $check->ip_address,
            'missed' => $check->isPending() && $check->due_at !== null
                && (! $open || HrLocationSchedule::isOverdue(CarbonImmutable::instance($check->due_at), CarbonImmutable::now())),
        ];
    }
}
