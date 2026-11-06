<?php

namespace App\Support\Hr;

use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeWorkAssignment;
use App\Models\Hr\Holiday;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Holidays applicable to employees on given dates. A holiday for one branch applies to the employees working in
 * that branch on the date (their work assignment in force that day, else their current branch).
 */
final class HrHolidays
{
    /** @return Collection<int, Holiday> */
    public static function between(int $centerId, CarbonInterface|string $from, CarbonInterface|string $to): Collection
    {
        return Holiday::query()
            ->forCenter($centerId)
            ->whereDate('date_from', '<=', Carbon::parse($to)->toDateString())
            ->whereDate('date_to', '>=', Carbon::parse($from)->toDateString())
            ->orderBy('date_from')
            ->orderBy('id')
            ->get();
    }

    /** The holiday of $date for an employee of $branchId: a branch holiday wins over a center-wide one. */
    public static function on(Collection $holidays, string $date, ?int $branchId): ?Holiday
    {
        $best = null;
        foreach ($holidays as $holiday) {
            if (! $holiday->covers($date) || ! $holiday->appliesToBranch($branchId)) {
                continue;
            }
            if ($best === null || ($best->branch_id === null && $holiday->branch_id !== null)) {
                $best = $holiday;
            }
        }

        return $best;
    }

    /**
     * Branch of each employee on each date of the range.
     *
     * @param  Collection<int, Employee>  $employees
     * @return array<int, array<string, ?int>> employee id => date => branch id
     */
    public static function branchesOn(Collection $employees, CarbonInterface|string $from, CarbonInterface|string $to): array
    {
        $first = Carbon::parse($from)->startOfDay();
        $last = Carbon::parse($to)->startOfDay();
        $assignments = EmployeeWorkAssignment::query()
            ->whereIn('employee_id', $employees->pluck('id')->all())
            ->whereDate('effective_from', '<=', $last->toDateString())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $first->toDateString()))
            ->orderBy('effective_from')
            ->get()
            ->groupBy('employee_id');

        $branches = [];
        foreach ($employees as $employee) {
            $rows = $assignments->get($employee->id, collect());
            for ($day = $first->copy(); $day->lte($last); $day->addDay()) {
                $date = $day->toDateString();
                $row = $rows->first(fn (EmployeeWorkAssignment $assignment) => $assignment->isEffectiveOn($date));
                $branchId = $row?->branch_id ?? $employee->branch_id;
                $branches[$employee->id][$date] = $branchId !== null ? (int) $branchId : null;
            }
        }

        return $branches;
    }
}
