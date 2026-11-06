<?php

namespace App\Http\Resources\Hr;

use App\Models\Hr\AttendanceRecordChange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** One manual creation or change of an attendance record (values: local "Y-m-d H:i" times, status keys, minutes). */
class AttendanceRecordChangeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var AttendanceRecordChange $change */
        $change = $this->resource;

        return [
            'id' => $change->id,
            'action' => $change->action,
            'source' => $change->source,
            'entry_reason' => $change->entry_reason,
            'reason' => $change->reason,
            'old_values' => $change->old_values,
            'new_values' => $change->new_values,
            'changed_by' => minimalUserReference($change->changedBy),
            'via_impersonation' => $change->via_impersonation,
            'ip_address' => $change->ip_address,
            'created_at' => $change->created_at?->toIso8601String(),
        ];
    }
}
