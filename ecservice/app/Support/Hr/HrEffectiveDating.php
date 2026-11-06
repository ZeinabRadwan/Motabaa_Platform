<?php

namespace App\Support\Hr;

use App\Models\Hr\Employee;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Rules shared by every effective-dated employee record (salary, insurance, shift):
 *  - rows are never edited; a new row starts after the latest one and closes it the day before;
 *  - nothing may take effect on or before payroll.changes_locked_until (closed payroll stays untouched);
 *  - nothing may take effect after the employee's end of service;
 *  - only the latest row, while it has not taken effect yet, can be cancelled (the previous row reopens).
 * Callers run inside a transaction holding a lock on the employee row.
 */
final class HrEffectiveDating
{
    public static function lockedUntil(int $centerId): ?Carbon
    {
        $value = HrSettings::get($centerId, 'payroll.changes_locked_until');

        return $value ? Carbon::parse($value)->startOfDay() : null;
    }

    public static function assertNotLocked(int $centerId, CarbonInterface $date, string $field): void
    {
        $locked = self::lockedUntil($centerId);
        if ($locked !== null && $date->lte($locked)) {
            throw new HrException('effective_date_locked', $field, 422, ['date' => $locked->toDateString()]);
        }
    }

    public static function assertWithinEmployment(Employee $employee, CarbonInterface $date, string $field): void
    {
        $period = $employee->latestEmploymentPeriod();
        if ($period?->end_date !== null && $date->gt($period->end_date)) {
            throw new HrException('effective_date_after_termination', $field, 422, ['date' => $period->end_date->toDateString()]);
        }
    }

    /**
     * Pure ordering check, kept separate so it can be unit tested.
     */
    public static function orderingError(?CarbonInterface $latestFrom, CarbonInterface $newFrom): ?string
    {
        return $latestFrom !== null && $newFrom->lte($latestFrom) ? 'effective_date_not_after_latest' : null;
    }

    /**
     * Appends a row to an effective-dated relation and closes the previous one.
     *
     * @return array{0: Model, 1: ?Model} [new row, previous row]
     */
    public static function append(Employee $employee, HasMany $relation, array $attributes, string $field = 'effective_from'): array
    {
        $from = Carbon::parse($attributes['effective_from'])->startOfDay();

        self::assertNotLocked($employee->center_id, $from, $field);
        self::assertWithinEmployment($employee, $from, $field);

        return self::appendRow($employee->center_id, $relation, $attributes, $field);
    }

    /**
     * Same as append() for records not owned by an employee (e.g. shift definition versions).
     *
     * @return array{0: Model, 1: ?Model} [new row, previous row]
     */
    public static function appendRow(int $centerId, HasMany $relation, array $attributes, string $field = 'effective_from'): array
    {
        $from = Carbon::parse($attributes['effective_from'])->startOfDay();

        self::assertNotLocked($centerId, $from, $field);

        /** @var Model|null $previous */
        $previous = (clone $relation)->orderByDesc('effective_from')->first();
        $error = self::orderingError($previous?->effective_from, $from);
        if ($error !== null) {
            throw new HrException($error, $field, 422, ['date' => $previous->effective_from->toDateString()]);
        }

        if ($previous !== null) {
            $previous->effective_to = $from->copy()->subDay();
            $previous->save();
        }

        $row = $relation->create(array_merge($attributes, [
            'center_id' => $centerId,
            'effective_from' => $from->toDateString(),
            'effective_to' => null,
        ]));

        return [$row, $previous];
    }

    /**
     * Removes a scheduled latest row and reopens the previous one.
     *
     * @return ?Model the reopened previous row
     */
    public static function cancel(Employee $employee, HasMany $relation, Model $row): ?Model
    {
        return self::cancelRow($employee->center_id, $relation, $row);
    }

    public static function cancelRow(int $centerId, HasMany $relation, Model $row): ?Model
    {
        $latest = (clone $relation)->orderByDesc('effective_from')->first();
        if ($latest === null || ! $latest->is($row) || ! $row->effective_from->gt(today())) {
            throw new HrException('change_not_cancellable');
        }
        self::assertNotLocked($centerId, $row->effective_from, 'error');

        $previous = (clone $relation)->where('effective_from', '<', $row->effective_from->toDateString())
            ->orderByDesc('effective_from')
            ->first();

        $row->delete();

        if ($previous !== null) {
            $previous->effective_to = null;
            $previous->save();
        }

        return $previous;
    }
}
