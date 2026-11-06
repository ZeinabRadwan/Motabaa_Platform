<?php

namespace App\Support\Hr;

use App\Models\Hr\Employee;
use App\Models\Hr\EmploymentPeriod;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Deactivation closes the open employment period (the employee stays payroll-eligible for the days worked);
 * activation opens a new period after the last one. Periods are never edited after closing.
 */
final class HrEmploymentLifecycle
{
    public static function deactivate(Employee $employee, array $data, User $actor): Employee
    {
        return DB::transaction(function () use ($employee, $data, $actor) {
            $employee = Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $period = $employee->openEmploymentPeriod();
            if (! $employee->isEmployed() || $period === null) {
                throw new HrException('employee_already_inactive');
            }

            $end = Carbon::parse($data['end_date'])->startOfDay();
            if ($end->lt($period->start_date)) {
                throw new HrException('deactivation_before_start', 'end_date', 422, ['date' => $period->start_date->toDateString()]);
            }
            HrEffectiveDating::assertNotLocked($employee->center_id, $end, 'end_date');

            foreach ([$employee->salaries(), $employee->insurances(), $employee->shiftAssignments(), $employee->workAssignments(), $employee->attendanceConfigs()] as $relation) {
                if ($relation->where('effective_from', '>', $end->toDateString())->exists()) {
                    throw new HrException('scheduled_changes_after_end', 'end_date');
                }
            }

            $period->end_date = $end->toDateString();
            $period->end_reason = $data['termination_reason'];
            $period->save();

            $previousStatus = $employee->employment_status;
            $employee->employment_status = Employee::STATUS_TERMINATED;
            $employee->terminated_at = $end->toDateString();
            $employee->termination_reason = $data['termination_reason'];
            $employee->updated_by = $actor->id;
            $employee->save();

            HrEmployeeChangeLog::record($employee, 'terminated', $previousStatus, $data['termination_reason'], $end, $data['notes'] ?? null, $period);

            return $employee;
        });
    }

    public static function activate(Employee $employee, array $data, User $actor): Employee
    {
        return DB::transaction(function () use ($employee, $data, $actor) {
            $employee = Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            if ($employee->isEmployed()) {
                throw new HrException('employee_already_active');
            }

            $start = Carbon::parse($data['start_date'])->startOfDay();
            $last = $employee->latestEmploymentPeriod();
            if ($last?->end_date !== null && $start->lte($last->end_date)) {
                throw new HrException('activation_before_last_end', 'start_date', 422, ['date' => $last->end_date->toDateString()]);
            }
            HrEffectiveDating::assertNotLocked($employee->center_id, $start, 'start_date');

            $period = EmploymentPeriod::create([
                'center_id' => $employee->center_id,
                'employee_id' => $employee->id,
                'start_date' => $start->toDateString(),
                'created_by' => $actor->id,
            ]);

            $employee->employment_status = in_array($data['employment_status'] ?? null, Employee::EMPLOYED_STATUSES, true)
                ? $data['employment_status']
                : Employee::STATUS_ACTIVE;
            $employee->employment_start_date = $start->toDateString();
            $employee->terminated_at = null;
            $employee->termination_reason = null;
            $employee->updated_by = $actor->id;
            $employee->save();

            HrEmployeeChangeLog::record($employee, 'rehired', null, $start, $start, $data['reason'] ?? null, $period);

            return $employee;
        });
    }
}
