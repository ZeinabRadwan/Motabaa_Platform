<?php

namespace App\Http\Resources\Hr;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendancePolicyVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_merge([
            'id' => $this->id,
            'attendance_policy_id' => $this->attendance_policy_id,
        ], AttendancePolicyResource::rules($this->resource), [
            'effective_from' => $this->effective_from->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'reason' => $this->reason,
            'is_current' => $this->isEffectiveOn(today()),
            'is_scheduled' => $this->isScheduled(),
            'created_by' => $this->creator?->name,
            'created_at' => $this->created_at,
        ]);
    }
}
