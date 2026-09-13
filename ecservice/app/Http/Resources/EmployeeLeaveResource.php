<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeLeaveResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $types = \App\Models\EmployeeLeave::types();
        $statuses = \App\Models\EmployeeLeave::statuses();

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->user?->name,
            'job_title' => $this->user?->job_title,
            'department' => $this->user?->department,
            'type' => $this->type,
            'type_label' => $types[$this->type] ?? $this->type,
            'date_from' => $this->date_from?->toDateString(),
            'date_to' => $this->date_to?->toDateString(),
            'days' => (int) $this->days,
            'status' => $this->status,
            'status_label' => $statuses[$this->status] ?? $this->status,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_by_name' => $this->createdBy?->name,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_by_name' => $this->reviewedBy?->name,
            'reviewed_at' => $this->reviewed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
