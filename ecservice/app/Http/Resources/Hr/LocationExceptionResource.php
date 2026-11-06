<?php

namespace App\Http\Resources\Hr;

use App\Models\Hr\LocationException;
use App\Support\Hr\HrElectronicAttendance;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationExceptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var LocationException $exception */
        $exception = $this->resource;
        $employee = $exception->employee;
        $timezone = HrElectronicAttendance::timezoneFor($employee);
        $local = fn (?CarbonInterface $at) => $at?->copy()->setTimezone($timezone);
        $last = $exception->lastCheck;

        return [
            'id' => $exception->id,
            'employee' => [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'name' => $employee->user?->name,
                'branch' => $employee->branch ? ['id' => $employee->branch->id, 'name' => $employee->branch->name] : null,
            ],
            'attendance_record_id' => $exception->attendance_record_id,
            'work_location' => $exception->workLocation ? ['id' => $exception->workLocation->id, 'name' => $exception->workLocation->name] : null,
            'type' => $exception->type,
            'status' => $exception->status,
            'date' => $local($exception->started_at)->toDateString(),
            'started_at' => $local($exception->started_at)->toIso8601String(),
            'started_time' => $local($exception->started_at)->format('H:i'),
            'last_seen_time' => $local($exception->last_seen_at)->format('H:i'),
            'ended_time' => $local($exception->ended_at)?->format('H:i'),
            'checks_count' => $exception->checks_count,
            'max_distance_meters' => $exception->max_distance_meters,
            'last_location' => $last ? ['latitude' => $last->latitude, 'longitude' => $last->longitude, 'accuracy_meters' => $last->accuracy_meters] : null,
            'review_status' => $exception->review_status,
            'reviewed_by' => $exception->reviewer ? ['id' => $exception->reviewer->id, 'name' => $exception->reviewer->name] : null,
            'reviewed_at' => $local($exception->reviewed_at)?->toIso8601String(),
            'review_notes' => $exception->review_notes,
        ];
    }
}
