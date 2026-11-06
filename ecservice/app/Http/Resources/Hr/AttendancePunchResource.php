<?php

namespace App\Http\Resources\Hr;

use App\Models\Hr\AttendancePunch;
use App\Support\Hr\HrElectronicAttendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendancePunchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var AttendancePunch $punch */
        $punch = $this->resource;
        $at = $punch->attempted_at->copy()->setTimezone(HrElectronicAttendance::timezoneFor($punch->employee));

        return [
            'id' => $punch->id,
            'attendance_record_id' => $punch->attendance_record_id,
            'type' => $punch->type,
            'result' => $punch->result,
            'reason' => $punch->reason,
            'attempted_at' => $at->toIso8601String(),
            'attempted_date' => $at->toDateString(),
            'attempted_time' => $at->format('H:i:s'),
            'attendance_date' => $punch->attendance_date?->toDateString(),
            'latitude' => $punch->latitude,
            'longitude' => $punch->longitude,
            'accuracy_meters' => $punch->accuracy_meters,
            'distance_meters' => $punch->distance_meters,
            'work_location' => $punch->workLocation ? ['id' => $punch->workLocation->id, 'name' => $punch->workLocation->name] : null,
            'verification' => $punch->verification,
            'location_error' => $punch->location_error,
            'ip_address' => $punch->ip_address,
            'user_agent' => $punch->user_agent,
        ];
    }
}
