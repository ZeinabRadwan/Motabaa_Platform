<?php

namespace App\Support\Hr;

use App\Models\Hr\AttendanceDay;
use App\Models\Hr\AttendancePeriod;
use App\Models\Hr\AttendancePeriodSummary;
use App\Models\Hr\Employee;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\LeaveType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Payroll attendance periods: the hand-over of attendance to payroll.
 *  - A period covers the whole center (every branch) or one branch; periods may not overlap when either covers
 *    the whole center or both cover the same branch, so a day can belong to one period only.
 *  - process: (re)processes the attendance of every employee employed during the period and rebuilds the
 *    per employee summaries (status processed).
 *  - approve (admin): once the period has ended and no exception is left unresolved, reprocesses, locks every
 *    attendance day of the period (attendance_period_id, locked_at) and rebuilds the summaries. Locked days are
 *    never reprocessed, corrected or re-entered, and leave can no longer be requested, approved or cancelled on them.
 *  - reopen (admin, with a reason) unlocks the days.
 *  - Approved additional working days are previewed by process, paid by approve and returned to approved (still
 *    reserved to the period) by reopen: see HrAdditionalWorkDays.
 *  - Payroll reads the summaries of approved periods only.
 * For a branch period, only the days the employee worked in that branch count (branch of the work assignment).
 */
final class HrAttendancePeriods
{
    /** Whether two periods may not coexist. Pure. */
    public static function conflicts(string $aStart, string $aEnd, ?int $aBranch, string $bStart, string $bEnd, ?int $bBranch): bool
    {
        $overlap = $aStart <= $bEnd && $bStart <= $aEnd;

        return $overlap && ($aBranch === null || $bBranch === null || $aBranch === $bBranch);
    }

    public static function assertNoConflict(int $centerId, string $start, string $end, ?int $branchId, ?int $ignoreId = null): void
    {
        $others = AttendancePeriod::query()
            ->forCenter($centerId)
            ->whereDate('period_start', '<=', $end)
            ->whereDate('period_end', '>=', $start)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get();

        foreach ($others as $other) {
            if (self::conflicts($start, $end, $branchId, $other->period_start->toDateString(), $other->period_end->toDateString(), $other->branch_id !== null ? (int) $other->branch_id : null)) {
                throw new HrException('attendance_period_overlaps', 'period_start', 422, [
                    'name' => $other->name,
                    'start' => $other->period_start->toDateString(),
                    'end' => $other->period_end->toDateString(),
                ], ['attendance_period_id' => $other->id]);
            }
        }
    }

    public static function assertDates(string $start, string $end): void
    {
        if ($end < $start) {
            throw new HrException('attendance_period_dates_invalid', 'period_end');
        }
        if (Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1 > AttendancePeriod::MAX_DAYS) {
            throw new HrException('attendance_period_too_long', 'period_end', 422, ['days' => AttendancePeriod::MAX_DAYS]);
        }
    }

    /** @return Collection<int, Employee> employees employed at least one day of the period (in its branch) */
    public static function employees(AttendancePeriod $period): Collection
    {
        $start = $period->period_start->toDateString();
        $end = $period->period_end->toDateString();

        return Employee::withTrashed()
            ->forCenter($period->center_id)
            ->where(fn (Builder $query) => $query
                ->eligibleForPayrollPeriod($start, $end)
                ->orWhere(fn (Builder $q) => $q->doesntHave('employmentPeriods')
                    ->where(fn (Builder $date) => $date->whereNull('employment_start_date')->orWhereDate('employment_start_date', '<=', $end))))
            ->when($period->branch_id !== null, fn (Builder $query) => $query->where(fn (Builder $q) => $q
                ->where('branch_id', $period->branch_id)
                ->orWhereHas('workAssignments', fn (Builder $assignment) => $assignment
                    ->where('branch_id', $period->branch_id)
                    ->whereDate('effective_from', '<=', $end)
                    ->where(fn (Builder $to) => $to->whereNull('effective_to')->orWhereDate('effective_to', '>=', $start)))))
            ->with(['employmentPeriods', 'user:id,name'])
            ->orderBy('id')
            ->get();
    }

    public static function process(AttendancePeriod $period, User $actor): AttendancePeriod
    {
        return DB::transaction(function () use ($period, $actor) {
            $period = self::lock($period);
            if ($period->isApproved()) {
                throw new HrException('attendance_period_approved', 'error', 409);
            }

            $employees = self::employees($period);
            HrAttendanceProcessor::process($period->center_id, $employees, $period->period_start, $period->period_end);
            self::buildSummaries($period, $employees);
            $period->totals = array_merge($period->totals ?? [], HrAdditionalWorkDays::preview($period));

            $period->fill([
                'status' => AttendancePeriod::STATUS_PROCESSED,
                'processed_at' => now(),
                'processed_by' => $actor->id,
            ]);
            $period->save();

            return $period;
        });
    }

    public static function approve(AttendancePeriod $period, User $actor): AttendancePeriod
    {
        return DB::transaction(function () use ($period, $actor) {
            $period = self::lock($period);
            if ($period->isApproved()) {
                throw new HrException('attendance_period_approved', 'error', 409);
            }
            if ($period->status !== AttendancePeriod::STATUS_PROCESSED) {
                throw new HrException('attendance_period_not_processed', 'error', 409);
            }
            $today = now()->setTimezone(HrAttendanceProcessor::timezone($period->center_id))->toDateString();
            if ($period->period_end->toDateString() >= $today) {
                throw new HrException('attendance_period_not_ended', 'error', 409, ['date' => $period->period_end->toDateString()]);
            }

            $employees = self::employees($period);
            HrAttendanceProcessor::process($period->center_id, $employees, $period->period_start, $period->period_end);

            $days = self::daysQuery($period, $employees->pluck('id')->all());
            $unresolved = (clone $days)->where(fn (Builder $q) => $q
                ->where('review_status', AttendanceDay::REVIEW_PENDING)
                ->orWhere('status', AttendanceDay::STATUS_PENDING))
                ->count();
            if ($unresolved > 0) {
                throw new HrException('attendance_period_unresolved', 'error', 409, ['count' => $unresolved]);
            }

            (clone $days)->whereNull('locked_at')->update([
                'attendance_period_id' => $period->id,
                'locked_at' => now(),
            ]);
            self::buildSummaries($period, $employees);
            $period->totals = array_merge($period->totals ?? [], HrAdditionalWorkDays::settle($period));

            $period->fill([
                'status' => AttendancePeriod::STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by' => $actor->id,
            ]);
            $period->save();

            return $period;
        });
    }

    public static function reopen(AttendancePeriod $period, User $actor, string $reason): AttendancePeriod
    {
        return DB::transaction(function () use ($period, $actor, $reason) {
            $period = self::lock($period);
            if (! $period->isApproved()) {
                throw new HrException('attendance_period_not_approved', 'error', 409);
            }

            AttendanceDay::query()->where('attendance_period_id', $period->id)->update([
                'attendance_period_id' => null,
                'locked_at' => null,
            ]);
            HrAdditionalWorkDays::unsettle($period);
            $period->fill([
                'status' => AttendancePeriod::STATUS_PROCESSED,
                'approved_at' => null,
                'approved_by' => null,
                'reopened_at' => now(),
                'reopened_by' => $actor->id,
                'reopen_reason' => trim($reason),
            ]);
            $period->save();

            return $period;
        });
    }

    /**
     * Rebuilds the summaries of the period from its attendance days.
     *
     * @param  Collection<int, Employee>  $employees
     */
    public static function buildSummaries(AttendancePeriod $period, Collection $employees): void
    {
        $ids = $employees->pluck('id')->all();
        $days = self::daysQuery($period, $ids)->orderBy('attendance_date')->get()->groupBy('employee_id');
        $leaves = LeaveRequest::query()
            ->whereIn('employee_id', $ids)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $period->period_end->toDateString())
            ->whereDate('end_date', '>=', $period->period_start->toDateString())
            ->with('leaveType')
            ->orderBy('start_date')
            ->get()
            ->groupBy('employee_id');
        $types = LeaveType::withTrashed()->forCenter($period->center_id)->get()->keyBy('id');

        AttendancePeriodSummary::query()->where('attendance_period_id', $period->id)->whereNotIn('employee_id', $ids)->delete();

        $totals = array_fill_keys(['absence_days', 'late_minutes', 'early_departure_minutes', 'leave_days', 'unpaid_leave_days', 'exceptions_count', 'unresolved_exceptions'], 0);
        $count = 0;
        foreach ($employees as $employee) {
            $rows = $days->get($employee->id, collect());
            if ($rows->isEmpty()) {
                AttendancePeriodSummary::query()->where('attendance_period_id', $period->id)->where('employee_id', $employee->id)->delete();

                continue;
            }

            $values = self::summarize($rows, $types) + [
                'leave_requests' => ($leaves->get($employee->id) ?? collect())->map(fn (LeaveRequest $leave) => [
                    'id' => $leave->id,
                    'leave_type' => $leave->leaveType?->name,
                    'is_paid' => (bool) $leave->leaveType?->is_paid,
                    'start_date' => $leave->start_date->toDateString(),
                    'end_date' => $leave->end_date->toDateString(),
                    'days' => $leave->days,
                ])->values()->all(),
                'branch_id' => $period->branch_id ?? $employee->branch_id,
            ];

            AttendancePeriodSummary::query()->updateOrCreate(
                ['attendance_period_id' => $period->id, 'employee_id' => $employee->id],
                $values + ['center_id' => $period->center_id],
            );

            $count++;
            foreach (array_keys($totals) as $key) {
                $totals[$key] += $values[$key];
            }
        }

        $period->employees_count = $count;
        $period->totals = array_map(fn ($value) => is_float($value) ? round($value, 2) : $value, $totals);
    }

    /**
     * Totals of one employee's attendance days. Pure.
     *
     * @param  Collection<int, AttendanceDay>  $days
     * @param  Collection<int, LeaveType>  $types  keyed by id
     */
    public static function summarize(Collection $days, Collection $types): array
    {
        $values = array_fill_keys(AttendancePeriodSummary::COUNTERS, 0);
        $breakdown = [];
        $exceptions = [];

        foreach ($days as $day) {
            $status = $day->status;
            $present = in_array($status, AttendanceDay::PRESENT_STATUSES, true);

            if ($day->day_type === 'working') {
                $values['scheduled_days']++;
            }
            if ($present) {
                $values['present_days']++;
            }
            $values['absence_days'] += (float) $day->absence_days;
            if ($day->late_minutes > 0) {
                $values['late_days']++;
                $values['late_minutes'] += $day->late_minutes;
            }
            if ($day->early_departure_minutes > 0) {
                $values['early_departure_days']++;
                $values['early_departure_minutes'] += $day->early_departure_minutes;
            }
            if ($present || $status === AttendanceDay::STATUS_INCOMPLETE) {
                $values['worked_minutes'] += (int) $day->worked_minutes;
            }
            if (in_array($status, [...AttendanceDay::PRESENT_STATUSES, AttendanceDay::STATUS_ABSENT, AttendanceDay::STATUS_INCOMPLETE, AttendanceDay::STATUS_EXCUSED], true)) {
                $values['expected_minutes'] += (int) $day->expected_minutes;
            }

            match ($status) {
                AttendanceDay::STATUS_ON_LEAVE => self::countLeave($values, $breakdown, $day, $types),
                AttendanceDay::STATUS_HOLIDAY => $values['holiday_days']++,
                AttendanceDay::STATUS_REST_DAY, AttendanceDay::STATUS_DAY_OFF => $values['rest_days']++,
                AttendanceDay::STATUS_EXEMPT => $values['exempt_days']++,
                AttendanceDay::STATUS_EXCUSED => $values['excused_days']++,
                default => null,
            };

            if (! empty($day->exception_reasons)) {
                $values['exceptions_count']++;
                if ($day->review_status === AttendanceDay::REVIEW_PENDING) {
                    $values['unresolved_exceptions']++;
                }
                $exceptions[] = [
                    'date' => $day->attendance_date->toDateString(),
                    'reasons' => array_values($day->exception_reasons),
                    'status' => $status,
                    'review_status' => $day->review_status,
                    'resolution' => $day->override_reason,
                ];
            }
        }

        $values['absence_days'] = round($values['absence_days'], 2);
        $values['leave_breakdown'] = array_values($breakdown);
        $values['exception_details'] = $exceptions;

        return $values;
    }

    /** @return Builder<AttendanceDay> */
    private static function daysQuery(AttendancePeriod $period, array $employeeIds): Builder
    {
        return AttendanceDay::query()
            ->forCenter($period->center_id)
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('attendance_date', '>=', $period->period_start->toDateString())
            ->whereDate('attendance_date', '<=', $period->period_end->toDateString())
            ->where(fn (Builder $query) => $query->whereNull('attendance_period_id')->orWhere('attendance_period_id', $period->id))
            ->when($period->branch_id !== null, fn (Builder $query) => $query->where('branch_id', $period->branch_id));
    }

    private static function countLeave(array &$values, array &$breakdown, AttendanceDay $day, Collection $types): void
    {
        $values['leave_days']++;
        if ($day->leave_is_paid === false) {
            $values['unpaid_leave_days']++;
        } else {
            $values['paid_leave_days']++;
        }

        $key = (int) $day->leave_type_id;
        $type = $types->get($key);
        $breakdown[$key] ??= [
            'leave_type_id' => $key ?: null,
            'leave_type' => $type?->name,
            'code' => $type?->code,
            'is_paid' => $day->leave_is_paid !== false,
            'days' => 0,
        ];
        $breakdown[$key]['days']++;
    }

    private static function lock(AttendancePeriod $period): AttendancePeriod
    {
        return AttendancePeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();
    }
}
