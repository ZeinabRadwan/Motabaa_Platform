<?php

namespace App\Support\Hr;

use App\Models\Hr\Branch;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeWorkAssignment;
use App\Models\Hr\Shift;
use App\Models\Hr\ShiftException;
use App\Models\Hr\ShiftVersion;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shift definitions. Name, code, colour and branch are edited in place. Timing (hours, break, expected
 * hours, grace periods, working / rest days, rotation cycle) is effective-dated once the shift has been
 * used: the change becomes a new hr_shift_versions row from the chosen date, so past schedules keep the
 * hours that applied then. hr_shifts mirrors the version in force today; later ones are applied by
 * hr:apply-shift-versions.
 */
final class HrShiftService
{
    private const IDENTITY_FIELDS = ['code', 'name_ar', 'name_en', 'color', 'branch_id'];

    public static function create(array $data, int $centerId, User $actor): Shift
    {
        if (isset($data['branch_id'])) {
            HrOrganizationService::assertBranchInCenter($data['branch_id'], $centerId);
        }

        $shift = new Shift(Arr::only($data, self::IDENTITY_FIELDS));
        $shift->center_id = $centerId;
        $shift->shift_type = $data['shift_type']
            ?? (isset($data['start_time'], $data['end_time']) ? HrShiftMath::suggestType($data['start_time'], $data['end_time']) : Shift::TYPE_CUSTOM);
        $shift->is_active = (bool) ($data['is_active'] ?? true);
        $shift->created_by = $actor->id;
        $timing = self::timing($shift, $data);

        return DB::transaction(function () use ($shift, $timing, $actor) {
            $shift->fill($timing)->save();
            $version = $shift->versions()->create($timing + [
                'center_id' => $shift->center_id,
                'effective_from' => today()->toDateString(),
                'created_by' => $actor->id,
            ]);
            $shift->current_version_id = $version->id;
            $shift->saveQuietly();

            return $shift;
        });
    }

    /**
     * @return array{0: Shift, 1: ?ShiftVersion} the shift and the version scheduled for a later date, if any
     */
    public static function update(Shift $shift, array $data, User $actor): array
    {
        if (array_key_exists('branch_id', $data) && (int) $data['branch_id'] !== (int) $shift->branch_id) {
            HrOrganizationService::assertBranchInCenter($data['branch_id'], $shift->center_id);
            self::assertBranchChangeAllowed($shift, $data['branch_id']);
        }

        $type = $data['shift_type'] ?? $shift->shift_type;
        $used = self::hasHistory($shift);
        if (($type === Shift::TYPE_ROTATING) !== $shift->isRotating() && $used) {
            throw new HrException('shift_type_locked', 'shift_type');
        }

        $current = $shift->timingAttributes();
        $shift->fill(Arr::only($data, self::IDENTITY_FIELDS));
        $shift->shift_type = $type;
        $timing = self::timing($shift, $data);
        $changed = Shift::normalizeTiming($timing) !== $current;

        return DB::transaction(function () use ($shift, $data, $actor, $timing, $changed, $used) {
            $scheduled = null;
            if ($changed && ! $used) {
                // Never used: correct the definition in place, there is no history to keep.
                $version = self::currentVersion($shift);
                $version?->fill($timing)->save();
                $shift->fill($timing);
            } elseif ($changed) {
                if (empty($data['effective_from'])) {
                    throw new HrException('shift_change_effective_date_required', 'effective_from');
                }
                $version = self::appendVersion($shift, $timing, $data['effective_from'], $data['reason'] ?? null, $actor);
                if ($version->effective_from->gt(today())) {
                    $scheduled = $version;
                } else {
                    $shift->fill($timing);
                    $shift->current_version_id = $version->id;
                }
            }
            $shift->save();

            return [$shift, $scheduled];
        });
    }

    public static function setActive(Shift $shift, bool $active): Shift
    {
        if (! $active && ($reason = self::blockingReason($shift)) !== null) {
            throw new HrException($reason);
        }
        if ($active && $shift->isRotating()) {
            $slots = array_values(array_filter($shift->rotation_pattern ?? [], fn ($slot) => $slot !== null));
            $activeSlots = Shift::query()->forCenter($shift->center_id)->whereIn('id', $slots)->where('is_active', true)->count();
            if ($activeSlots !== count(array_unique($slots))) {
                throw new HrException('shift_rotation_slot_inactive');
            }
        }

        $shift->is_active = $active;
        $shift->save();

        return $shift;
    }

    public static function delete(Shift $shift): void
    {
        if (($reason = self::blockingReason($shift)) !== null) {
            throw new HrException($reason);
        }
        $shift->delete();
    }

    public static function cancelVersion(Shift $shift, ShiftVersion $version): void
    {
        DB::transaction(function () use ($shift, $version) {
            Shift::query()->whereKey($shift->id)->lockForUpdate()->first();
            HrEffectiveDating::cancelRow($shift->center_id, $shift->versions(), $version);
        });
    }

    /**
     * Copies the version in force on $date into hr_shifts for every shift whose cached version differs.
     *
     * @return int number of shifts updated
     */
    public static function applyDue(?int $centerId = null, ?CarbonInterface $date = null): int
    {
        $table = (new ShiftVersion())->getTable();
        $due = ShiftVersion::query()
            ->effectiveOn(($date ?? today())->toDateString())
            ->join('hr_shifts as s', 's.id', '=', "{$table}.shift_id")
            ->when($centerId !== null, fn ($query) => $query->where("{$table}.center_id", $centerId))
            ->where(fn ($query) => $query->whereNull('s.current_version_id')->orWhereColumn('s.current_version_id', '!=', "{$table}.id"))
            ->select("{$table}.*")
            ->get();

        foreach ($due as $version) {
            DB::transaction(function () use ($version) {
                $shift = Shift::withTrashed()->whereKey($version->shift_id)->lockForUpdate()->first();
                if ($shift !== null) {
                    $shift->fill($version->timingAttributes());
                    $shift->current_version_id = $version->id;
                    $shift->save();
                }
            });
        }

        return $due->count();
    }

    /**
     * Assigns $shift to each employee from the same date. Each employee is processed on its own, so one
     * failure (e.g. a locked payroll period) does not undo the others.
     *
     * @param  Collection<int, Employee>  $employees
     * @return array{assigned: int, failed: array<int, array{employee_id: int, name: ?string, employee_number: ?string, reason: string, message: string}>}
     */
    public static function assignMany(Shift $shift, Collection $employees, array $data, User $actor): array
    {
        $assigned = 0;
        $failed = [];
        foreach ($employees as $employee) {
            try {
                HrEmployeeRecords::assignShift($employee, [
                    'shift_id' => $shift->id,
                    'effective_from' => $data['effective_from'],
                    'reason' => $data['reason'] ?? null,
                    'rotation_offset' => $data['rotation_offset'] ?? null,
                ], $actor);
                $assigned++;
            } catch (HrException $e) {
                $failed[] = [
                    'employee_id' => $employee->id,
                    'name' => $employee->user?->name,
                    'employee_number' => $employee->employee_number,
                    'reason' => $e->reason,
                    'message' => $e->localizedMessage(),
                ];
            }
        }

        return ['assigned' => $assigned, 'failed' => $failed];
    }

    /**
     * Validates the employee-specific part of an assignment and returns the columns to store.
     *
     * @return array{rotation_offset: ?int, working_days: ?string, rest_days: ?string}
     */
    public static function assignmentOverrides(Shift $shift, array $data): array
    {
        $offset = $data['rotation_offset'] ?? null;
        $working = isset($data['working_days']) ? array_map('intval', (array) $data['working_days']) : null;
        $rest = isset($data['rest_days']) ? array_map('intval', (array) $data['rest_days']) : null;

        if ($shift->isRotating()) {
            if ($working !== null || $rest !== null) {
                throw new HrException('shift_override_not_allowed', 'working_days');
            }
            $length = $shift->cycleLength();
            $offset = (int) ($offset ?? 0);
            if ($offset < 0 || $offset >= $length) {
                throw new HrException('rotation_offset_invalid', 'rotation_offset', 422, ['max' => $length]);
            }

            return ['rotation_offset' => $offset, 'working_days' => null, 'rest_days' => null];
        }

        if ($offset !== null && (int) $offset !== 0) {
            throw new HrException('shift_override_not_allowed', 'rotation_offset');
        }
        if ($working === null && $rest === null) {
            return ['rotation_offset' => null, 'working_days' => null, 'rest_days' => null];
        }

        $effectiveWorking = $working ?? $shift->working_days_list;
        $effectiveRest = $rest ?? array_values(array_diff($shift->rest_days_list, $effectiveWorking));
        self::assertDays($effectiveWorking, $effectiveRest);

        return [
            'rotation_offset' => null,
            'working_days' => $working !== null ? Shift::encodeDays($working) : null,
            'rest_days' => $rest !== null ? Shift::encodeDays($rest) : null,
        ];
    }

    /** A branch shift can only be worked by employees of that branch; center-wide shifts fit everyone. */
    public static function assertFitsBranch(Shift $shift, ?int $branchId, string $field = 'shift_id'): void
    {
        if ($shift->branch_id !== null && (int) $shift->branch_id !== (int) $branchId) {
            throw new HrException('shift_branch_mismatch', $field);
        }
    }

    public static function branchOn(Employee $employee, CarbonInterface $date): ?int
    {
        $branchId = EmployeeWorkAssignment::query()
            ->where('employee_id', $employee->id)
            ->effectiveOn($date)
            ->value('branch_id') ?? $employee->branch_id;

        return $branchId !== null ? (int) $branchId : null;
    }

    /** Default shift a branch gives new hires: active, fixed, and usable in that branch. */
    public static function assertBranchDefault(int $centerId, ?int $shiftId, ?int $branchId): void
    {
        if ($shiftId === null) {
            return;
        }
        $shift = HrEmployeeService::findShift($centerId, $shiftId);
        if ($shift->isRotating()) {
            throw new HrException('shift_rotating_not_allowed', 'default_shift_id');
        }
        self::assertFitsBranch($shift, $branchId, 'default_shift_id');
    }

    public static function branchDefaultShift(int $centerId, ?int $branchId): ?Shift
    {
        if ($branchId === null) {
            return null;
        }
        $shiftId = Branch::query()->forCenter($centerId)->whereKey($branchId)->value('default_shift_id');
        if ($shiftId === null) {
            return null;
        }
        $shift = Shift::query()->forCenter($centerId)->whereKey($shiftId)->where('is_active', true)->first();

        return $shift !== null && ! $shift->isRotating() ? $shift : null;
    }

    /**
     * Reason the shift cannot be deactivated or deleted, or null when it is free.
     */
    public static function blockingReason(Shift $shift): ?string
    {
        $today = today()->toDateString();

        $assigned = $shift->assignments()
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today))
            ->whereHas('employee', fn ($q) => $q->where('employment_status', '!=', Employee::STATUS_TERMINATED))
            ->exists();
        if ($assigned) {
            return 'shift_in_use';
        }
        if (self::rotationsUsing($shift, true)->isNotEmpty()) {
            return 'shift_used_in_rotation';
        }
        if (Branch::query()->forCenter($shift->center_id)->where('default_shift_id', $shift->id)->exists()) {
            return 'shift_is_branch_default';
        }
        if (ShiftException::query()->where('shift_id', $shift->id)->where('date_to', '>=', $today)->exists()) {
            return 'shift_in_use';
        }

        return null;
    }

    /** Whether any schedule (past or present) depends on the shift's timing. */
    public static function hasHistory(Shift $shift): bool
    {
        if (! $shift->exists) {
            return false;
        }

        return $shift->assignments()->exists()
            || ShiftException::withTrashed()->where('shift_id', $shift->id)->exists()
            || Branch::query()->forCenter($shift->center_id)->where('default_shift_id', $shift->id)->exists()
            || self::rotationsUsing($shift, false)->isNotEmpty();
    }

    /**
     * Rotating shifts whose cycle contains $shift (only versions in force today or later when $currentOnly).
     *
     * @return Collection<int, int> rotating shift ids
     */
    public static function rotationsUsing(Shift $shift, bool $currentOnly): Collection
    {
        $rotatingIds = Shift::query()->forCenter($shift->center_id)
            ->where('shift_type', Shift::TYPE_ROTATING)
            ->whereKeyNot($shift->id)
            ->pluck('id');
        if ($rotatingIds->isEmpty()) {
            return collect();
        }

        return ShiftVersion::query()
            ->whereIn('shift_id', $rotatingIds)
            ->when($currentOnly, fn ($q) => $q->where(fn ($inner) => $inner->whereNull('effective_to')->orWhere('effective_to', '>=', today()->toDateString())))
            ->get(['shift_id', 'rotation_pattern'])
            ->filter(fn (ShiftVersion $version) => in_array($shift->id, array_map('intval', array_filter($version->rotation_pattern ?? [], fn ($slot) => $slot !== null)), true))
            ->pluck('shift_id')
            ->unique()
            ->values();
    }

    /**
     * Merged and validated timing for $shift (with its new type) as storable column values.
     */
    private static function timing(Shift $shift, array $data): array
    {
        $values = $shift->exists ? $shift->timingAttributes() : [];
        foreach (Shift::TIMING_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $values[$field] = $data[$field];
            }
        }
        $values = Shift::normalizeTiming($values);

        if ($shift->isRotating()) {
            $pattern = $values['rotation_pattern'] ?? [];
            self::assertRotation($shift, $pattern);

            return [
                'start_time' => '00:00', 'end_time' => '00:00', 'break_start_time' => null, 'break_minutes' => 0,
                'expected_minutes' => null, 'grace_minutes' => null, 'early_leave_grace_minutes' => null,
                'working_days' => null, 'rest_days' => null, 'rotation_pattern' => $pattern,
            ];
        }

        if ($values['start_time'] === null || $values['end_time'] === null) {
            throw new HrException('shift_hours_required', 'start_time');
        }
        if ($values['start_time'] === $values['end_time']) {
            throw new HrException('shift_hours_equal', 'end_time');
        }
        $errors = HrShiftMath::timingErrors($values['start_time'], $values['end_time'], $values['break_minutes'], $values['break_start_time'], $values['expected_minutes']);
        if ($errors !== []) {
            $field = array_key_first($errors);
            throw new HrException($errors[$field], $field);
        }

        $working = Shift::decodeDays($values['working_days']);
        $workingChanged = ! $shift->exists || array_key_exists('working_days', $data);
        if (array_key_exists('rest_days', $data) || ! $workingChanged) {
            $rest = Shift::decodeDays($values['rest_days']);
        } else {
            $rest = self::defaultRestDays($shift->center_id, $working);
        }
        self::assertDays($working, $rest);

        $values['working_days'] = Shift::encodeDays($working);
        $values['rest_days'] = Shift::encodeDays($rest);
        $values['rotation_pattern'] = null;

        return $values;
    }

    /** Weekend days not worked; when the shift works the whole weekend, every day it does not work. */
    private static function defaultRestDays(int $centerId, array $working): array
    {
        $weekend = array_map('intval', (array) HrSettings::get($centerId, 'general.weekend_days'));
        $rest = array_values(array_diff($weekend, $working));

        return $rest !== [] ? $rest : array_values(array_diff(range(0, 6), $working));
    }

    /** Saudi Labor Law Article 104: every worker has at least one weekly rest day. */
    private static function assertDays(array $working, array $rest): void
    {
        if ($working === []) {
            throw new HrException('shift_working_days_required', 'working_days');
        }
        if (array_intersect($working, $rest) !== []) {
            throw new HrException('shift_days_overlap', 'rest_days');
        }
        if ($rest === []) {
            throw new HrException('shift_rest_day_required', 'rest_days');
        }
    }

    /**
     * Cycle of 1-56 days of fixed shifts (same branch or center-wide) with at least one rest day per week.
     *
     * @param  array<int, int|null>  $pattern
     */
    private static function assertRotation(Shift $shift, array $pattern): void
    {
        $length = count($pattern);
        $slots = array_values(array_unique(array_filter($pattern, fn ($slot) => $slot !== null)));
        if ($length < 1 || $length > Shift::MAX_CYCLE_DAYS || $slots === []) {
            throw new HrException('shift_rotation_invalid', 'rotation_pattern', 422, ['max' => Shift::MAX_CYCLE_DAYS]);
        }
        $restSlots = $length - count(array_filter($pattern, fn ($slot) => $slot !== null));
        if ($restSlots < max(1, intdiv($length, 7))) {
            throw new HrException('shift_rest_day_required', 'rotation_pattern');
        }

        $found = Shift::query()->forCenter($shift->center_id)->whereIn('id', $slots)->get()->keyBy('id');
        foreach ($slots as $slot) {
            $slotShift = $found->get($slot);
            if ($slotShift === null || $slotShift->isRotating() || ($shift->exists && $slotShift->id === $shift->id)) {
                throw new HrException('shift_rotation_invalid', 'rotation_pattern', 422, ['max' => Shift::MAX_CYCLE_DAYS]);
            }
            if (! $slotShift->is_active) {
                throw new HrException('shift_rotation_slot_inactive', 'rotation_pattern');
            }
            if ($slotShift->branch_id !== null && (int) $slotShift->branch_id !== (int) $shift->branch_id) {
                throw new HrException('shift_branch_mismatch', 'rotation_pattern');
            }
        }
    }

    /** Moving a shift to a branch is refused while employees of other branches still work it. */
    private static function assertBranchChangeAllowed(Shift $shift, mixed $branchId): void
    {
        if ($branchId === null) {
            return;
        }
        $elsewhere = $shift->assignments()
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', today()->toDateString()))
            ->whereHas('employee', fn ($q) => $q->employed()->where(fn ($inner) => $inner->whereNull('branch_id')->orWhere('branch_id', '!=', (int) $branchId)))
            ->exists();
        if ($elsewhere) {
            throw new HrException('shift_branch_mismatch', 'branch_id');
        }
    }

    private static function appendVersion(Shift $shift, array $timing, string $from, ?string $reason, User $actor): ShiftVersion
    {
        Shift::query()->whereKey($shift->id)->lockForUpdate()->first();
        $date = Carbon::parse($from)->startOfDay();

        /** @var ShiftVersion|null $latest */
        $latest = $shift->versions()->orderByDesc('effective_from')->first();
        if ($latest !== null && $latest->effective_from->isSameDay($date)) {
            // Same-day correction of a version that is not locked yet.
            HrEffectiveDating::assertNotLocked($shift->center_id, $date, 'effective_from');
            $latest->fill($timing + ['reason' => $reason ?? $latest->reason])->save();

            return $latest;
        }

        /** @var ShiftVersion $version */
        [$version] = HrEffectiveDating::appendRow($shift->center_id, $shift->versions(), $timing + [
            'effective_from' => $date->toDateString(),
            'reason' => $reason,
            'created_by' => $actor->id,
        ]);

        return $version;
    }

    private static function currentVersion(Shift $shift): ?ShiftVersion
    {
        return ($shift->current_version_id ? $shift->versions()->find($shift->current_version_id) : null)
            ?? $shift->versions()->orderByDesc('effective_from')->first();
    }
}
