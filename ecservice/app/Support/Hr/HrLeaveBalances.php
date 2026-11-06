<?php

namespace App\Support\Hr;

use App\Models\Hr\Employee;
use App\Models\Hr\LeaveBalance;
use App\Models\Hr\LeaveBalanceTransaction;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\LeaveType;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Leave balances per employee, leave type and calendar year, created on first use:
 *  - annual types: leave.annual_days (leave.annual_days_after_five_years once the employee has five years of
 *    service on 1 January), or the user's contract entitlement when higher, prorated in the year employment starts;
 *  - fixed types: days_per_year;
 *  - carry-over: the previous year's unused days, at most carry_over_max_days.
 * Pending requests reserve their days (pending_days); approval moves them to used_days; rejection or cancellation
 * releases them; cancelling approved leave restores them. Every change writes hr_leave_balance_transactions.
 * Callers that change a balance run inside a transaction and pass $lock = true.
 */
final class HrLeaveBalances
{
    public static function balanceFor(Employee $employee, LeaveType $type, int $year, bool $lock = false, ?User $actor = null): ?LeaveBalance
    {
        if (! $type->tracksBalance()) {
            return null;
        }

        $find = fn () => LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->where('year', $year)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();

        $balance = $find();
        if ($balance !== null) {
            return $balance;
        }

        $entitled = self::entitlementFor($type, $employee, $year);
        $carried = self::carryOverFor($type, $employee, $year);

        try {
            $balance = LeaveBalance::create([
                'center_id' => $employee->center_id,
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'year' => $year,
                'entitled_days' => $entitled,
                'carried_over_days' => $carried,
            ]);
        } catch (QueryException $e) {
            // Created concurrently: the unique (employee, type, year) index keeps one row.
            $balance = $find();
            if ($balance === null) {
                throw $e;
            }

            return $balance;
        }

        if ($entitled != 0.0) {
            self::log($balance, LeaveBalanceTransaction::KIND_ENTITLEMENT, $entitled, null, null, $actor);
        }
        if ($carried != 0.0) {
            self::log($balance, LeaveBalanceTransaction::KIND_CARRY_OVER, $carried, null, null, $actor);
        }

        return $balance;
    }

    /**
     * Balances of every active leave type with a balance, for one year.
     *
     * @return Collection<int, LeaveBalance>
     */
    public static function overview(Employee $employee, int $year): Collection
    {
        HrLeaveTypes::ensureDefaults($employee->center_id);

        return LeaveType::query()
            ->forCenter($employee->center_id)
            ->where('is_active', true)
            ->where('balance_mode', '!=', LeaveType::BALANCE_NONE)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (LeaveType $type) use ($employee, $year) {
                $balance = self::balanceFor($employee, $type, $year);
                $balance?->setRelation('leaveType', $type);

                return $balance;
            })
            ->filter()
            ->values();
    }

    public static function entitlementFor(LeaveType $type, Employee $employee, int $year): float
    {
        return match ($type->balance_mode) {
            LeaveType::BALANCE_ANNUAL => self::annualEntitlement(
                self::serviceStart($employee),
                $year,
                (float) HrSettings::get($employee->center_id, 'leave.annual_days'),
                (float) HrSettings::get($employee->center_id, 'leave.annual_days_after_five_years'),
                self::contractDays($employee),
            ),
            LeaveType::BALANCE_FIXED => round((float) $type->days_per_year, 2),
            default => 0.0,
        };
    }

    /**
     * Annual leave entitlement for a calendar year. Pure.
     *  - $afterFive once the employee has completed five years of service on 1 January of $year, else $base;
     *  - the contract entitlement when it is higher;
     *  - prorated by the days remaining in the year when service starts during the year; 0 before it starts.
     */
    public static function annualEntitlement(?string $serviceStart, int $year, float $base, float $afterFive, ?float $contractDays = null): float
    {
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd = $yearStart->copy()->endOfYear()->startOfDay();
        $start = $serviceStart !== null ? Carbon::parse($serviceStart)->startOfDay() : null;

        if ($start !== null && $start->gt($yearEnd)) {
            return 0.0;
        }

        $days = $start !== null && $start->copy()->addYears(5)->lte($yearStart) ? $afterFive : $base;
        if ($contractDays !== null && $contractDays > $days) {
            $days = $contractDays;
        }

        if ($start !== null && $start->gt($yearStart)) {
            $remaining = (int) $start->diffInDays($yearEnd) + 1;
            $days = $days * $remaining / $yearStart->daysInYear;
        }

        return round($days, 2);
    }

    public static function reserve(LeaveRequest $request, ?User $actor): void
    {
        self::change($request, ['pending_days' => $request->days], LeaveBalanceTransaction::KIND_RESERVE, $request->days, $actor);
    }

    public static function release(LeaveRequest $request, ?User $actor): void
    {
        self::change($request, ['pending_days' => -$request->days], LeaveBalanceTransaction::KIND_RELEASE, -$request->days, $actor);
    }

    /** A pending request was approved: its reserved days become used days. */
    public static function consume(LeaveRequest $request, ?User $actor): void
    {
        self::change($request, ['pending_days' => -$request->days, 'used_days' => $request->days], LeaveBalanceTransaction::KIND_USE, $request->days, $actor);
    }

    /** Leave recorded as already approved: used directly, without a reservation. */
    public static function useDirectly(LeaveRequest $request, ?User $actor): void
    {
        self::change($request, ['used_days' => $request->days], LeaveBalanceTransaction::KIND_USE, $request->days, $actor);
    }

    /** Approved leave was cancelled: its days return to the balance. */
    public static function restore(LeaveRequest $request, ?User $actor, ?string $reason = null): void
    {
        self::change($request, ['used_days' => -$request->days], LeaveBalanceTransaction::KIND_RESTORE, -$request->days, $actor, $reason);
    }

    /** Manual adjustment (+/-) with its reason; the total may not fall below the used and reserved days. */
    public static function adjust(LeaveBalance $balance, float $days, string $reason, User $actor): LeaveBalance
    {
        $balance = LeaveBalance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();
        $available = $balance->available();
        $balance->adjusted_days = round($balance->adjusted_days + $days, 2);
        if ($balance->available() < 0) {
            throw new HrException('leave_balance_negative', 'days', 422, ['available' => self::format($available)]);
        }
        $balance->save();
        self::log($balance, LeaveBalanceTransaction::KIND_ADJUSTMENT, $days, null, $reason, $actor);

        return $balance;
    }

    public static function format(float $days): string
    {
        return rtrim(rtrim(number_format($days, 2, '.', ''), '0'), '.');
    }

    private static function change(LeaveRequest $request, array $deltas, string $kind, float $days, ?User $actor, ?string $reason = null): void
    {
        $request->loadMissing(['leaveType', 'employee']);
        $balance = self::balanceFor($request->employee, $request->leaveType, (int) $request->balance_year, true, $actor);
        if ($balance === null) {
            return;
        }

        foreach ($deltas as $column => $delta) {
            $balance->{$column} = max(0, round($balance->{$column} + $delta, 2));
        }
        $balance->save();
        self::log($balance, $kind, $days, $request->id, $reason, $actor);
    }

    private static function log(LeaveBalance $balance, string $kind, float $days, ?int $requestId, ?string $reason, ?User $actor): void
    {
        LeaveBalanceTransaction::create([
            'center_id' => $balance->center_id,
            'leave_balance_id' => $balance->id,
            'employee_id' => $balance->employee_id,
            'leave_type_id' => $balance->leave_type_id,
            'leave_request_id' => $requestId,
            'kind' => $kind,
            'days' => round($days, 2),
            'reason' => $reason,
            'created_by' => $actor?->id,
        ]);
    }

    private static function carryOverFor(LeaveType $type, Employee $employee, int $year): float
    {
        if ($type->carry_over_max_days <= 0) {
            return 0.0;
        }

        $previous = LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->where('year', $year - 1)
            ->first();

        return $previous === null ? 0.0 : round(min(max(0, $previous->available()), $type->carry_over_max_days), 2);
    }

    private static function serviceStart(Employee $employee): ?string
    {
        $date = $employee->employment_start_date ?? $employee->contract_start_date;
        if ($date === null) {
            $hire = $employee->user?->getAttribute('hire_date');

            return $hire ? Carbon::parse($hire)->toDateString() : null;
        }

        return $date->toDateString();
    }

    private static function contractDays(Employee $employee): ?float
    {
        $value = $employee->user?->getAttribute('annual_leave_entitlement');

        return is_numeric($value) && (float) $value > 0 ? (float) $value : null;
    }
}
