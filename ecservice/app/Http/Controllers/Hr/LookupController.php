<?php

namespace App\Http\Controllers\Hr;

use App\Models\Hr\AdditionalWorkDay;
use App\Models\Hr\ApprovalRequest;
use App\Models\Hr\ApprovalRequestStep;
use App\Models\Hr\AttendanceDay;
use App\Models\Hr\AttendancePeriod;
use App\Models\Hr\AttendancePolicy;
use App\Models\Hr\AttendanceRecord;
use App\Models\Hr\Branch;
use App\Models\Hr\Department;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeChange;
use App\Models\Hr\EmployeeDocument;
use App\Models\Hr\EmployeeInsurance;
use App\Models\Hr\Holiday;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\LeaveType;
use App\Models\Hr\Shift;
use App\Models\Hr\ShiftException;
use App\Models\Hr\WorkLocation;
use App\Support\Hr\HrAttendanceRules;
use App\Support\Hr\HrBranchScope;
use App\Support\Hr\HrEmployeeService;
use App\Support\Hr\HrLeaveTypes;
use App\Support\Hr\HrMorphMap;
use App\Support\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

/**
 * One call with every option list HR forms need (web and future mobile clients).
 */
class LookupController extends HrController
{
    public function index(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $scope = $this->branchScope($request, $centerId);
            $named = fn ($rows) => $rows->map(fn ($row) => ['value' => $row->id, 'title' => $row->name] + (
                isset($row->branch_id) ? ['branch_id' => $row->branch_id] : []
            ))->values();
            $activeBranchIds = Branch::query()->forCenter($centerId)->where('is_active', true)->select('id');

            return success([
                'branch_scope' => ['restricted' => $scope !== null, 'branch_ids' => $scope ?? []],
                'branches' => $named(HrBranchScope::restrict(Branch::query()->forCenter($centerId)->where('is_active', true), $scope, 'id')
                    ->orderBy('name_ar')->get(['id', 'name_ar', 'name_en'])),
                'departments' => $named(Department::query()->forCenter($centerId)->where('is_active', true)->orderBy('sort_order')->get(['id', 'name_ar', 'name_en'])),
                'work_locations' => HrBranchScope::restrict(WorkLocation::query()->forCenter($centerId)->where('is_active', true)->whereIn('branch_id', $activeBranchIds), $scope, 'branch_id')
                    ->orderBy('name_ar')
                    ->get(['id', 'branch_id', 'name_ar', 'name_en', 'latitude', 'longitude'])
                    ->map(fn (WorkLocation $location) => [
                        'value' => $location->id, 'title' => $location->name, 'branch_id' => $location->branch_id, 'has_coordinates' => $location->hasCoordinates(),
                    ])->values(),
                'shifts' => HrBranchScope::restrict(Shift::query()->forCenter($centerId)->where('is_active', true), $scope, 'branch_id', true)
                    ->orderBy('name_ar')
                    ->get(['id', 'branch_id', 'code', 'shift_type', 'color', 'name_ar', 'name_en', 'start_time', 'end_time', 'working_days', 'rest_days', 'rotation_pattern'])
                    ->map(fn (Shift $shift) => [
                        'value' => $shift->id,
                        'title' => $shift->name.' ('.$shift->timeRange().')',
                        'code' => $shift->code,
                        'branch_id' => $shift->branch_id,
                        'shift_type' => $shift->shift_type,
                        'color' => $shift->color,
                        'is_rotating' => $shift->isRotating(),
                        'cycle_length' => $shift->cycleLength(),
                        'time_range' => $shift->timeRange(),
                        'working_days' => $shift->working_days_list,
                        'rest_days' => $shift->rest_days_list,
                    ])->values(),
                'shift_types' => self::options(Shift::typeLabels()),
                'shift_exception_types' => self::options(ShiftException::typeLabels()),
                'attendance_policies' => AttendancePolicy::query()->forCenter($centerId)->where('is_active', true)->orderBy('name_ar')
                    ->get(['id', 'code', 'name_ar', 'name_en', 'gps_requirement', 'allow_manual_attendance', 'late_grace_minutes'])
                    ->map(fn (AttendancePolicy $policy) => [
                        'value' => $policy->id,
                        'title' => $policy->name,
                        'code' => $policy->code,
                        'gps_requirement' => $policy->gps_requirement,
                        'allow_manual_attendance' => $policy->allow_manual_attendance,
                        'late_grace_minutes' => $policy->late_grace_minutes,
                    ])->values(),
                'attendance_rule_options' => array_map(fn (array $labels) => self::options($labels), AttendancePolicy::optionLabels()),
                'attendance_defaults' => HrAttendanceRules::centerDefaults($centerId),
                'job_titles' => EmployeeController::jobTitleOptions($centerId),
                'employment_statuses' => self::options(Employee::statusLabels()),
                'attendance_modes' => self::options(Employee::attendanceModeLabels()),
                'attendance_methods' => self::options(Employee::labels(AttendanceRecord::METHODS, 'tr.hr.attendance_methods')),
                'attendance_statuses' => self::options(Employee::labels(AttendanceRecord::ATTENDANCE_STATUSES, 'tr.hr.attendance_statuses')),
                'attendance_entry_reasons' => self::options(Employee::labels(AttendanceRecord::ENTRY_REASONS, 'tr.hr.attendance_entry_reasons')),
                'gps_policies' => self::options(Employee::gpsPolicyLabels()),
                'termination_reasons' => self::options(Employee::terminationReasonLabels()),
                'insurance_categories' => self::options(EmployeeInsurance::categoryLabels()),
                'document_categories' => self::options(Employee::labels(EmployeeDocument::categories(), 'tr.hr.document_categories')),
                'document_types' => self::documentTypes(),
                'change_types' => self::options(EmployeeChange::typeLabels()),
                'staff_roles' => self::staffRoles(),
                'request_types' => self::options(ApprovalRequest::typeLabels()),
                'approval_statuses' => self::options(ApprovalRequest::statusLabels()),
                'approver_types' => self::options(ApprovalRequestStep::approverTypeLabels()),
                'auditable_types' => self::options(array_combine(
                    array_keys(HrMorphMap::MAP),
                    array_map(fn ($type) => __("tr.hr.auditable_types.{$type}"), array_keys(HrMorphMap::MAP))
                )),
                'approver_roles' => self::staffRoles(),
                'approver_permissions' => self::hrPermissions(),
                'holiday_types' => self::options(Holiday::typeLabels()),
                'leave_types' => self::leaveTypes($centerId),
                'leave_statuses' => self::options(LeaveRequest::statusLabels()),
                'leave_type_options' => array_map(fn (array $labels) => self::options($labels), LeaveType::optionLabels()),
                'attendance_day_statuses' => self::options(AttendanceDay::statusLabels()),
                'attendance_exceptions' => self::options(AttendanceDay::exceptionLabels()),
                'attendance_override_statuses' => self::options(array_intersect_key(AttendanceDay::statusLabels(), array_flip(AttendanceDay::OVERRIDE_STATUSES))),
                'attendance_period_statuses' => self::options(AttendancePeriod::statusLabels()),
                'additional_day_statuses' => self::options(AdditionalWorkDay::statusLabels()),
                'additional_day_types' => self::options(AdditionalWorkDay::dayTypeLabels()),
            ]);
        });
    }

    /**
     * Roles usable as approval steps (stored as the role id string), excluding parent/admin.
     */
    private static function staffRoles(): array
    {
        return HrEmployeeService::staffRolesQuery()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Role $role) => ['value' => (string) $role->id, 'title' => (string) $role->name])
            ->values()
            ->all();
    }

    /** Document types with the categories they belong to (a type like "other" exists in several). */
    private static function documentTypes(): array
    {
        $options = [];
        foreach (EmployeeDocument::allTypes() as $type) {
            $categories = array_keys(array_filter(EmployeeDocument::TYPES_BY_CATEGORY, fn ($types) => in_array($type, $types, true)));
            $options[] = ['value' => $type, 'title' => __("tr.hr.document_types.{$type}"), 'categories' => $categories];
        }

        return $options;
    }

    private static function leaveTypes(int $centerId): array
    {
        HrLeaveTypes::ensureDefaults($centerId);

        return LeaveType::query()->forCenter($centerId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (LeaveType $type) => [
                'value' => $type->id,
                'title' => $type->name,
                'code' => $type->code,
                'color' => $type->color,
                'is_paid' => $type->is_paid,
                'is_active' => $type->is_active,
                'balance_mode' => $type->balance_mode,
                'requires_attachment' => $type->requires_attachment,
            ])
            ->values()
            ->all();
    }

    private static function hrPermissions(): array
    {
        $local = app()->getLocale() === 'ar';
        $options = [];
        foreach (PermissionCatalog::definitions() as $name => $definition) {
            if (str_starts_with($definition['module'], 'hr-')) {
                $options[] = ['value' => $name, 'title' => $local ? $definition['name_local'] : $definition['name_en']];
            }
        }

        return $options;
    }

    private static function options(array $labels): array
    {
        $options = [];
        foreach ($labels as $value => $title) {
            $options[] = ['value' => $value, 'title' => $title];
        }

        return $options;
    }
}
