<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\Auditable;
use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * HR profile of a staff user within one center. Personal and contract data remain on users
 * (name, phone, nationality, id_or_residence_number, job_title, contract_type, hire_date, ...).
 * Salary, insurance and shift are effective-dated child rows; employment periods drive payroll eligibility.
 */
class Employee extends Model
{
    use Auditable, BelongsToCenter, SoftDeletes;

    protected $table = 'hr_employees';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PROBATION = 'probation';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_TERMINATED = 'terminated';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_PROBATION,
        self::STATUS_SUSPENDED,
        self::STATUS_TERMINATED,
    ];

    /** Statuses an employed person can be moved between with a normal edit; ending employment is a deactivation. */
    public const EMPLOYED_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_PROBATION,
        self::STATUS_SUSPENDED,
    ];

    public const ATTENDANCE_ELECTRONIC = 'electronic';
    public const ATTENDANCE_MANUAL = 'manual';
    public const ATTENDANCE_EXEMPT = 'exempt';

    public const ATTENDANCE_MODES = [
        self::ATTENDANCE_ELECTRONIC,
        self::ATTENDANCE_MANUAL,
        self::ATTENDANCE_EXEMPT,
    ];

    /**
     * required: check-in only inside the work location radius; flexible: record the position and flag it; none;
     * policy: follow the attendance policy's GPS requirement. Always none unless the method is electronic.
     */
    public const GPS_FOLLOW_POLICY = 'policy';
    public const GPS_REQUIRED = 'required';
    public const GPS_FLEXIBLE = 'flexible';
    public const GPS_NONE = 'none';

    public const GPS_POLICIES = [
        self::GPS_FOLLOW_POLICY,
        self::GPS_REQUIRED,
        self::GPS_FLEXIBLE,
        self::GPS_NONE,
    ];

    public const TERMINATION_REASONS = [
        'resignation',
        'contract_end',
        'employer_termination',
        'article_80',
        'retirement',
        'death',
        'other',
    ];

    protected $fillable = [
        'center_id',
        'user_id',
        'employee_number',
        'branch_id',
        'department_id',
        'work_location_id',
        'manager_id',
        'employment_start_date',
        'contract_start_date',
        'employment_status',
        'attendance_mode',
        'gps_policy',
        'grace_minutes',
        'attendance_policy_id',
        'terminated_at',
        'termination_reason',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'employment_start_date' => 'date',
        'contract_start_date' => 'date',
        'terminated_at' => 'date',
        'grace_minutes' => 'integer',
    ];

    public static function statusLabels(): array
    {
        return self::labels(self::STATUSES, 'tr.hr.employment_statuses');
    }

    public static function attendanceModeLabels(): array
    {
        return self::labels(self::ATTENDANCE_MODES, 'tr.hr.attendance_modes');
    }

    public static function gpsPolicyLabels(): array
    {
        return self::labels(self::GPS_POLICIES, 'tr.hr.gps_policies');
    }

    public static function terminationReasonLabels(): array
    {
        return self::labels(self::TERMINATION_REASONS, 'tr.hr.termination_reasons');
    }

    public static function labels(array $keys, string $prefix): array
    {
        $labels = [];
        foreach ($keys as $key) {
            $labels[$key] = __("{$prefix}.{$key}");
        }

        return $labels;
    }

    public function auditExcludedAttributes(): array
    {
        return ['updated_by'];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id')->withTrashed();
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id')->withTrashed();
    }

    public function workLocation()
    {
        return $this->belongsTo(WorkLocation::class, 'work_location_id')->withTrashed();
    }

    public function attendancePolicy()
    {
        return $this->belongsTo(AttendancePolicy::class, 'attendance_policy_id')->withTrashed();
    }

    public function manager()
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function subordinates()
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function employmentPeriods()
    {
        return $this->hasMany(EmploymentPeriod::class, 'employee_id');
    }

    public function salaries()
    {
        return $this->hasMany(EmployeeSalary::class, 'employee_id');
    }

    public function insurances()
    {
        return $this->hasMany(EmployeeInsurance::class, 'employee_id');
    }

    public function shiftAssignments()
    {
        return $this->hasMany(EmployeeShiftAssignment::class, 'employee_id');
    }

    public function workAssignments()
    {
        return $this->hasMany(EmployeeWorkAssignment::class, 'employee_id');
    }

    public function attendanceConfigs()
    {
        return $this->hasMany(EmployeeAttendanceConfig::class, 'employee_id');
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class, 'employee_id');
    }

    public function attendancePunches()
    {
        return $this->hasMany(AttendancePunch::class, 'employee_id');
    }

    public function locationChecks()
    {
        return $this->hasMany(LocationCheck::class, 'employee_id');
    }

    public function attendanceDevices()
    {
        return $this->hasMany(AttendanceDevice::class, 'employee_id');
    }

    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class, 'employee_id');
    }

    public function changes()
    {
        return $this->hasMany(EmployeeChange::class, 'employee_id');
    }

    public function scopeEmployed(Builder $query): Builder
    {
        return $query->where($this->getTable().'.employment_status', '!=', self::STATUS_TERMINATED);
    }

    /**
     * Employees with at least one employment day inside [start, end], whatever their current status: an employee
     * deactivated mid-period still qualifies for that period, and is excluded from every later one.
     */
    public function scopeEligibleForPayrollPeriod(Builder $query, CarbonInterface|string $start, CarbonInterface|string $end): Builder
    {
        $from = Carbon::parse($start)->toDateString();
        $to = Carbon::parse($end)->toDateString();

        return $query->whereHas('employmentPeriods', fn (Builder $q) => $q->overlapping($from, $to));
    }

    public function isEmployed(): bool
    {
        return $this->employment_status !== self::STATUS_TERMINATED;
    }

    public function openEmploymentPeriod(): ?EmploymentPeriod
    {
        return $this->employmentPeriods()->whereNull('end_date')->latest('start_date')->first();
    }

    public function latestEmploymentPeriod(): ?EmploymentPeriod
    {
        return $this->employmentPeriods()->orderByDesc('start_date')->orderByDesc('id')->first();
    }

    public function salaryOn(CarbonInterface|string $date): ?EmployeeSalary
    {
        return $this->salaries()->effectiveOn($date)->first();
    }

    public function insuranceOn(CarbonInterface|string $date): ?EmployeeInsurance
    {
        return $this->insurances()->effectiveOn($date)->first();
    }

    public function shiftAssignmentOn(CarbonInterface|string $date): ?EmployeeShiftAssignment
    {
        return $this->shiftAssignments()->effectiveOn($date)->with('shift')->first();
    }

    public function workAssignmentOn(CarbonInterface|string $date): ?EmployeeWorkAssignment
    {
        return $this->workAssignments()->effectiveOn($date)->with(['branch', 'workLocation'])->first();
    }

    /**
     * The geofence GPS attendance must use on a date: the work location of the assignment in force that day.
     * Employees without any assignment row (legacy profiles without a branch) fall back to the cached column.
     */
    public function effectiveWorkLocation(CarbonInterface|string|null $date = null): ?WorkLocation
    {
        $assignment = $this->workAssignmentOn($date ?? today());
        if ($assignment !== null) {
            return $assignment->workLocation;
        }

        return $this->workAssignments()->exists() ? null : $this->workLocation;
    }
}
