<?php

namespace App\Http\Resources\Hr;

use App\Models\Hr\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Employee row on branch and work location pages (hr-organization access): who works there and since when,
 * without the personal or compensation data of the employee profile.
 */
class PlacedEmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->user;

        return [
            'id' => $this->id,
            'employee_number' => $this->employee_number,
            'name' => $user?->name,
            'photo_url' => EmployeeResource::photoUrl($user),
            'job_title' => $user?->job_title,
            'department_name' => $this->department?->name,
            'work_location_id' => $this->work_location_id,
            'work_location_name' => $this->workLocation?->name,
            'employment_status' => $this->employment_status,
            'employment_status_label' => Employee::statusLabels()[$this->employment_status] ?? $this->employment_status,
            'assigned_since' => $this->assigned_since ? substr((string) $this->assigned_since, 0, 10) : null,
        ];
    }
}
