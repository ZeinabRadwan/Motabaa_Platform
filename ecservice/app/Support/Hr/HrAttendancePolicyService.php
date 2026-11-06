<?php

namespace App\Support\Hr;

use App\Models\Hr\AttendancePolicy;
use App\Models\Hr\AttendancePolicyVersion;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeAttendanceConfig;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Attendance policies. Code, names, description and status are edited in place. Rules (check-in / check-out
 * windows, grace periods, late / early departure handling, missing check-out, duplicate punches, GPS, manual
 * attendance) are effective-dated once the policy has been used: the change becomes a new
 * hr_attendance_policy_versions row from the chosen date (today or later), so attendance for earlier dates
 * keeps the rules that applied then. hr_attendance_policies mirrors the version in force today; later ones are
 * applied by hr:apply-attendance-changes.
 */
final class HrAttendancePolicyService
{
    private const IDENTITY_FIELDS = ['code', 'name_ar', 'name_en', 'description'];

    public static function create(array $data, int $centerId, User $actor): AttendancePolicy
    {
        $rules = self::rules($data, HrAttendanceRules::centerDefaults($centerId));
        $from = Carbon::parse($data['effective_from'] ?? today())->startOfDay();
        HrEffectiveDating::assertNotLocked($centerId, $from, 'effective_from');

        return DB::transaction(function () use ($data, $centerId, $actor, $rules, $from) {
            $policy = new AttendancePolicy(Arr::only($data, self::IDENTITY_FIELDS) + $rules);
            $policy->center_id = $centerId;
            $policy->is_active = (bool) ($data['is_active'] ?? true);
            $policy->created_by = $actor->id;
            $policy->save();

            $version = $policy->versions()->create($rules + [
                'center_id' => $centerId,
                'effective_from' => $from->toDateString(),
                'reason' => $data['reason'] ?? null,
                'created_by' => $actor->id,
            ]);
            $policy->current_version_id = $version->id;
            $policy->saveQuietly();

            return $policy;
        });
    }

    /**
     * @return array{0: AttendancePolicy, 1: ?AttendancePolicyVersion} the policy and the version scheduled for a later date, if any
     */
    public static function update(AttendancePolicy $policy, array $data, User $actor): array
    {
        $current = self::normalize($policy->ruleValues());
        $rules = self::rules($data, $policy->ruleValues());
        $changed = self::normalize($rules) !== $current;
        $used = self::hasHistory($policy);

        if ($changed && $used) {
            if (empty($data['effective_from'])) {
                throw new HrException('policy_change_effective_date_required', 'effective_from');
            }
            $from = Carbon::parse($data['effective_from'])->startOfDay();
            if ($from->lt(today())) {
                throw new HrException('policy_change_in_past', 'effective_from');
            }
            if (! $rules['allow_manual_attendance']) {
                self::assertNoManualEmployees($policy, $from);
            }
        } elseif ($changed && ! $rules['allow_manual_attendance']) {
            self::assertNoManualEmployees($policy, today());
        }

        if (array_key_exists('is_active', $data) && ! $data['is_active'] && $policy->is_active) {
            self::assertNotInUse($policy);
        }

        return DB::transaction(function () use ($policy, $data, $actor, $rules, $changed, $used) {
            $scheduled = null;
            $policy->fill(Arr::only($data, self::IDENTITY_FIELDS));
            if (array_key_exists('is_active', $data)) {
                $policy->is_active = (bool) $data['is_active'];
            }

            if ($changed && ! $used) {
                // Never used: correct the rules in place, there is no history to keep.
                $version = self::currentVersion($policy);
                $version?->fill($rules + ['reason' => $data['reason'] ?? $version->reason])->save();
                $policy->fill($rules);
            } elseif ($changed) {
                $version = self::appendVersion($policy, $rules, $data['effective_from'], $data['reason'] ?? null, $actor);
                if ($version->effective_from->gt(today())) {
                    $scheduled = $version;
                } else {
                    $policy->fill($rules);
                    $policy->current_version_id = $version->id;
                }
            }
            $policy->save();

            return [$policy, $scheduled];
        });
    }

    public static function setActive(AttendancePolicy $policy, bool $active): AttendancePolicy
    {
        if (! $active) {
            self::assertNotInUse($policy);
        }
        $policy->is_active = $active;
        $policy->save();

        return $policy;
    }

    public static function delete(AttendancePolicy $policy): void
    {
        self::assertNotInUse($policy);
        $policy->delete();
    }

    public static function cancelVersion(AttendancePolicy $policy, AttendancePolicyVersion $version): void
    {
        DB::transaction(function () use ($policy, $version) {
            AttendancePolicy::query()->whereKey($policy->id)->lockForUpdate()->first();
            HrEffectiveDating::cancelRow($policy->center_id, $policy->versions(), $version);
        });
    }

    /**
     * Copies the version in force on $date into hr_attendance_policies for every policy whose cached version differs.
     *
     * @return int number of policies updated
     */
    public static function applyDue(?int $centerId = null, ?CarbonInterface $date = null): int
    {
        $table = (new AttendancePolicyVersion())->getTable();
        $due = AttendancePolicyVersion::query()
            ->effectiveOn(($date ?? today())->toDateString())
            ->join('hr_attendance_policies as p', 'p.id', '=', "{$table}.attendance_policy_id")
            ->when($centerId !== null, fn ($query) => $query->where("{$table}.center_id", $centerId))
            ->where(fn ($query) => $query->whereNull('p.current_version_id')->orWhereColumn('p.current_version_id', '!=', "{$table}.id"))
            ->select("{$table}.*")
            ->get();

        foreach ($due as $version) {
            DB::transaction(function () use ($version) {
                $policy = AttendancePolicy::withTrashed()->whereKey($version->attendance_policy_id)->lockForUpdate()->first();
                if ($policy !== null) {
                    $policy->fill($version->ruleValues());
                    $policy->current_version_id = $version->id;
                    $policy->save();
                }
            });
        }

        return $due->count();
    }

    /** Whether any employee configuration (past, present or scheduled) references the policy. */
    public static function hasHistory(AttendancePolicy $policy): bool
    {
        if (! $policy->exists) {
            return false;
        }

        return $policy->attendanceConfigs()->exists() || $policy->employees()->exists();
    }

    /** Employed employees whose configuration in force today or later uses the policy. */
    public static function inUse(AttendancePolicy $policy): bool
    {
        return self::configsFrom($policy, today())->exists();
    }

    /**
     * Rules for a policy: $data's rule fields over $base, validated together.
     *
     * @return array<string, mixed>
     */
    public static function rules(array $data, array $base): array
    {
        $rules = [];
        foreach (AttendancePolicy::RULE_FIELDS as $field) {
            $rules[$field] = array_key_exists($field, $data) ? $data[$field] : ($base[$field] ?? null);
        }
        $rules['allow_manual_attendance'] = (bool) $rules['allow_manual_attendance'];

        foreach (['check_in_before_minutes', 'check_out_after_minutes', 'late_grace_minutes', 'early_leave_grace_minutes', 'duplicate_window_minutes'] as $field) {
            if ($rules[$field] === null) {
                throw new HrException('policy_rule_required', $field);
            }
        }
        if ($rules['check_in_after_minutes'] !== null && (int) $rules['check_in_after_minutes'] < (int) $rules['late_grace_minutes']) {
            throw new HrException('check_in_window_shorter_than_grace', 'check_in_after_minutes', 422, ['minutes' => (int) $rules['late_grace_minutes']]);
        }
        if ($rules['absence_after_minutes'] !== null && (int) $rules['absence_after_minutes'] <= (int) $rules['late_grace_minutes']) {
            throw new HrException('absence_within_grace', 'absence_after_minutes', 422, ['minutes' => (int) $rules['late_grace_minutes']]);
        }

        return $rules;
    }

    private static function assertNotInUse(AttendancePolicy $policy): void
    {
        if (self::inUse($policy)) {
            throw new HrException('attendance_policy_in_use');
        }
    }

    /** A policy that disallows manual attendance cannot apply to manual employees from $from on. */
    private static function assertNoManualEmployees(AttendancePolicy $policy, CarbonInterface $from): void
    {
        if (self::configsFrom($policy, $from)->where('attendance_mode', Employee::ATTENDANCE_MANUAL)->exists()) {
            throw new HrException('policy_used_by_manual_employees', 'allow_manual_attendance');
        }
    }

    /** Configuration rows of employed employees using the policy on $from or later. */
    private static function configsFrom(AttendancePolicy $policy, CarbonInterface $from)
    {
        return EmployeeAttendanceConfig::query()
            ->where('attendance_policy_id', $policy->id)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from->toDateString()))
            ->whereHas('employee', fn ($q) => $q->employed());
    }

    private static function normalize(array $rules): array
    {
        $normalized = [];
        foreach (AttendancePolicy::RULE_FIELDS as $field) {
            $value = $rules[$field] ?? null;
            $normalized[$field] = match (true) {
                $value === null => null,
                is_bool($value) || $field === 'allow_manual_attendance' => (bool) $value,
                is_numeric($value) => (int) $value,
                default => (string) $value,
            };
        }

        return $normalized;
    }

    private static function appendVersion(AttendancePolicy $policy, array $rules, string $from, ?string $reason, User $actor): AttendancePolicyVersion
    {
        AttendancePolicy::query()->whereKey($policy->id)->lockForUpdate()->first();
        $date = Carbon::parse($from)->startOfDay();

        /** @var AttendancePolicyVersion|null $latest */
        $latest = $policy->versions()->orderByDesc('effective_from')->first();
        if ($latest !== null && $latest->effective_from->isSameDay($date)) {
            // Same-day correction of a version that is not locked yet.
            HrEffectiveDating::assertNotLocked($policy->center_id, $date, 'effective_from');
            $latest->fill($rules + ['reason' => $reason ?? $latest->reason])->save();

            return $latest;
        }

        /** @var AttendancePolicyVersion $version */
        [$version] = HrEffectiveDating::appendRow($policy->center_id, $policy->versions(), $rules + [
            'effective_from' => $date->toDateString(),
            'reason' => $reason,
            'created_by' => $actor->id,
        ]);

        return $version;
    }

    private static function currentVersion(AttendancePolicy $policy): ?AttendancePolicyVersion
    {
        return ($policy->current_version_id ? $policy->versions()->find($policy->current_version_id) : null)
            ?? $policy->versions()->orderByDesc('effective_from')->first();
    }
}
