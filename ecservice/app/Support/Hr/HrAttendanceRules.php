<?php

namespace App\Support\Hr;

use App\Models\Hr\AttendancePolicy;
use App\Models\Hr\AttendancePolicyVersion;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeAttendanceConfig;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Resolves the attendance rules (AttendanceRuleSet) per employee and date:
 *  1. the employee's attendance configuration row in force that day (dates before the first row use the first
 *     row; employees without rows use the cached hr_employees columns);
 *  2. the version of its attendance policy in force that day, soft-deleted policies included, so history keeps
 *     the rules that applied; dates before the first version use the first one;
 *  3. without a policy, the center defaults from hr_settings (attendance.*).
 * Load once per batch (load()) to resolve many employee-days without extra queries.
 */
final class HrAttendanceRules
{
    /** @var array<int, Collection<int, EmployeeAttendanceConfig>> */
    private array $configs = [];

    /** @var array<int, AttendancePolicy> */
    private array $policies = [];

    private ?array $defaults = null;

    private function __construct(private readonly int $centerId)
    {
    }

    /**
     * @param  Collection<int, Employee>  $employees  employees of one center
     */
    public static function load(int $centerId, Collection $employees, CarbonInterface|string $from, CarbonInterface|string $to): self
    {
        $resolver = new self($centerId);
        $ids = $employees->pluck('id')->all();
        if ($ids === []) {
            return $resolver;
        }

        $first = Carbon::parse($from)->toDateString();
        $last = Carbon::parse($to)->toDateString().' 23:59:59';
        $rows = EmployeeAttendanceConfig::query()
            ->whereIn('employee_id', $ids)
            ->where('effective_from', '<=', $last)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $first))
            ->orderBy('effective_from')
            ->get();

        // Ranges before an employee's first row fall back to that row.
        $covered = $rows->pluck('employee_id')->unique()->all();
        $missing = array_values(array_diff($ids, $covered));
        if ($missing !== []) {
            $earliest = EmployeeAttendanceConfig::query()
                ->whereIn('employee_id', $missing)
                ->orderBy('effective_from')
                ->get()
                ->unique('employee_id');
            $rows = $rows->concat($earliest);
        }

        $resolver->configs = $rows->groupBy('employee_id')->all();

        $policyIds = $rows->pluck('attendance_policy_id')
            ->merge($employees->pluck('attendance_policy_id'))
            ->filter()
            ->unique()
            ->all();
        $resolver->loadPolicies($policyIds);

        return $resolver;
    }

    public static function on(Employee $employee, CarbonInterface|string $date): AttendanceRuleSet
    {
        $day = Carbon::parse($date)->toDateString();

        return self::load($employee->center_id, collect([$employee]), $day, $day)->resolve($employee, $day);
    }

    /**
     * Rule values used when no policy applies, from the center settings.
     *
     * @return array<string, mixed>
     */
    public static function centerDefaults(int $centerId): array
    {
        $settings = HrSettings::all($centerId);
        $window = (int) ($settings['attendance.punch_window_minutes'] ?? 240);
        $counting = ! empty($settings['attendance.count_after_grace'])
            ? AttendancePolicy::COUNTING_EXCESS_ONLY
            : AttendancePolicy::COUNTING_FULL;

        return [
            'check_in_before_minutes' => $window,
            'check_in_after_minutes' => null,
            'check_out_before_minutes' => null,
            'check_out_after_minutes' => $window,
            'late_grace_minutes' => (int) ($settings['attendance.late_grace_minutes'] ?? 15),
            'early_leave_grace_minutes' => (int) ($settings['attendance.early_leave_grace_minutes'] ?? 15),
            'late_counting' => $counting,
            'late_action' => AttendancePolicy::ACTION_RECORD_ONLY,
            'absence_after_minutes' => null,
            'early_leave_counting' => $counting,
            'early_leave_action' => AttendancePolicy::ACTION_RECORD_ONLY,
            'missing_checkout_action' => AttendancePolicy::MISSING_CHECKOUT_FLAG,
            'duplicate_punch_action' => AttendancePolicy::DUPLICATE_FIRST_IN_LAST_OUT,
            'duplicate_window_minutes' => 5,
            'gps_requirement' => AttendancePolicy::GPS_REQUIRED,
            'allow_manual_attendance' => (bool) ($settings['attendance.allow_manual_attendance'] ?? true),
        ];
    }

    public function resolve(Employee $employee, CarbonInterface|string $date): AttendanceRuleSet
    {
        $day = Carbon::parse($date)->toDateString();
        $config = $this->configOn($employee->id, $day);

        $mode = (string) ($config?->attendance_mode ?? $employee->attendance_mode ?? Employee::ATTENDANCE_MANUAL);
        $gpsPolicy = (string) ($config?->gps_policy ?? $employee->gps_policy ?? Employee::GPS_NONE);
        $policyId = $config !== null ? $config->attendance_policy_id : $employee->attendance_policy_id;
        $grace = $config !== null ? $config->grace_minutes : $employee->grace_minutes;

        $policy = $policyId !== null ? ($this->policies[(int) $policyId] ?? null) : null;
        $version = $policy !== null ? $this->versionOn($policy, $day) : null;
        $rules = $policy !== null
            ? ($version ?? $policy)->ruleValues()
            : ($this->defaults ??= self::centerDefaults($this->centerId));

        return new AttendanceRuleSet(
            date: $day,
            attendanceMode: $mode,
            gpsPolicy: $gpsPolicy,
            gpsRequirement: AttendanceRuleSet::resolveGps($mode, $gpsPolicy, (string) $rules['gps_requirement']),
            configId: $config?->id,
            policyId: $policy?->id,
            policyVersionId: $version?->id,
            source: $policy !== null ? AttendanceRuleSet::SOURCE_POLICY : AttendanceRuleSet::SOURCE_CENTER_DEFAULTS,
            employeeGraceMinutes: $grace !== null ? (int) $grace : null,
            lateGraceMinutes: (int) $rules['late_grace_minutes'],
            earlyLeaveGraceMinutes: (int) $rules['early_leave_grace_minutes'],
            checkInBeforeMinutes: (int) $rules['check_in_before_minutes'],
            checkInAfterMinutes: $rules['check_in_after_minutes'] !== null ? (int) $rules['check_in_after_minutes'] : null,
            checkOutBeforeMinutes: $rules['check_out_before_minutes'] !== null ? (int) $rules['check_out_before_minutes'] : null,
            checkOutAfterMinutes: (int) $rules['check_out_after_minutes'],
            lateCounting: (string) $rules['late_counting'],
            lateAction: (string) $rules['late_action'],
            absenceAfterMinutes: $rules['absence_after_minutes'] !== null ? (int) $rules['absence_after_minutes'] : null,
            earlyLeaveCounting: (string) $rules['early_leave_counting'],
            earlyLeaveAction: (string) $rules['early_leave_action'],
            missingCheckoutAction: (string) $rules['missing_checkout_action'],
            duplicatePunchAction: (string) $rules['duplicate_punch_action'],
            duplicateWindowMinutes: (int) $rules['duplicate_window_minutes'],
            allowManualAttendance: (bool) $rules['allow_manual_attendance'],
        );
    }

    private function configOn(int $employeeId, string $date): ?EmployeeAttendanceConfig
    {
        $rows = $this->configs[$employeeId] ?? null;
        if ($rows === null || $rows->isEmpty()) {
            return null;
        }

        return $rows->first(fn (EmployeeAttendanceConfig $row) => $row->isEffectiveOn($date)) ?? $rows->first();
    }

    private function versionOn(AttendancePolicy $policy, string $date): ?AttendancePolicyVersion
    {
        $version = null;
        foreach ($policy->versions as $candidate) {
            if ($candidate->effective_from->toDateString() > $date) {
                break;
            }
            $version = $candidate;
        }

        return $version ?? $policy->versions->first();
    }

    private function loadPolicies(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $this->policies = AttendancePolicy::withTrashed()
            ->forCenter($this->centerId)
            ->whereIn('id', $ids)
            ->with(['versions' => fn ($q) => $q->orderBy('effective_from')])
            ->get()
            ->keyBy('id')
            ->all();
    }
}
