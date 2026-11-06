<?php

namespace App\Http\Resources\Hr;

use App\Models\Hr\Employee;
use App\Support\Hr\SaudiIdentity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Employee row for lists and autocompletes: identity, organization, dates and statuses. The full profile
 * (personal details, attendance configuration, financial summary) is EmployeeProfileResource.
 */
class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->user;
        $idNumber = $user?->id_or_residence_number;

        return [
            'id' => $this->id,
            'employee_number' => $this->employee_number,
            'user_id' => $this->user_id,
            'name' => $user?->name,
            'photo_url' => self::photoUrl($user),
            'email' => $user?->email,
            'phone' => $user?->phone,
            'nationality' => $user?->nationality,
            'is_saudi' => $user?->nationality === SaudiIdentity::SAUDI_NATIONALITY,
            'id_or_residence_number' => $idNumber,
            'id_type' => $idNumber ? SaudiIdentity::type((string) $idNumber) : null,
            'job_title' => $user?->job_title,
            'contract_type' => $user?->contract_type,
            'hire_date' => $user?->hire_date?->toDateString(),
            'employment_start_date' => $this->employment_start_date?->toDateString(),
            'contract_start_date' => $this->contract_start_date?->toDateString(),
            'contract_end_date' => $user?->contract_end_date?->toDateString(),
            'id_expiry_date' => $user?->id_expiry_date?->toDateString(),
            'branch_id' => $this->branch_id,
            'branch_name' => $this->branch?->name,
            'department_id' => $this->department_id,
            'department_name' => $this->department?->name,
            'work_location_id' => $this->work_location_id,
            'work_location_name' => $this->workLocation?->name,
            'manager_id' => $this->manager_id,
            'manager' => $this->whenLoaded('manager', fn () => EmployeeReference::make($this->manager)),
            'employment_status' => $this->employment_status,
            'employment_status_label' => Employee::statusLabels()[$this->employment_status] ?? $this->employment_status,
            'is_employed' => $this->isEmployed(),
            'attendance_mode' => $this->attendance_mode,
            'attendance_mode_label' => Employee::attendanceModeLabels()[$this->attendance_mode] ?? $this->attendance_mode,
            'terminated_at' => $this->terminated_at?->toDateString(),
            'termination_reason' => $this->termination_reason,
            'termination_reason_label' => $this->termination_reason
                ? (Employee::terminationReasonLabels()[$this->termination_reason] ?? $this->termination_reason)
                : null,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /** Only resolves storage when a photo was uploaded (resolving an empty path lists the folder). */
    public static function photoUrl($user): ?string
    {
        if (! $user || ! filled($user->image_path)) {
            return null;
        }

        return $user->urlImage()['file_url'] ?? null;
    }
}
