<?php

namespace App\Support\Hr;

use App\Models\Hr\Branch;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeChange;
use App\Models\Hr\EmployeeWorkAssignment;
use App\Models\Hr\WorkLocation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Branch transfers and work location changes as effective-dated rows (rules in HrEffectiveDating), logged in
 * the employee timeline. hr_employees.branch_id / work_location_id mirror the row in force today; rows
 * scheduled for later are applied by hr:apply-work-assignments.
 */
final class HrWorkAssignments
{
    /** First row for a new employee; nothing precedes it, so no ordering or lock checks apply. */
    public static function open(Employee $employee, ?User $actor): ?EmployeeWorkAssignment
    {
        if ($employee->branch_id === null) {
            return null;
        }

        return $employee->workAssignments()->create([
            'center_id' => $employee->center_id,
            'branch_id' => $employee->branch_id,
            'work_location_id' => $employee->work_location_id,
            'effective_from' => $employee->employment_start_date->toDateString(),
            'created_by' => $actor?->id,
        ]);
    }

    /**
     * @param  array{branch_id: int, work_location_id?: ?int, effective_from: string, reason?: ?string}  $data
     */
    public static function transfer(Employee $employee, array $data, ?User $actor): EmployeeWorkAssignment
    {
        $centerId = $employee->center_id;
        $branchId = (int) $data['branch_id'];
        $locationId = isset($data['work_location_id']) ? (int) $data['work_location_id'] : null;
        $from = Carbon::parse($data['effective_from'])->startOfDay();

        HrBranchScope::assertAllowed(HrBranchScope::branchIds($actor, $centerId), $branchId);
        [$branch, $location] = self::assignable($centerId, $branchId, $locationId);

        return DB::transaction(function () use ($employee, $data, $actor, $branch, $location, $from) {
            $employee = Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();

            if (! $location?->hasCoordinates() && HrAttendanceRules::on($employee, $from)->requiresGps()) {
                throw new HrException('gps_requires_location', 'work_location_id');
            }

            /** @var EmployeeWorkAssignment|null $latest */
            $latest = $employee->workAssignments()->orderByDesc('effective_from')->first();
            if ($latest !== null && (int) $latest->branch_id === $branch->id && (int) $latest->work_location_id === (int) $location?->id) {
                throw new HrException('assignment_unchanged', 'branch_id');
            }

            if ($latest !== null && $latest->effective_from->isSameDay($from)) {
                // Same-day correction of a row that is not locked yet (e.g. a wrong branch picked at hiring).
                HrEffectiveDating::assertNotLocked($employee->center_id, $from, 'effective_from');
                $previous = $latest->replicate();
                $latest->fill([
                    'branch_id' => $branch->id,
                    'work_location_id' => $location?->id,
                    'reason' => $data['reason'] ?? $latest->reason,
                ])->save();
                $row = $latest;
            } else {
                /** @var EmployeeWorkAssignment $row */
                [$row, $previous] = HrEffectiveDating::append($employee, $employee->workAssignments(), [
                    'effective_from' => $from->toDateString(),
                    'branch_id' => $branch->id,
                    'work_location_id' => $location?->id,
                    'reason' => $data['reason'] ?? null,
                    'created_by' => $actor?->id,
                ]);
            }

            self::log($employee, $previous, $row);

            if (! $row->effective_from->gt(today())) {
                self::applyToEmployee($employee, $row);
            }

            return $row;
        });
    }

    public static function cancel(Employee $employee, EmployeeWorkAssignment $assignment): void
    {
        DB::transaction(function () use ($employee, $assignment) {
            $employee = Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            HrEffectiveDating::cancel($employee, $employee->workAssignments(), $assignment);

            $date = $assignment->effective_from;
            HrEmployeeChangeLog::record($employee, 'branch', $assignment->branch_id, null, $date, null, $assignment, [
                'old' => HrEmployeeChangeLog::snapshot($assignment->branch),
            ], EmployeeChange::ACTION_CANCELLED);
            if ($assignment->work_location_id !== null) {
                HrEmployeeChangeLog::record($employee, 'work_location', $assignment->work_location_id, null, $date, null, $assignment, [
                    'old' => HrEmployeeChangeLog::snapshot($assignment->workLocation),
                ], EmployeeChange::ACTION_CANCELLED);
            }
        });
    }

    /**
     * Copies the assignment in force on $date into hr_employees for every employee whose cached columns differ.
     *
     * @return int number of employees updated
     */
    public static function applyDue(?int $centerId = null, ?CarbonInterface $date = null): int
    {
        $day = ($date ?? today())->toDateString();
        $table = (new EmployeeWorkAssignment())->getTable();

        $due = EmployeeWorkAssignment::query()
            ->effectiveOn($day)
            ->join('hr_employees as e', 'e.id', '=', "{$table}.employee_id")
            ->when($centerId !== null, fn ($query) => $query->where("{$table}.center_id", $centerId))
            ->where(function ($query) use ($table) {
                $query->whereNull('e.branch_id')
                    ->orWhereColumn('e.branch_id', '!=', "{$table}.branch_id")
                    ->orWhereRaw("COALESCE(e.work_location_id, 0) <> COALESCE({$table}.work_location_id, 0)");
            })
            ->select("{$table}.*")
            ->get();

        foreach ($due as $assignment) {
            DB::transaction(function () use ($assignment) {
                $employee = Employee::query()->whereKey($assignment->employee_id)->lockForUpdate()->first();
                if ($employee !== null) {
                    self::applyToEmployee($employee, $assignment);
                }
            });
        }

        return $due->count();
    }

    /**
     * Branch must be active; the work location (optional) must be active and belong to it.
     *
     * @return array{0: Branch, 1: ?WorkLocation}
     */
    public static function assignable(int $centerId, int $branchId, ?int $locationId): array
    {
        $branch = Branch::query()->forCenter($centerId)->whereKey($branchId)->first();
        if ($branch === null) {
            throw new HrException('reference_not_in_center', 'branch_id');
        }
        if (! $branch->is_active) {
            throw new HrException('reference_inactive', 'branch_id');
        }

        if ($locationId === null) {
            return [$branch, null];
        }

        $location = WorkLocation::query()->forCenter($centerId)->whereKey($locationId)->first();
        if ($location === null) {
            throw new HrException('reference_not_in_center', 'work_location_id');
        }
        if ((int) $location->branch_id !== $branch->id) {
            throw new HrException('work_location_branch_mismatch', 'work_location_id');
        }
        if (! $location->is_active) {
            throw new HrException('reference_inactive', 'work_location_id');
        }

        return [$branch, $location];
    }

    private static function applyToEmployee(Employee $employee, EmployeeWorkAssignment $assignment): void
    {
        $employee->branch_id = $assignment->branch_id;
        $employee->work_location_id = $assignment->work_location_id;
        if ($employee->isDirty()) {
            $employee->save();
        }
    }

    private static function log(Employee $employee, ?EmployeeWorkAssignment $previous, EmployeeWorkAssignment $row): void
    {
        $date = $row->effective_from;
        $reason = $row->reason;

        if ((int) $previous?->branch_id !== (int) $row->branch_id) {
            HrEmployeeChangeLog::record($employee, 'branch', $previous?->branch_id, $row->branch_id, $date, $reason, $row, [
                'old' => HrEmployeeChangeLog::snapshot($previous?->branch_id ? Branch::withTrashed()->find($previous->branch_id) : null),
                'new' => HrEmployeeChangeLog::snapshot(Branch::withTrashed()->find($row->branch_id)),
            ]);
        }
        if ((int) $previous?->work_location_id !== (int) $row->work_location_id) {
            HrEmployeeChangeLog::record($employee, 'work_location', $previous?->work_location_id, $row->work_location_id, $date, $reason, $row, [
                'old' => HrEmployeeChangeLog::snapshot($previous?->work_location_id ? WorkLocation::withTrashed()->find($previous->work_location_id) : null),
                'new' => HrEmployeeChangeLog::snapshot($row->work_location_id ? WorkLocation::withTrashed()->find($row->work_location_id) : null),
            ]);
        }
    }
}
