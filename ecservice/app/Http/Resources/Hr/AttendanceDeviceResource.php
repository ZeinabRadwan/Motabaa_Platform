<?php

namespace App\Http\Resources\Hr;

use App\Models\Hr\AttendanceDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A registered mobile device. The secret is never included. */
class AttendanceDeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var AttendanceDevice $device */
        $device = $this->resource;
        $employee = $device->relationLoaded('employee') ? $device->employee : null;

        return [
            'id' => $device->id,
            'employee' => $employee ? [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'name' => $employee->user?->name,
            ] : null,
            'device_uuid' => $device->device_uuid,
            'platform' => $device->platform,
            'device_name' => $device->device_name,
            'device_model' => $device->device_model,
            'os_version' => $device->os_version,
            'app_version' => $device->app_version,
            'status' => $device->status,
            'approved_at' => $device->approved_at?->toIso8601String(),
            'revoked_at' => $device->revoked_at?->toIso8601String(),
            'revoke_reason' => $device->revoke_reason,
            'registered_at' => $device->created_at?->toIso8601String(),
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'last_location_at' => $device->last_location_at?->toIso8601String(),
        ];
    }
}
