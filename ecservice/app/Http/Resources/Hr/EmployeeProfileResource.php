<?php

namespace App\Http\Resources\Hr;

use App\Models\Hr\AttendancePolicy;
use App\Models\Hr\Employee;
use App\Support\Hr\HrAttendanceRules;
use App\Support\Hr\HrContext;
use App\Support\Hr\HrEffectiveDating;
use App\Support\Hr\HrEmployeeAttendance;
use App\Support\Hr\HrEmployeeService;
use App\Support\Hr\HrShiftSchedule;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;

/**
 * Full employee profile: the list row plus personal details, attendance configuration, current shift,
 * employment periods and (for hr-compensation holders only) the salary / insurance in force today.
 */
class EmployeeProfileResource extends EmployeeResource
{
    public function toArray(Request $request): array
    {
        /** @var Employee $employee */
        $employee = $this->resource;
        $user = $employee->user;
        $today = today();
        $canViewCompensation = HrContext::canViewCompensation($request->user());

        $currentShift = $employee->shiftAssignmentOn($today);
        $afterToday = $today->toDateString().' 23:59:59';
        $nextShift = $employee->shiftAssignments()->where('effective_from', '>', $afterToday)
            ->orderBy('effective_from')->with('shift')->first();
        // GPS attendance uses the work location of the assignment in force today, not the cached column.
        $location = $employee->effectiveWorkLocation($today);
        $currentAssignment = $employee->workAssignmentOn($today);
        $nextAssignment = $employee->workAssignments()->where('effective_from', '>', $afterToday)
            ->orderBy('effective_from')->with(['branch', 'workLocation'])->first();
        $rules = HrAttendanceRules::on($employee, $today);
        $currentConfig = $employee->attendanceConfigs()->effectiveOn($today)->with(['attendancePolicy', 'creator:id,name'])->first();
        $nextConfig = $employee->attendanceConfigs()->where('effective_from', '>', $afterToday)
            ->orderBy('effective_from')->with(['attendancePolicy', 'creator:id,name'])->first();
        $mode = $currentConfig?->attendance_mode ?? $employee->attendance_mode;
        $gpsPolicy = $currentConfig?->gps_policy ?? $employee->gps_policy;
        $graceMinutes = $currentConfig ? $currentConfig->grace_minutes : $employee->grace_minutes;
        $policy = $currentConfig ? $currentConfig->attendancePolicy : $employee->attendancePolicy;

        return array_merge(parent::toArray($request), [
            'gender' => $user?->gender !== null ? (int) $user->gender : null,
            'birthdate' => $user?->birthdate ? substr((string) $user->birthdate, 0, 10) : null,
            'address' => [
                'city' => $user?->address_city,
                'area' => $user?->address_area,
                'street' => $user?->address_street,
                'building' => $user?->address_building,
                'number' => $user?->address_number,
                'unit' => $user?->address_unit,
                'zipcode' => $user?->address_zipcode,
            ],
            'attendance' => [
                'mode' => $mode,
                'mode_label' => Employee::attendanceModeLabels()[$mode] ?? $mode,
                'gps_policy' => $gpsPolicy,
                'gps_policy_label' => Employee::gpsPolicyLabels()[$gpsPolicy] ?? $gpsPolicy,
                'gps_requirement' => $rules->gpsRequirement,
                'gps_requirement_label' => AttendancePolicy::optionLabels()['gps_requirements'][$rules->gpsRequirement] ?? $rules->gpsRequirement,
                'grace_minutes' => $graceMinutes,
                'effective_grace_minutes' => HrEmployeeService::effectiveGraceMinutes($employee, $currentShift?->shift, $rules),
                'attendance_policy_id' => $policy?->id,
                'attendance_policy' => $policy ? new AttendancePolicyResource($policy) : null,
                'rules' => $rules->toArray(),
                'current_config' => $currentConfig ? new EmployeeAttendanceConfigResource($currentConfig) : null,
                'next_config' => $nextConfig ? new EmployeeAttendanceConfigResource($nextConfig) : null,
                'warnings' => collect(HrEmployeeAttendance::warnings($employee, $rules, $today))
                    ->map(fn (string $key) => ['key' => $key, 'message' => __("tr.hr.attendance_warnings.{$key}")])
                    ->values(),
                'can_configure' => can(PermissionCatalog::HR_ATTENDANCE_CONFIG_PERMISSION, $request->user()),
                'work_location' => $location ? [
                    'id' => $location->id,
                    'name' => $location->name,
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude,
                    'radius_meters' => $location->radius_meters,
                    'has_coordinates' => $location->hasCoordinates(),
                ] : null,
                'current_shift' => $currentShift ? new EmployeeShiftAssignmentResource($currentShift) : null,
                'next_shift' => $nextShift ? new EmployeeShiftAssignmentResource($nextShift) : null,
                'today' => HrShiftSchedule::dayFor($employee, $today)->toArray(),
            ],
            'work_assignment' => [
                'current' => $currentAssignment ? new EmployeeWorkAssignmentResource($currentAssignment) : null,
                'next' => $nextAssignment ? new EmployeeWorkAssignmentResource($nextAssignment) : null,
            ],
            'employment_periods' => $employee->employmentPeriods()->orderByDesc('start_date')->get()->map(fn ($period) => [
                'id' => $period->id,
                'start_date' => $period->start_date->toDateString(),
                'end_date' => $period->end_date?->toDateString(),
                'end_reason' => $period->end_reason,
                'end_reason_label' => $period->end_reason ? (Employee::terminationReasonLabels()[$period->end_reason] ?? $period->end_reason) : null,
            ])->values(),
            'financial' => $canViewCompensation ? [
                'current_salary' => ($salary = $employee->salaryOn($today)) ? new EmployeeSalaryResource($salary) : null,
                'current_insurance' => ($insurance = $employee->insuranceOn($today)) ? new EmployeeInsuranceResource($insurance) : null,
            ] : null,
            'can_view_compensation' => $canViewCompensation,
            'changes_locked_until' => HrEffectiveDating::lockedUntil($employee->center_id)?->toDateString(),
            'documents_count' => $employee->documents()->count(),
        ]);
    }
}
