<?php

namespace App\Support\Hr;

use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeChange;
use App\Models\Hr\EmployeeInsurance;
use App\Models\Hr\EmployeeSalary;
use App\Models\Hr\EmployeeShiftAssignment;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Salary, insurance and shift changes. Each change is a new effective-dated row (see HrEffectiveDating)
 * plus timeline entries; nothing here calculates pay.
 */
final class HrEmployeeRecords
{
    public static function addSalary(Employee $employee, array $data, User $actor): EmployeeSalary
    {
        return DB::transaction(function () use ($employee, $data, $actor) {
            $employee = self::lock($employee);

            /** @var EmployeeSalary $row */
            [$row, $previous] = HrEffectiveDating::append($employee, $employee->salaries(), [
                'effective_from' => $data['effective_from'],
                'basic_salary' => $data['basic_salary'],
                'housing_allowance' => $data['housing_allowance'] ?? 0,
                'transportation_allowance' => $data['transportation_allowance'] ?? 0,
                'other_allowances' => $data['other_allowances'] ?? 0,
                'other_allowances_note' => $data['other_allowances_note'] ?? null,
                'currency' => (string) HrSettings::get($employee->center_id, 'payroll.currency'),
                'reason' => $data['reason'] ?? null,
                'created_by' => $actor->id,
            ]);

            $basic = fn (?EmployeeSalary $salary) => $salary?->basic_salary;
            $allowances = fn (?EmployeeSalary $salary) => $salary ? $salary->only(EmployeeSalary::ALLOWANCE_FIELDS) : null;

            if ($previous === null || (string) $basic($previous) !== (string) $basic($row)) {
                HrEmployeeChangeLog::record($employee, 'salary', $basic($previous), $basic($row), $row->effective_from, $row->reason, $row);
            }
            if ($previous === null || $allowances($previous) != $allowances($row)) {
                HrEmployeeChangeLog::record($employee, 'allowances', $allowances($previous), $allowances($row), $row->effective_from, $row->reason, $row);
            }

            return $row;
        });
    }

    public static function cancelSalary(Employee $employee, EmployeeSalary $salary): void
    {
        DB::transaction(function () use ($employee, $salary) {
            $employee = self::lock($employee);
            HrEffectiveDating::cancel($employee, $employee->salaries(), $salary);
            HrEmployeeChangeLog::record($employee, 'salary', $salary->basic_salary, null, $salary->effective_from, null, $salary, [], EmployeeChange::ACTION_CANCELLED);
        });
    }

    public static function addInsurance(Employee $employee, array $data, User $actor): EmployeeInsurance
    {
        if (! EmployeeInsurance::categoryMatchesNationality($data['category'], $employee->user?->nationality)) {
            throw new HrException('insurance_category_nationality', 'category');
        }

        return DB::transaction(function () use ($employee, $data, $actor) {
            $employee = self::lock($employee);

            /** @var EmployeeInsurance $row */
            [$row, $previous] = HrEffectiveDating::append($employee, $employee->insurances(), [
                'effective_from' => $data['effective_from'],
                'category' => $data['category'],
                'employee_rate' => $data['employee_rate'],
                'employer_rate' => $data['employer_rate'],
                'contribution_start_date' => $data['contribution_start_date'] ?? null,
                'subscription_number' => $data['subscription_number'] ?? null,
                'reason' => $data['reason'] ?? null,
                'created_by' => $actor->id,
            ]);

            $summary = fn (?EmployeeInsurance $insurance) => $insurance
                ? $insurance->only(['category', 'employee_rate', 'employer_rate'])
                : null;
            HrEmployeeChangeLog::record($employee, 'insurance', $summary($previous), $summary($row), $row->effective_from, $row->reason, $row);

            return $row;
        });
    }

    public static function cancelInsurance(Employee $employee, EmployeeInsurance $insurance): void
    {
        DB::transaction(function () use ($employee, $insurance) {
            $employee = self::lock($employee);
            HrEffectiveDating::cancel($employee, $employee->insurances(), $insurance);
            HrEmployeeChangeLog::record($employee, 'insurance', $insurance->only(['category', 'employee_rate', 'employer_rate']), null, $insurance->effective_from, null, $insurance, [], EmployeeChange::ACTION_CANCELLED);
        });
    }

    public static function assignShift(Employee $employee, array $data, User $actor): EmployeeShiftAssignment
    {
        $shift = HrEmployeeService::findShift($employee->center_id, (int) $data['shift_id']);
        $overrides = HrShiftService::assignmentOverrides($shift, $data);
        $from = Carbon::parse($data['effective_from'])->startOfDay();

        return DB::transaction(function () use ($employee, $data, $actor, $shift, $overrides, $from) {
            $employee = self::lock($employee);
            HrShiftService::assertFitsBranch($shift, HrShiftService::branchOn($employee, $from));

            /** @var EmployeeShiftAssignment|null $latest */
            $latest = $employee->shiftAssignments()->orderByDesc('effective_from')->first();
            if ($latest !== null && (int) $latest->shift_id === $shift->id
                && Arr::only($latest->getAttributes(), array_keys($overrides)) == $overrides) {
                throw new HrException('shift_assignment_unchanged', 'shift_id');
            }

            if ($latest !== null && $latest->effective_from->isSameDay($from)) {
                // Same-day correction of an assignment that is not locked yet.
                HrEffectiveDating::assertNotLocked($employee->center_id, $from, 'effective_from');
                HrEffectiveDating::assertWithinEmployment($employee, $from, 'effective_from');
                $previous = $latest->replicate();
                $latest->fill($overrides + [
                    'shift_id' => $shift->id,
                    'reason' => $data['reason'] ?? $latest->reason,
                ])->save();
                $row = $latest;
            } else {
                /** @var EmployeeShiftAssignment $row */
                [$row, $previous] = HrEffectiveDating::append($employee, $employee->shiftAssignments(), $overrides + [
                    'effective_from' => $from->toDateString(),
                    'shift_id' => $shift->id,
                    'reason' => $data['reason'] ?? null,
                    'created_by' => $actor->id,
                ]);
            }

            HrEmployeeChangeLog::record($employee, 'shift', $previous?->shift_id, $shift->id, $row->effective_from, $row->reason, $row, [
                'old' => HrEmployeeChangeLog::snapshot($previous?->shift),
                'new' => HrEmployeeChangeLog::snapshot($shift),
            ]);

            return $row;
        });
    }

    public static function cancelShift(Employee $employee, EmployeeShiftAssignment $assignment): void
    {
        DB::transaction(function () use ($employee, $assignment) {
            $employee = self::lock($employee);
            HrEffectiveDating::cancel($employee, $employee->shiftAssignments(), $assignment);
            HrEmployeeChangeLog::record($employee, 'shift', $assignment->shift_id, null, $assignment->effective_from, null, $assignment, [
                'old' => HrEmployeeChangeLog::snapshot($assignment->shift),
            ], EmployeeChange::ACTION_CANCELLED);
        });
    }

    private static function lock(Employee $employee): Employee
    {
        return Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
    }
}
