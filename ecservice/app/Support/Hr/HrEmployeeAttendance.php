<?php

namespace App\Support\Hr;

use App\Models\Hr\AttendancePolicy;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeAttendanceConfig;
use App\Models\Hr\EmployeeChange;
use App\Models\Hr\WorkLocation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Employee attendance configuration as effective-dated hr_employee_attendance_configs rows (rules in
 * HrEffectiveDating): attendance method, GPS verification, attendance policy and grace period. One change can
 * also move the employee to another shift / work location from the same date (stored in their own
 * effective-dated tables). Rules enforced here:
 *  - manual (and exempt) employees never provide GPS: their GPS setting is always "none";
 *  - electronic employees follow the GPS requirement of their own setting or of the policy; a required GPS
 *    check needs a work location with coordinates;
 *  - a manual employee's policy (or the center, without a policy) must allow manual attendance;
 *  - past rows are never rewritten; every change is written to the employee timeline.
 * hr_employees caches the row in force today; rows scheduled for later are applied by hr:apply-attendance-changes.
 */
final class HrEmployeeAttendance
{
    private const CHANGE_TYPES = [
        'attendance_mode' => 'attendance_method',
        'gps_policy' => 'gps_policy',
        'grace_minutes' => 'grace_period',
        'attendance_policy_id' => 'attendance_policy',
    ];

    /** GPS setting stored for a method: none unless electronic; electronic defaults to following the policy. */
    public static function normalizedGps(string $mode, ?string $gps): string
    {
        if ($mode !== Employee::ATTENDANCE_ELECTRONIC) {
            return Employee::GPS_NONE;
        }

        return in_array($gps, Employee::GPS_POLICIES, true) ? $gps : Employee::GPS_FOLLOW_POLICY;
    }

    /** First row for a new employee, from the employment start; nothing precedes it. */
    public static function open(Employee $employee, ?User $actor): EmployeeAttendanceConfig
    {
        return $employee->attendanceConfigs()->create([
            'center_id' => $employee->center_id,
            'attendance_mode' => $employee->attendance_mode,
            'gps_policy' => $employee->gps_policy,
            'attendance_policy_id' => $employee->attendance_policy_id,
            'grace_minutes' => $employee->grace_minutes,
            'effective_from' => $employee->employment_start_date->toDateString(),
            'created_by' => $actor?->id,
        ]);
    }

    /**
     * Validates a configuration for a new employee (no history yet).
     */
    public static function assertValidForNewEmployee(int $centerId, array $data, ?int $workLocationId, CarbonInterface $from): void
    {
        $mode = $data['attendance_mode'] ?? Employee::ATTENDANCE_MANUAL;
        $policy = self::policy($centerId, $data['attendance_policy_id'] ?? null, null);
        $location = $workLocationId ? WorkLocation::query()->forCenter($centerId)->whereKey($workLocationId)->first() : null;
        self::assertConsistent($centerId, $mode, self::normalizedGps($mode, $data['gps_policy'] ?? null), $policy, $location, $from);
    }

    /**
     * Applies a configuration change from $data['effective_from'] (past dates down to the payroll lock, or a
     * later date to schedule it).
     *
     * @param  array{attendance_mode: string, gps_policy?: ?string, attendance_policy_id?: ?int, grace_minutes?: ?int,
     *               shift_id?: ?int, work_location_id?: ?int, effective_from: string, reason?: ?string}  $data
     * @return array{config: ?EmployeeAttendanceConfig, scheduled: bool}
     */
    public static function configure(Employee $employee, array $data, User $actor): array
    {
        if (! $employee->isEmployed()) {
            throw new HrException('employee_not_employed');
        }

        $from = Carbon::parse($data['effective_from'])->startOfDay();
        $reason = $data['reason'] ?? null;
        $mode = (string) $data['attendance_mode'];
        $values = [
            'attendance_mode' => $mode,
            'gps_policy' => self::normalizedGps($mode, $data['gps_policy'] ?? null),
            'attendance_policy_id' => isset($data['attendance_policy_id']) ? (int) $data['attendance_policy_id'] : null,
            'grace_minutes' => isset($data['grace_minutes']) ? (int) $data['grace_minutes'] : null,
        ];

        return DB::transaction(function () use ($employee, $data, $actor, $from, $reason, $values) {
            $employee = Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();

            $inForce = self::configOn($employee, $from);
            $policy = self::policy($employee->center_id, $values['attendance_policy_id'], $inForce?->attendance_policy_id);

            [$branchId, $currentLocationId] = self::placementOn($employee, $from);
            $locationChanged = array_key_exists('work_location_id', $data)
                && (int) $data['work_location_id'] !== (int) $currentLocationId;
            $locationId = $locationChanged ? ($data['work_location_id'] !== null ? (int) $data['work_location_id'] : null) : $currentLocationId;
            $location = $locationId ? WorkLocation::query()->forCenter($employee->center_id)->whereKey($locationId)->first() : null;
            if ($locationId !== null && $location === null) {
                throw new HrException('reference_not_in_center', 'work_location_id');
            }

            $shiftId = isset($data['shift_id']) ? (int) $data['shift_id'] : null;
            $shiftChanged = $shiftId !== null
                && $shiftId !== (int) $employee->shiftAssignments()->effectiveOn($from)->value('shift_id');

            $configChanged = $inForce === null || self::differs($inForce, $values);
            if (! $configChanged && ! $shiftChanged && ! $locationChanged) {
                throw new HrException('attendance_config_unchanged', 'attendance_mode');
            }

            self::assertConsistent($employee->center_id, $values['attendance_mode'], $values['gps_policy'], $policy, $location, $from);

            $row = null;
            if ($configChanged) {
                $row = self::write($employee, $values, $from, $reason, $actor);
            }
            if ($locationChanged) {
                HrWorkAssignments::transfer($employee, [
                    'branch_id' => $branchId ?? $location?->branch_id,
                    'work_location_id' => $locationId,
                    'effective_from' => $from->toDateString(),
                    'reason' => $reason,
                ], $actor);
            }
            if ($shiftChanged) {
                HrEmployeeRecords::assignShift($employee, [
                    'shift_id' => $shiftId,
                    'effective_from' => $from->toDateString(),
                    'reason' => $reason,
                ], $actor);
            }

            return ['config' => $row, 'scheduled' => $from->gt(today())];
        });
    }

    /** Cancels a configuration change that has not taken effect yet; the previous row applies again. */
    public static function cancel(Employee $employee, EmployeeAttendanceConfig $config): void
    {
        DB::transaction(function () use ($employee, $config) {
            $employee = Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $previous = HrEffectiveDating::cancel($employee, $employee->attendanceConfigs(), $config);

            foreach (self::CHANGE_TYPES as $field => $type) {
                if (self::fieldDiffers($previous?->{$field}, $config->{$field})) {
                    HrEmployeeChangeLog::record(
                        $employee, $type, $config->{$field}, $previous?->{$field}, $config->effective_from, null, $config,
                        $field === 'attendance_policy_id' ? ['old' => self::policySnapshot($config->attendance_policy_id)] : [],
                        EmployeeChange::ACTION_CANCELLED
                    );
                }
            }
        });
    }

    /**
     * Copies the row in force on $date into hr_employees for every employee whose cached columns differ.
     *
     * @return int number of employees updated
     */
    public static function applyDue(?int $centerId = null, ?CarbonInterface $date = null): int
    {
        $table = (new EmployeeAttendanceConfig())->getTable();
        $due = EmployeeAttendanceConfig::query()
            ->effectiveOn(($date ?? today())->toDateString())
            ->join('hr_employees as e', 'e.id', '=', "{$table}.employee_id")
            ->when($centerId !== null, fn ($query) => $query->where("{$table}.center_id", $centerId))
            ->where(function ($query) use ($table) {
                $query->whereColumn('e.attendance_mode', '!=', "{$table}.attendance_mode")
                    ->orWhereColumn('e.gps_policy', '!=', "{$table}.gps_policy")
                    ->orWhereRaw("COALESCE(e.attendance_policy_id, 0) <> COALESCE({$table}.attendance_policy_id, 0)")
                    ->orWhereRaw("COALESCE(e.grace_minutes, -1) <> COALESCE({$table}.grace_minutes, -1)");
            })
            ->select("{$table}.*")
            ->get();

        foreach ($due as $config) {
            DB::transaction(function () use ($config) {
                $employee = Employee::query()->whereKey($config->employee_id)->lockForUpdate()->first();
                if ($employee !== null) {
                    self::applyToEmployee($employee, $config);
                }
            });
        }

        return $due->count();
    }

    /**
     * Configuration issues that do not block saving but prevent electronic attendance from working as expected.
     *
     * @return array<int, string> reason keys (tr.hr.attendance_warnings)
     */
    public static function warnings(Employee $employee, AttendanceRuleSet $rules, ?CarbonInterface $date = null): array
    {
        $warnings = [];
        if (! $rules->isElectronic()) {
            return $warnings;
        }

        $date ??= today();
        if (! $employee->shiftAssignments()->effectiveOn($date)->exists()) {
            $warnings[] = 'no_shift';
        }
        if ($rules->gpsRequirement !== AttendancePolicy::GPS_NONE) {
            $locationId = self::placementOn($employee, $date)[1];
            $location = $locationId ? WorkLocation::withTrashed()->find($locationId) : null;
            if ($location === null || ! $location->hasCoordinates()) {
                $warnings[] = 'no_location_coordinates';
            }
        }

        return $warnings;
    }

    private static function write(Employee $employee, array $values, Carbon $from, ?string $reason, User $actor): EmployeeAttendanceConfig
    {
        /** @var EmployeeAttendanceConfig|null $latest */
        $latest = $employee->attendanceConfigs()->orderByDesc('effective_from')->first();

        if ($latest !== null && $latest->effective_from->isSameDay($from)) {
            // Same-day correction of a row that is not locked yet.
            HrEffectiveDating::assertNotLocked($employee->center_id, $from, 'effective_from');
            HrEffectiveDating::assertWithinEmployment($employee, $from, 'effective_from');
            $previous = $latest->replicate();
            $latest->fill($values + ['reason' => $reason ?? $latest->reason])->save();
            $row = $latest;
        } else {
            /** @var EmployeeAttendanceConfig $row */
            [$row, $previous] = HrEffectiveDating::append($employee, $employee->attendanceConfigs(), $values + [
                'effective_from' => $from->toDateString(),
                'reason' => $reason,
                'created_by' => $actor->id,
            ]);
        }

        foreach (self::CHANGE_TYPES as $field => $type) {
            if (self::fieldDiffers($previous?->{$field}, $row->{$field})) {
                HrEmployeeChangeLog::record(
                    $employee, $type, $previous?->{$field}, $row->{$field}, $row->effective_from, $row->reason, $row,
                    $field === 'attendance_policy_id' ? [
                        'old' => self::policySnapshot($previous?->attendance_policy_id),
                        'new' => self::policySnapshot($row->attendance_policy_id),
                    ] : []
                );
            }
        }

        if (! $row->effective_from->gt(today())) {
            $employee->updated_by = $actor->id;
            self::applyToEmployee($employee, $row);
        }

        return $row;
    }

    private static function assertConsistent(int $centerId, string $mode, string $gps, ?AttendancePolicy $policy, ?WorkLocation $location, CarbonInterface $from): void
    {
        $rules = self::policyRulesOn($centerId, $policy, $from);

        if ($mode === Employee::ATTENDANCE_MANUAL && ! $rules['allow_manual_attendance']) {
            throw new HrException($policy !== null ? 'manual_not_allowed_by_policy' : 'manual_not_allowed_by_center', 'attendance_mode');
        }

        $requirement = AttendanceRuleSet::resolveGps($mode, $gps, (string) $rules['gps_requirement']);
        if ($requirement === AttendancePolicy::GPS_REQUIRED && ! $location?->hasCoordinates()) {
            throw new HrException('gps_requires_location', 'work_location_id');
        }
    }

    /** Rules of $policy in force on $date (center defaults without a policy). */
    private static function policyRulesOn(int $centerId, ?AttendancePolicy $policy, CarbonInterface $date): array
    {
        if ($policy === null) {
            return HrAttendanceRules::centerDefaults($centerId);
        }

        $version = $policy->versions()->effectiveOn($date)->first()
            ?? $policy->versions()->orderBy('effective_from')->first();

        return ($version ?? $policy)->ruleValues();
    }

    /** The policy to assign: in the center, and active unless the employee already uses it. */
    private static function policy(int $centerId, mixed $policyId, ?int $currentId): ?AttendancePolicy
    {
        if ($policyId === null || $policyId === '') {
            return null;
        }

        $policy = AttendancePolicy::query()->forCenter($centerId)->whereKey((int) $policyId)->first();
        if ($policy === null) {
            throw new HrException('reference_not_in_center', 'attendance_policy_id');
        }
        if (! $policy->is_active && (int) $currentId !== $policy->id) {
            throw new HrException('reference_inactive', 'attendance_policy_id');
        }

        return $policy;
    }

    private static function configOn(Employee $employee, CarbonInterface $date): ?EmployeeAttendanceConfig
    {
        return $employee->attendanceConfigs()->effectiveOn($date)->first()
            ?? $employee->attendanceConfigs()->orderByDesc('effective_from')->first();
    }

    /** @return array{0: ?int, 1: ?int} branch id and work location id in force on $date */
    private static function placementOn(Employee $employee, CarbonInterface $date): array
    {
        $row = $employee->workAssignments()->effectiveOn($date)->first();
        if ($row !== null) {
            return [(int) $row->branch_id, $row->work_location_id !== null ? (int) $row->work_location_id : null];
        }

        return [$employee->branch_id, $employee->work_location_id];
    }

    private static function differs(EmployeeAttendanceConfig $config, array $values): bool
    {
        foreach ($values as $field => $value) {
            if (self::fieldDiffers($config->{$field}, $value)) {
                return true;
            }
        }

        return false;
    }

    private static function fieldDiffers(mixed $old, mixed $new): bool
    {
        return ($old === null) !== ($new === null) || (string) $old !== (string) $new;
    }

    private static function policySnapshot(?int $policyId): ?array
    {
        return HrEmployeeChangeLog::snapshot($policyId ? AttendancePolicy::withTrashed()->find($policyId) : null);
    }

    private static function applyToEmployee(Employee $employee, EmployeeAttendanceConfig $config): void
    {
        $employee->fill($config->only(EmployeeAttendanceConfig::CONFIG_FIELDS));
        if ($employee->isDirty()) {
            $employee->save();
        }
    }
}
