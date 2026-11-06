<?php

namespace App\Support\Hr;

use App\Models\Hr\Employee;
use App\Models\Hr\ShiftException;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Temporary schedule changes. Exceptions are never edited: cancel one and record a new one. Nothing may
 * start on or before payroll.changes_locked_until, and two exceptions of the same scope cannot overlap.
 */
final class HrShiftExceptions
{
    public static function create(int $centerId, array $data, User $actor): ShiftException
    {
        $branchScope = HrBranchScope::branchIds($actor, $centerId);
        $employeeId = null;
        $branchId = null;
        $targetBranch = null;

        switch ($data['scope']) {
            case ShiftException::SCOPE_EMPLOYEE:
                $employee = Employee::query()->forCenter($centerId)->whereKey((int) $data['employee_id'])->first();
                if ($employee === null) {
                    throw new HrException('reference_not_in_center', 'employee_id');
                }
                HrBranchScope::assertAllowed($branchScope, $employee->branch_id, 'employee_id');
                $employeeId = $employee->id;
                $targetBranch = $employee->branch_id;
                break;
            case ShiftException::SCOPE_BRANCH:
                HrOrganizationService::assertBranchInCenter((int) $data['branch_id'], $centerId);
                HrBranchScope::assertAllowed($branchScope, (int) $data['branch_id']);
                $branchId = (int) $data['branch_id'];
                $targetBranch = $branchId;
                break;
            default:
                HrBranchScope::assertUnrestricted($branchScope);
        }

        $from = Carbon::parse($data['date_from'])->startOfDay();
        $to = Carbon::parse($data['date_to'])->startOfDay();
        if ($from->diffInDays($to) + 1 > ShiftException::MAX_DAYS) {
            throw new HrException('shift_exception_range_too_long', 'date_to', 422, ['days' => ShiftException::MAX_DAYS]);
        }
        HrEffectiveDating::assertNotLocked($centerId, $from, 'date_from');

        $attributes = ['shift_id' => null, 'start_time' => null, 'end_time' => null, 'break_minutes' => null];
        if ($data['exception_type'] === ShiftException::TYPE_SHIFT) {
            $shift = HrEmployeeService::findShift($centerId, (int) $data['shift_id']);
            if ($shift->isRotating()) {
                throw new HrException('shift_rotating_not_allowed', 'shift_id');
            }
            HrShiftService::assertFitsBranch($shift, $targetBranch);
            $attributes['shift_id'] = $shift->id;
        } elseif ($data['exception_type'] === ShiftException::TYPE_CUSTOM_HOURS) {
            $break = (int) ($data['break_minutes'] ?? 0);
            $errors = HrShiftMath::timingErrors($data['start_time'], $data['end_time'], $break, null, null);
            if ($errors !== []) {
                $field = array_key_first($errors);
                throw new HrException($errors[$field], $field);
            }
            $attributes['start_time'] = $data['start_time'];
            $attributes['end_time'] = $data['end_time'];
            $attributes['break_minutes'] = $break;
        }

        $overlaps = ShiftException::query()->forCenter($centerId)
            ->where(fn ($q) => $employeeId !== null ? $q->where('employee_id', $employeeId) : $q->whereNull('employee_id'))
            ->where(fn ($q) => $branchId !== null ? $q->where('branch_id', $branchId) : $q->whereNull('branch_id'))
            ->where('date_from', '<=', $to->toDateString().' 23:59:59')
            ->where('date_to', '>=', $from->toDateString())
            ->exists();
        if ($overlaps) {
            throw new HrException('shift_exception_overlap', 'date_from');
        }

        return ShiftException::create($attributes + [
            'center_id' => $centerId,
            'employee_id' => $employeeId,
            'branch_id' => $branchId,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'exception_type' => $data['exception_type'],
            'reason' => $data['reason'] ?? null,
            'created_by' => $actor->id,
        ]);
    }

    public static function cancel(ShiftException $exception): void
    {
        HrEffectiveDating::assertNotLocked($exception->center_id, $exception->date_from, 'error');
        $exception->delete();
    }
}
