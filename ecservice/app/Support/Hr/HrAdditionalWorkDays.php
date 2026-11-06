<?php

namespace App\Support\Hr;

use App\Models\Hr\AdditionalWorkDay;
use App\Models\Hr\ApprovalRequest;
use App\Models\Hr\ApprovalRequestStep;
use App\Models\Hr\AttendancePeriod;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeSalary;
use App\Models\User;
use App\Support\Hr\Approvals\ApprovalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Additional working days: whole or half days worked on a rest day, day off, holiday or unscheduled day. Payroll
 * pays days only, never overtime hours: amount = (gross salary on the work date / 30) × days.
 *  - Records are created as drafts (or submitted at once), edited while draft or pending (editing a pending record
 *    restarts its approval) and decided by the approval engine: the additional_work_day workflow when configured,
 *    else the direct manager then HR (apply_hr-additional-days). Requesters never approve their own records.
 *  - One active record per employee and date (duplicate_guard); rejected and cancelled records are kept as history
 *    and free the date again. Records are never deleted.
 *  - attendance_period_id is the payroll period covering the work date (kept in sync by relink()).
 *  - Settlement: approving a payroll period pays every approved, unpaid record it is eligible for (eligibleFor()):
 *    its own days plus the approved days carried forward from periods already approved without them. Paid records
 *    keep paid_period_id and a snapshot of the salary and amount; they can no longer be changed or cancelled.
 *    Reopening the period returns them to approved but keeps them reserved to that period, so the same days are
 *    never paid by two periods. Records without a salary on the work date are skipped and stay approved.
 */
final class HrAdditionalWorkDays
{
    public const HR_APPROVAL_PERMISSION = 'apply_hr-additional-days';

    /** Gross salary divisor of the daily rate (payroll month of 30 days). */
    public const SALARY_DIVISOR = 30;

    public const MAX_EMPLOYEES_PER_REQUEST = 50;

    /**
     * Pay of additional days: (gross / 30) × days, in cents to avoid float drift. Pure.
     *
     * @return array{gross_salary: string, daily_rate: string, amount: string}
     */
    public static function amount(string|float $grossSalary, float $days): array
    {
        $cents = (int) round(((float) $grossSalary) * 100);

        return [
            'gross_salary' => self::money($cents),
            'daily_rate' => self::money((int) round($cents / self::SALARY_DIVISOR)),
            'amount' => self::money((int) round($cents * $days / self::SALARY_DIVISOR)),
        ];
    }

    /**
     * Whether the payroll of a period pays an additional day. Pure.
     *  - only approved, unpaid records; a record already reserved to a period (paid_period_id, kept when that period
     *    is reopened) is paid by that period only;
     *  - the work date must not be after the period, and a branch period pays the days worked in its branch;
     *  - a day before the period start is carried forward only when its own period is approved (closed without it)
     *    or does not exist; while its own period is open, that period pays it.
     *
     * @param  array{status: string, work_date: string, branch_id: ?int, paid_period_id: ?int, own_period_id: ?int, own_period_status: ?string}  $record
     */
    public static function eligibleFor(array $record, int $periodId, string $periodStart, string $periodEnd, ?int $periodBranchId): bool
    {
        if ($record['status'] !== AdditionalWorkDay::STATUS_APPROVED) {
            return false;
        }
        if ($record['paid_period_id'] !== null) {
            return $record['paid_period_id'] === $periodId;
        }
        if ($record['work_date'] > $periodEnd) {
            return false;
        }
        if ($periodBranchId !== null && $record['branch_id'] !== $periodBranchId) {
            return false;
        }
        if ($record['work_date'] >= $periodStart) {
            return true;
        }

        return $record['own_period_id'] === null
            || $record['own_period_id'] === $periodId
            || $record['own_period_status'] === AttendancePeriod::STATUS_APPROVED;
    }

    /** Whether an approved day missed the payroll of its own period and waits for a later one. */
    public static function isCarriedForward(AdditionalWorkDay $record): bool
    {
        if ($record->isPaid()) {
            return $record->paidPeriod !== null && $record->paidPeriod->period_start->toDateString() > $record->work_date->toDateString();
        }

        return $record->status === AdditionalWorkDay::STATUS_APPROVED && $record->attendancePeriod?->isApproved() === true;
    }

    /**
     * The payroll period covering a date (for the employee's branch that day). Pure.
     *
     * @param  iterable<AttendancePeriod>  $periods
     */
    public static function periodFor(iterable $periods, string $date, ?int $branchId): ?AttendancePeriod
    {
        foreach ($periods as $period) {
            if ($period->period_start->toDateString() <= $date && $period->period_end->toDateString() >= $date
                && ($period->branch_id === null || (int) $period->branch_id === $branchId)) {
                return $period;
            }
        }

        return null;
    }

    /**
     * Adds the same additional day for one or more employees (all or nothing), as drafts or submitted at once.
     *
     * @param  Collection<int, Employee>  $employees
     * @return Collection<int, AdditionalWorkDay>
     *
     * @throws HrException
     */
    public static function create(Collection $employees, array $data, User $actor, bool $submit): Collection
    {
        return DB::transaction(function () use ($employees, $data, $actor, $submit) {
            $records = collect();
            foreach ($employees as $employee) {
                $employee = Employee::query()->whereKey($employee->id)->lockForUpdate()->with(['employmentPeriods', 'user:id,name'])->firstOrFail();
                $date = Carbon::parse($data['work_date'])->toDateString();
                $context = self::assertCanRecord($employee, $date, $actor);

                $record = AdditionalWorkDay::create([
                    'center_id' => $employee->center_id,
                    'employee_id' => $employee->id,
                    'branch_id' => $context['branch_id'],
                    'work_date' => $date,
                    'days' => (float) $data['days'],
                    'day_type' => $context['day_type'],
                    'reason' => trim((string) $data['reason']),
                    'status' => AdditionalWorkDay::STATUS_DRAFT,
                    'duplicate_guard' => $date,
                    'attendance_period_id' => self::resolvePeriodId($employee->center_id, $date, $context['branch_id']),
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
                $record->setRelation('employee', $employee);

                if ($submit) {
                    self::startApproval($record, $actor);
                }
                $records->push($record);
            }

            return $records;
        });
    }

    /**
     * Edits a draft or pending record. Changing a pending record cancels its approval request and submits a new one,
     * so approvers always decide the current values.
     *
     * @throws HrException
     */
    public static function update(AdditionalWorkDay $record, array $data, User $actor): AdditionalWorkDay
    {
        return DB::transaction(function () use ($record, $data, $actor) {
            $model = self::lock($record);
            if (! $model->isEditable()) {
                throw new HrException('additional_day_not_editable', 'error', 409);
            }
            $employee = Employee::withTrashed()->whereKey($model->employee_id)->lockForUpdate()->with(['employmentPeriods', 'user:id,name'])->firstOrFail();

            $date = isset($data['work_date']) ? Carbon::parse($data['work_date'])->toDateString() : $model->work_date->toDateString();
            $context = self::assertCanRecord($employee, $date, $actor, $model->id);

            $model->fill([
                'work_date' => $date,
                'duplicate_guard' => $date,
                'days' => array_key_exists('days', $data) ? (float) $data['days'] : $model->days,
                'reason' => array_key_exists('reason', $data) ? trim((string) $data['reason']) : $model->reason,
                'branch_id' => $context['branch_id'],
                'day_type' => $context['day_type'],
                'attendance_period_id' => self::resolvePeriodId($model->center_id, $date, $context['branch_id']),
            ]);
            if (! $model->isDirty(['work_date', 'days', 'reason'])) {
                return $model->setRelation('employee', $employee);
            }
            $model->updated_by = $actor->id;

            $previous = $model->status === AdditionalWorkDay::STATUS_PENDING && $model->approval_request_id
                ? ApprovalRequest::query()->find($model->approval_request_id)
                : null;
            if ($previous !== null) {
                // Detached first so the cancellation below does not resolve (cancel) the record itself.
                $model->approval_request_id = null;
            }
            $model->save();
            $model->setRelation('employee', $employee);

            if ($previous !== null) {
                if ($previous->isPending()) {
                    ApprovalService::forceCancel($previous, $actor, __('validation.hr.additional_day_resubmitted'));
                }
                self::startApproval($model, $actor);
            }

            return $model;
        });
    }

    /** @throws HrException */
    public static function submit(AdditionalWorkDay $record, User $actor): AdditionalWorkDay
    {
        return DB::transaction(function () use ($record, $actor) {
            $model = self::lock($record);
            if ($model->status !== AdditionalWorkDay::STATUS_DRAFT) {
                throw new HrException('additional_day_not_submittable', 'error', 409);
            }
            $employee = Employee::withTrashed()->whereKey($model->employee_id)->with(['employmentPeriods', 'user:id,name'])->firstOrFail();
            self::assertCanRecord($employee, $model->work_date->toDateString(), $actor, $model->id);
            $model->setRelation('employee', $employee);
            self::startApproval($model, $actor);

            return $model;
        });
    }

    /** Called by the approval engine, inside its transaction, once the approval request is resolved. */
    public static function approvalResolved(AdditionalWorkDay $record, ApprovalRequest $approval): void
    {
        $model = self::lock($record);
        if ($model->status !== AdditionalWorkDay::STATUS_PENDING || (int) $model->approval_request_id !== (int) $approval->id) {
            return;
        }

        $actor = auth()->user();
        $deciding = $approval->steps->whereNotNull('acted_by')->sortByDesc('step_order')->first();
        $deciderId = $deciding?->acted_by ?? $actor?->id;

        switch ($approval->status) {
            case ApprovalRequest::STATUS_APPROVED:
                $model->fill(['status' => AdditionalWorkDay::STATUS_APPROVED, 'approved_at' => now(), 'approved_by' => $deciderId]);
                break;
            case ApprovalRequest::STATUS_REJECTED:
                $rejecting = $approval->steps->firstWhere('status', ApprovalRequestStep::STATUS_REJECTED) ?? $deciding;
                $model->fill([
                    'status' => AdditionalWorkDay::STATUS_REJECTED,
                    'rejected_at' => now(),
                    'rejected_by' => $rejecting?->acted_by ?? $deciderId,
                    'rejection_reason' => $rejecting?->comment,
                    'duplicate_guard' => null,
                ]);
                break;
            case ApprovalRequest::STATUS_CANCELLED:
                $model->fill([
                    'status' => AdditionalWorkDay::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'cancelled_by' => $actor?->id,
                    'duplicate_guard' => null,
                ]);
                break;
            default:
                return;
        }
        $model->save();

        $record->setRawAttributes($model->getAttributes(), true);
    }

    /**
     * Cancels a record with a reason: drafts and pending records (their approval request is cancelled), or, with
     * $mayCancelApproved, approved days not paid yet. Paid days can never be cancelled.
     *
     * @throws HrException
     */
    public static function cancel(AdditionalWorkDay $record, User $actor, string $reason, bool $mayCancelApproved): AdditionalWorkDay
    {
        return DB::transaction(function () use ($record, $actor, $reason, $mayCancelApproved) {
            $model = self::lock($record);
            if ($model->isPaid()) {
                throw new HrException('additional_day_paid', 'error', 409);
            }
            if (! in_array($model->status, [AdditionalWorkDay::STATUS_DRAFT, AdditionalWorkDay::STATUS_PENDING, AdditionalWorkDay::STATUS_APPROVED], true)) {
                throw new HrException('additional_day_not_cancellable', 'error', 409);
            }
            if ($model->status === AdditionalWorkDay::STATUS_APPROVED && ! $mayCancelApproved) {
                throw new HrException('additional_day_cancel_approved_not_allowed', 'error', 403);
            }

            if ($model->status === AdditionalWorkDay::STATUS_PENDING && $model->approval_request_id) {
                $approval = ApprovalRequest::query()->find($model->approval_request_id);
                if ($approval !== null && $approval->isPending()) {
                    ApprovalService::forceCancel($approval, $actor, $reason);
                    $model = self::lock($model);
                }
            }

            $model->fill([
                'status' => AdditionalWorkDay::STATUS_CANCELLED,
                'duplicate_guard' => null,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancel_reason' => trim($reason),
                'updated_by' => $actor->id,
            ]);
            $model->save();

            return $model;
        });
    }

    /**
     * Pays the eligible approved days with the payroll of a period being approved (inside its transaction).
     *
     * @return array<string, int|float> totals merged into the period totals
     */
    public static function settle(AttendancePeriod $period): array
    {
        $totals = self::emptyTotals();
        $cents = 0;
        foreach (self::eligible($period, true) as $record) {
            $salary = self::salaryOn($record->employee, $record->work_date->toDateString());
            if ($salary === null) {
                $totals['additional_missing_salary']++;

                continue;
            }
            $pay = self::amount($salary->grossSalary(), (float) $record->days);
            $record->fill($pay + [
                'status' => AdditionalWorkDay::STATUS_PAID,
                'paid_period_id' => $period->id,
                'paid_at' => now(),
            ]);
            $record->save();

            $totals['additional_records']++;
            $totals['additional_days'] += (float) $record->days;
            $cents += (int) round(((float) $pay['amount']) * 100);
        }

        return self::finishTotals($totals, $cents);
    }

    /**
     * What approving the period would pay now (processing preview).
     *
     * @return array<string, int|float>
     */
    public static function preview(AttendancePeriod $period): array
    {
        $totals = self::emptyTotals();
        $cents = 0;
        foreach (self::eligible($period, false) as $record) {
            $salary = self::salaryOn($record->employee, $record->work_date->toDateString());
            if ($salary === null) {
                $totals['additional_missing_salary']++;

                continue;
            }
            $totals['additional_records']++;
            $totals['additional_days'] += (float) $record->days;
            $cents += (int) round(((float) self::amount($salary->grossSalary(), (float) $record->days)['amount']) * 100);
        }

        return self::finishTotals($totals, $cents);
    }

    /** Reopening a period: its paid days return to approved but stay reserved to it (paid_period_id). */
    public static function unsettle(AttendancePeriod $period): void
    {
        $records = AdditionalWorkDay::query()
            ->where('paid_period_id', $period->id)
            ->where('status', AdditionalWorkDay::STATUS_PAID)
            ->lockForUpdate()
            ->get();
        foreach ($records as $record) {
            $record->fill([
                'status' => AdditionalWorkDay::STATUS_APPROVED,
                'paid_at' => null,
                'gross_salary' => null,
                'daily_rate' => null,
                'amount' => null,
            ]);
            $record->save();
        }
    }

    /**
     * Re-links the records of a center not paid yet (history included) to the payroll period covering their work
     * date, after periods were added, moved or deleted.
     */
    public static function relink(int $centerId): void
    {
        $periods = AttendancePeriod::query()->forCenter($centerId)->get(['id', 'branch_id', 'period_start', 'period_end', 'status']);
        AdditionalWorkDay::query()
            ->forCenter($centerId)
            ->where('status', '!=', AdditionalWorkDay::STATUS_PAID)
            ->get(['id', 'work_date', 'branch_id', 'attendance_period_id'])
            ->each(function (AdditionalWorkDay $record) use ($periods) {
                $periodId = self::periodFor($periods, $record->work_date->toDateString(), $record->branch_id !== null ? (int) $record->branch_id : null)?->id;
                if ($periodId !== ($record->attendance_period_id !== null ? (int) $record->attendance_period_id : null)) {
                    // Derived link: updated without an audit entry.
                    AdditionalWorkDay::query()->whereKey($record->id)->update(['attendance_period_id' => $periodId]);
                }
            });
    }

    /**
     * Additional days paid by an approved period, grouped by employee id.
     *
     * @return Collection<int, Collection<int, AdditionalWorkDay>>
     */
    public static function paidBy(AttendancePeriod $period): Collection
    {
        return AdditionalWorkDay::query()
            ->where('paid_period_id', $period->id)
            ->where('status', AdditionalWorkDay::STATUS_PAID)
            ->with(['employee.user:id,name', 'paidPeriod:id,period_start'])
            ->orderBy('work_date')
            ->orderBy('id')
            ->get()
            ->groupBy('employee_id');
    }

    /** Estimated pay of an unpaid record from the salary on its work date (salaries eager-loaded when possible). */
    public static function estimate(AdditionalWorkDay $record): ?array
    {
        $employee = $record->employee;
        if ($employee === null) {
            return null;
        }
        $salary = self::salaryOn($employee, $record->work_date->toDateString());

        return $salary ? self::amount($salary->grossSalary(), (float) $record->days) : null;
    }

    /**
     * Counts, days and amounts per status of the records matched by $query (unordered).
     *
     * @param  Builder<AdditionalWorkDay>  $query
     */
    public static function summary(Builder $query): array
    {
        $rows = (clone $query)
            ->selectRaw('status, COUNT(*) as records, COALESCE(SUM(days), 0) as days, COALESCE(SUM(amount), 0) as amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $summary = [];
        foreach (AdditionalWorkDay::STATUSES as $status) {
            $row = $rows->get($status);
            $summary[$status] = [
                'records' => (int) ($row->records ?? 0),
                'days' => round((float) ($row->days ?? 0), 2),
            ];
        }
        $summary[AdditionalWorkDay::STATUS_PAID]['amount'] = round((float) ($rows->get(AdditionalWorkDay::STATUS_PAID)->amount ?? 0), 2);

        $carried = (clone $query)
            ->where('status', AdditionalWorkDay::STATUS_APPROVED)
            ->whereHas('attendancePeriod', fn (Builder $period) => $period->where('status', AttendancePeriod::STATUS_APPROVED))
            ->selectRaw('COUNT(*) as records, COALESCE(SUM(days), 0) as days')
            ->first();
        $summary['carried_forward'] = ['records' => (int) ($carried->records ?? 0), 'days' => round((float) ($carried->days ?? 0), 2)];

        return $summary;
    }

    /**
     * Rules for recording an additional day for an employee: not for oneself, not in the future, while employed,
     * on a day the employee was not scheduled to work (rest day, day off, holiday or unscheduled), and once per date.
     *
     * @return array{branch_id: ?int, day_type: string}
     *
     * @throws HrException
     */
    private static function assertCanRecord(Employee $employee, string $date, User $actor, ?int $ignoreId = null): array
    {
        $name = $employee->user?->name ?? (string) $employee->employee_number;
        if ($employee->user_id !== null && (int) $employee->user_id === (int) $actor->id) {
            throw new HrException('additional_day_own_record', 'employee_ids', 403);
        }

        $today = now()->setTimezone(HrAttendanceProcessor::timezone($employee->center_id))->toDateString();
        if ($date > $today) {
            throw new HrException('additional_day_future', 'work_date');
        }

        $day = HrShiftSchedule::dayFor($employee, $date);
        if ($day->status === ScheduledShiftDay::STATUS_NOT_EMPLOYED) {
            throw new HrException('additional_day_not_employed', 'work_date', 422, ['name' => $name, 'date' => $date]);
        }

        $branchId = HrHolidays::branchesOn(collect([$employee]), $date, $date)[$employee->id][$date] ?? null;
        $holiday = HrHolidays::on(HrHolidays::between($employee->center_id, $date, $date), $date, $branchId);
        if ($holiday === null && $day->status === ScheduledShiftDay::STATUS_WORKING) {
            throw new HrException('additional_day_scheduled_working', 'work_date', 422, ['name' => $name, 'date' => $date]);
        }

        $existing = AdditionalWorkDay::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', AdditionalWorkDay::ACTIVE_STATUSES)
            ->whereDate('work_date', $date)
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->first(['id']);
        if ($existing !== null) {
            throw new HrException('additional_day_duplicate', 'work_date', 409, ['name' => $name, 'date' => $date], ['additional_work_day_id' => $existing->id]);
        }

        return [
            'branch_id' => $branchId,
            'day_type' => $holiday !== null ? AdditionalWorkDay::DAY_TYPE_HOLIDAY : $day->status,
        ];
    }

    private static function startApproval(AdditionalWorkDay $record, User $actor): void
    {
        $employee = $record->employee;
        $employee->loadMissing('manager');
        $approval = ApprovalService::submit(
            $record,
            AdditionalWorkDay::APPROVAL_TYPE,
            $record->center_id,
            $actor,
            $employee,
            fn (array $steps) => HrLeaveRequests::approvalSteps(
                $steps,
                $steps === [],
                $employee->manager?->user_id !== null ? (int) $employee->manager->user_id : null,
                (int) $actor->id,
                $employee->user_id !== null ? (int) $employee->user_id : null,
                self::HR_APPROVAL_PERMISSION,
            ),
        );
        $record->fill([
            'status' => AdditionalWorkDay::STATUS_PENDING,
            'approval_request_id' => $approval->id,
            'submitted_at' => now(),
            'submitted_by' => $actor->id,
        ]);
        $record->save();
    }

    /** @return Collection<int, AdditionalWorkDay> */
    private static function eligible(AttendancePeriod $period, bool $lock): Collection
    {
        $periodBranch = $period->branch_id !== null ? (int) $period->branch_id : null;

        return AdditionalWorkDay::query()
            ->forCenter($period->center_id)
            ->where('status', AdditionalWorkDay::STATUS_APPROVED)
            ->where(fn (Builder $query) => $query->whereNull('paid_period_id')->orWhere('paid_period_id', $period->id))
            ->whereDate('work_date', '<=', $period->period_end->toDateString())
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())
            ->with(['employee' => fn ($query) => $query->withTrashed()->with('salaries'), 'attendancePeriod:id,status'])
            ->orderBy('work_date')
            ->orderBy('id')
            ->get()
            ->filter(fn (AdditionalWorkDay $record) => self::eligibleFor([
                'status' => $record->status,
                'work_date' => $record->work_date->toDateString(),
                'branch_id' => $record->branch_id !== null ? (int) $record->branch_id : null,
                'paid_period_id' => $record->paid_period_id !== null ? (int) $record->paid_period_id : null,
                'own_period_id' => $record->attendance_period_id !== null ? (int) $record->attendance_period_id : null,
                'own_period_status' => $record->attendancePeriod?->status,
            ], (int) $period->id, $period->period_start->toDateString(), $period->period_end->toDateString(), $periodBranch))
            ->values();
    }

    private static function salaryOn(Employee $employee, string $date): ?EmployeeSalary
    {
        if ($employee->relationLoaded('salaries')) {
            return $employee->salaries
                ->filter(fn (EmployeeSalary $salary) => $salary->isEffectiveOn($date))
                ->sortByDesc(fn (EmployeeSalary $salary) => $salary->effective_from->toDateString())
                ->first();
        }

        return $employee->salaryOn($date);
    }

    private static function resolvePeriodId(int $centerId, string $date, ?int $branchId): ?int
    {
        $periods = AttendancePeriod::query()
            ->forCenter($centerId)
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->get(['id', 'branch_id', 'period_start', 'period_end']);

        return self::periodFor($periods, $date, $branchId)?->id;
    }

    private static function lock(AdditionalWorkDay $record): AdditionalWorkDay
    {
        return AdditionalWorkDay::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
    }

    private static function emptyTotals(): array
    {
        return ['additional_records' => 0, 'additional_days' => 0.0, 'additional_amount' => 0.0, 'additional_missing_salary' => 0];
    }

    private static function finishTotals(array $totals, int $cents): array
    {
        $totals['additional_days'] = round($totals['additional_days'], 2);
        $totals['additional_amount'] = round($cents / 100, 2);

        return $totals;
    }

    private static function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
