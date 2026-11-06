<?php

namespace App\Support\Hr;

use App\Models\Hr\AdditionalWorkDay;
use App\Models\Hr\ApprovalRequest;
use App\Models\Hr\ApprovalWorkflow;
use App\Models\Hr\AttendanceDay;
use App\Models\Hr\AttendanceDevice;
use App\Models\Hr\AttendancePeriod;
use App\Models\Hr\AttendancePolicy;
use App\Models\Hr\AttendancePolicyVersion;
use App\Models\Hr\AttendanceRecord;
use App\Models\Hr\Branch;
use App\Models\Hr\Department;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeAttendanceConfig;
use App\Models\Hr\EmployeeDocument;
use App\Models\Hr\EmployeeInsurance;
use App\Models\Hr\EmployeeSalary;
use App\Models\Hr\EmployeeShiftAssignment;
use App\Models\Hr\EmployeeWorkAssignment;
use App\Models\Hr\EmploymentPeriod;
use App\Models\Hr\Holiday;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\LeaveType;
use App\Models\Hr\LocationException;
use App\Models\Hr\Setting;
use App\Models\Hr\Shift;
use App\Models\Hr\ShiftException;
use App\Models\Hr\ShiftVersion;
use App\Models\Hr\WorkLocation;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Stable aliases stored in hr_audit_logs.auditable_type and hr_approval_requests.approvable_type, so
 * history survives class renames. Not enforced: models outside HR keep their class-name morph types.
 */
class HrMorphMap
{
    public const MAP = [
        'hr_branch' => Branch::class,
        'hr_work_location' => WorkLocation::class,
        'hr_department' => Department::class,
        'hr_employee' => Employee::class,
        'hr_employee_salary' => EmployeeSalary::class,
        'hr_employee_insurance' => EmployeeInsurance::class,
        'hr_employee_shift_assignment' => EmployeeShiftAssignment::class,
        'hr_employee_work_assignment' => EmployeeWorkAssignment::class,
        'hr_employee_document' => EmployeeDocument::class,
        'hr_employment_period' => EmploymentPeriod::class,
        'hr_shift' => Shift::class,
        'hr_shift_version' => ShiftVersion::class,
        'hr_shift_exception' => ShiftException::class,
        'hr_attendance_policy' => AttendancePolicy::class,
        'hr_attendance_policy_version' => AttendancePolicyVersion::class,
        'hr_employee_attendance_config' => EmployeeAttendanceConfig::class,
        'hr_setting' => Setting::class,
        'hr_approval_workflow' => ApprovalWorkflow::class,
        'hr_approval_request' => ApprovalRequest::class,
        'hr_attendance_device' => AttendanceDevice::class,
        'hr_location_exception' => LocationException::class,
        'hr_attendance_record' => AttendanceRecord::class,
        'hr_holiday' => Holiday::class,
        'hr_leave_type' => LeaveType::class,
        'hr_leave_request' => LeaveRequest::class,
        'hr_attendance_day' => AttendanceDay::class,
        'hr_attendance_period' => AttendancePeriod::class,
        'hr_additional_work_day' => AdditionalWorkDay::class,
    ];

    public static function register(): void
    {
        Relation::morphMap(self::MAP);
    }
}
