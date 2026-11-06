<?php

namespace App\Support\Hr;

use App\Models\Hr\ApprovalRequest;
use App\Models\Hr\ApprovalRequestStep;
use App\Models\Hr\Employee;
use App\Models\Hr\Holiday;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\LeaveRequestAttachment;
use App\Models\Hr\LeaveType;
use App\Models\User;
use App\Support\Hr\Approvals\ApprovalService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Leave requests:
 *  - days: working-day types count the scheduled working days (or, without a schedule, the weekdays outside
 *    general.weekend_days) that are not holidays; calendar-day types count every day of the range;
 *  - a request stays within one calendar year (its balance year), inside employment, after the payroll lock and
 *    outside approved attendance periods, without overlapping another pending / approved request, within the type's
 *    maximum per request and the available balance;
 *  - pending requests reserve their days and go through the approval engine: the leave_request workflow when the
 *    center configured one, else the employee's direct manager; an HR step (apply_hr-leave) follows when the type
 *    requires HR approval or no other approver exists;
 *  - approval uses the reserved days and reprocesses the attendance of the leave days; rejection / cancellation
 *    releases them; cancelling approved leave (admin) restores them and reprocesses attendance.
 */
final class HrLeaveRequests
{
    public const HR_APPROVAL_PERMISSION = 'apply_hr-leave';

    public const DISK = 'local';
    public const MAX_ATTACHMENTS = 5;
    public const ATTACHMENT_MAX_KB = 5120;
    public const ATTACHMENT_MIMES = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

    /**
     * Days of a leave range for an employee.
     *
     * @return array{days: int, calendar_days: int, dates: string[], not_employed: string[]}
     */
    public static function countDays(Employee $employee, LeaveType $type, string $from, string $to): array
    {
        $schedule = HrShiftSchedule::forEmployee($employee, $from, $to);
        $holidays = HrHolidays::between($employee->center_id, $from, $to);
        $branches = HrHolidays::branchesOn(collect([$employee]), $from, $to)[$employee->id] ?? [];
        $weekend = self::weekendDays($employee->center_id);

        $dates = [];
        $notEmployed = [];
        foreach ($schedule as $date => $day) {
            if ($day->status === ScheduledShiftDay::STATUS_NOT_EMPLOYED) {
                $notEmployed[] = $date;

                continue;
            }
            if (self::isLeaveDay($day, HrHolidays::on($holidays, $date, $branches[$date] ?? null), $weekend, $type->countsCalendarDays())) {
                $dates[] = $date;
            }
        }

        return ['days' => count($dates), 'calendar_days' => count($schedule), 'dates' => $dates, 'not_employed' => $notEmployed];
    }

    /** Whether leave of this kind consumes $day. Pure. */
    public static function isLeaveDay(ScheduledShiftDay $day, ?Holiday $holiday, array $weekendDays, bool $calendarDays): bool
    {
        if ($day->status === ScheduledShiftDay::STATUS_NOT_EMPLOYED) {
            return false;
        }
        if ($calendarDays) {
            return true;
        }
        if ($holiday !== null) {
            return false;
        }
        if ($day->isWorking()) {
            return true;
        }

        return $day->status === ScheduledShiftDay::STATUS_UNSCHEDULED
            && ! in_array(Carbon::parse($day->date)->dayOfWeek, $weekendDays, true);
    }

    /**
     * Approval steps of a leave request (or of another employee request decided by the manager, then HR holding
     * $hrPermission). Pure.
     *
     * @param  array<int, array{approver_type: string, approver_value: ?string}>  $workflowSteps  [] without a workflow
     * @return array<int, array{step_order: int, approver_type: string, approver_value: ?string}>
     */
    public static function approvalSteps(
        array $workflowSteps,
        bool $requiresHr,
        ?int $managerUserId,
        int $requesterId,
        ?int $employeeUserId,
        string $hrPermission = self::HR_APPROVAL_PERMISSION,
    ): array {
        $steps = array_values($workflowSteps);
        if ($steps === [] && $managerUserId !== null && $managerUserId !== $requesterId && $managerUserId !== $employeeUserId) {
            $steps[] = ['approver_type' => ApprovalRequestStep::APPROVER_DIRECT_MANAGER, 'approver_value' => null];
        }

        $hasHr = false;
        foreach ($steps as $step) {
            $hasHr = $hasHr || ($step['approver_type'] === ApprovalRequestStep::APPROVER_PERMISSION && $step['approver_value'] === $hrPermission);
        }
        if (($requiresHr || $steps === []) && ! $hasHr) {
            $steps[] = ['approver_type' => ApprovalRequestStep::APPROVER_PERMISSION, 'approver_value' => $hrPermission];
        }

        $ordered = [];
        foreach ($steps as $index => $step) {
            $ordered[] = [
                'step_order' => $index + 1,
                'approver_type' => $step['approver_type'],
                'approver_value' => $step['approver_value'] ?? null,
            ];
        }

        return $ordered;
    }

    /**
     * What a request would be, without saving: days, balance and the first problem found.
     *
     * @return array<string, mixed>
     */
    public static function preview(Employee $employee, array $data, bool $selfService): array
    {
        $type = self::leaveType($employee->center_id, $data['leave_type_id'] ?? null);
        $result = self::evaluate($employee, $type, (string) $data['start_date'], (string) $data['end_date'], $selfService, false);
        $balance = $result['balance'];
        $problem = $result['problems'][0] ?? null;

        return [
            'leave_type' => [
                'id' => $type->id,
                'name' => $type->name,
                'is_paid' => $type->is_paid,
                'day_counting' => $type->day_counting,
                'requires_attachment' => $type->requires_attachment,
                'requires_hr_approval' => $type->requires_hr_approval,
                'max_days_per_request' => $type->max_days_per_request,
            ],
            'days' => $result['days'],
            'calendar_days' => $result['calendar_days'],
            'dates' => $result['dates'],
            'balance' => $balance === null ? null : [
                'year' => $balance->year,
                'total' => $balance->total(),
                'used' => $balance->used_days,
                'pending' => $balance->pending_days,
                'available' => $balance->available(),
                'after' => round($balance->available() - $result['days'], 2),
            ],
            'valid' => $problem === null,
            'problem' => $problem === null ? null : [
                'reason' => $problem->reason,
                'field' => $problem->field,
                'message' => $problem->localizedMessage(),
            ],
        ];
    }

    /**
     * @param  string  $source  LeaveRequest::SOURCE_*; `recorded` saves the leave as approved (no approval steps)
     * @param  UploadedFile[]  $files
     *
     * @throws HrException
     */
    public static function submit(Employee $employee, array $data, User $actor, string $source, array $files = []): LeaveRequest
    {
        $type = self::leaveType($employee->center_id, $data['leave_type_id'] ?? null);
        if ($type->requires_attachment && $files === []) {
            throw new HrException('leave_attachment_required', 'attachments');
        }
        if (count($files) > self::MAX_ATTACHMENTS) {
            throw new HrException('leave_too_many_attachments', 'attachments', 422, ['max' => self::MAX_ATTACHMENTS]);
        }

        $stored = [];
        try {
            return DB::transaction(function () use ($employee, $type, $data, $actor, $source, $files, &$stored) {
                Employee::query()->whereKey($employee->id)->lockForUpdate()->first();

                $start = (string) $data['start_date'];
                $end = (string) $data['end_date'];
                $result = self::evaluate($employee, $type, $start, $end, $source === LeaveRequest::SOURCE_SELF, true);
                if ($result['problems'] !== []) {
                    throw $result['problems'][0];
                }

                $recorded = $source === LeaveRequest::SOURCE_RECORDED;
                $request = LeaveRequest::create([
                    'center_id' => $employee->center_id,
                    'employee_id' => $employee->id,
                    'leave_type_id' => $type->id,
                    'branch_id' => $employee->branch_id,
                    'start_date' => $start,
                    'end_date' => $end,
                    'days' => $result['days'],
                    'calendar_days' => $result['calendar_days'],
                    'balance_year' => (int) substr($start, 0, 4),
                    'reason' => trim((string) $data['reason']),
                    'status' => $recorded ? LeaveRequest::STATUS_APPROVED : LeaveRequest::STATUS_PENDING,
                    'source' => $source,
                    'requested_by' => $actor->id,
                    'decided_at' => $recorded ? now() : null,
                    'decided_by' => $recorded ? $actor->id : null,
                ]);
                $request->setRelation('leaveType', $type);
                $request->setRelation('employee', $employee);

                foreach ($files as $file) {
                    $stored[] = self::storeAttachment($request, $file, $actor)->path;
                }

                if ($recorded) {
                    HrLeaveBalances::useDirectly($request, $actor);
                    HrAttendanceProcessor::forEmployee($employee, $start, $end);

                    return $request;
                }

                HrLeaveBalances::reserve($request, $actor);
                $employee->loadMissing('manager');
                $approval = ApprovalService::submit(
                    $request,
                    LeaveRequest::APPROVAL_TYPE,
                    $employee->center_id,
                    $actor,
                    $employee,
                    fn (array $steps) => self::approvalSteps(
                        $steps,
                        $type->requires_hr_approval,
                        $employee->manager?->user_id !== null ? (int) $employee->manager->user_id : null,
                        (int) $actor->id,
                        $employee->user_id !== null ? (int) $employee->user_id : null,
                    ),
                );
                $request->approval_request_id = $approval->id;
                $request->save();

                return $request;
            });
        } catch (\Throwable $e) {
            if ($stored !== []) {
                Storage::disk(self::DISK)->delete($stored);
            }

            throw $e;
        }
    }

    /** Called by the approval engine, inside its transaction, once the approval request is resolved. */
    public static function approvalResolved(LeaveRequest $leave, ApprovalRequest $approval): void
    {
        $request = LeaveRequest::query()->whereKey($leave->id)->lockForUpdate()->firstOrFail();
        if (! $request->isPending()) {
            return;
        }
        $request->load(['leaveType', 'employee']);

        $actorId = $approval->steps->whereNotNull('acted_by')->sortByDesc('step_order')->first()?->acted_by;
        $actor = auth()->user();

        switch ($approval->status) {
            case ApprovalRequest::STATUS_APPROVED:
                self::assertDaysNotLocked($request->employee, $request->start_date->toDateString(), $request->end_date->toDateString());
                HrLeaveBalances::consume($request, $actor);
                $request->fill(['status' => LeaveRequest::STATUS_APPROVED, 'decided_at' => now(), 'decided_by' => $actorId ?? $actor?->id]);
                $request->save();
                HrAttendanceProcessor::forEmployee($request->employee, $request->start_date->toDateString(), $request->end_date->toDateString());
                break;
            case ApprovalRequest::STATUS_REJECTED:
                HrLeaveBalances::release($request, $actor);
                $request->fill(['status' => LeaveRequest::STATUS_REJECTED, 'decided_at' => now(), 'decided_by' => $actorId ?? $actor?->id]);
                $request->save();
                break;
            case ApprovalRequest::STATUS_CANCELLED:
                HrLeaveBalances::release($request, $actor);
                $request->fill([
                    'status' => LeaveRequest::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'cancelled_by' => $actor?->id,
                ]);
                $request->save();
                break;
        }

        $leave->setRawAttributes($request->getAttributes(), true);
    }

    /**
     * Cancels a pending request (releases its days) or, with $mayCancelApproved, approved leave (restores its days
     * and reprocesses attendance), with a reason. Who may cancel is decided by the caller.
     *
     * @throws HrException
     */
    public static function cancel(LeaveRequest $leave, User $actor, string $reason, bool $mayCancelApproved): LeaveRequest
    {
        return DB::transaction(function () use ($leave, $actor, $reason, $mayCancelApproved) {
            $request = LeaveRequest::query()->whereKey($leave->id)->lockForUpdate()->firstOrFail();
            $request->load(['leaveType', 'employee']);
            $reason = trim($reason);

            if ($request->isPending()) {
                $approval = $request->approval_request_id ? ApprovalRequest::query()->find($request->approval_request_id) : null;
                if ($approval !== null && $approval->isPending()) {
                    ApprovalService::forceCancel($approval, $actor, $reason);
                    $request->refresh()->load(['leaveType', 'employee']);
                } else {
                    HrLeaveBalances::release($request, $actor);
                    $request->status = LeaveRequest::STATUS_CANCELLED;
                }
                $request->fill(['cancelled_at' => now(), 'cancelled_by' => $actor->id, 'cancel_reason' => $reason]);
                $request->save();

                return $request;
            }

            if (! $request->isApproved()) {
                throw new HrException('leave_not_cancellable', 'error', 409);
            }
            if (! $mayCancelApproved) {
                throw new HrException('leave_cancel_approved_not_allowed', 'error', 403);
            }

            $start = $request->start_date->toDateString();
            $end = $request->end_date->toDateString();
            self::assertNotPayrollLocked($request->center_id, $start);
            self::assertDaysNotLocked($request->employee, $start, $end);

            HrLeaveBalances::restore($request, $actor, $reason);
            $request->fill([
                'status' => LeaveRequest::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancel_reason' => $reason,
            ]);
            $request->save();
            HrAttendanceProcessor::forEmployee($request->employee, $start, $end);

            return $request;
        });
    }

    public static function download(LeaveRequestAttachment $file): StreamedResponse
    {
        $disk = Storage::disk($file->disk);
        if (! $disk->exists($file->path)) {
            throw new HrException('document_file_missing', 'error', 404);
        }

        return $disk->download($file->path, $file->original_name, array_filter(['Content-Type' => $file->mime_type]));
    }

    public static function leaveType(int $centerId, mixed $id): LeaveType
    {
        HrLeaveTypes::ensureDefaults($centerId);
        $type = is_numeric($id) ? LeaveType::query()->forCenter($centerId)->find((int) $id) : null;
        if ($type === null) {
            throw new HrException('leave_type_not_found', 'leave_type_id');
        }

        return $type;
    }

    /** @return int[] */
    public static function weekendDays(int $centerId): array
    {
        return array_map('intval', (array) HrSettings::get($centerId, 'general.weekend_days'));
    }

    /**
     * @return array{days: int, calendar_days: int, dates: string[], balance: ?\App\Models\Hr\LeaveBalance, problems: HrException[]}
     */
    private static function evaluate(Employee $employee, LeaveType $type, string $start, string $end, bool $selfService, bool $lock): array
    {
        $result = ['days' => 0, 'calendar_days' => 0, 'dates' => [], 'balance' => null, 'problems' => []];
        $fail = function (HrException $problem) use (&$result) {
            $result['problems'][] = $problem;

            return $result;
        };

        if (! $type->is_active) {
            return $fail(new HrException('leave_type_inactive', 'leave_type_id'));
        }
        if ($selfService && ! $type->allow_self_request) {
            return $fail(new HrException('leave_type_not_self_service', 'leave_type_id'));
        }
        if ($end < $start) {
            return $fail(new HrException('leave_dates_invalid', 'end_date'));
        }
        if (substr($start, 0, 4) !== substr($end, 0, 4)) {
            return $fail(new HrException('leave_crosses_year', 'end_date'));
        }

        $count = self::countDays($employee, $type, $start, $end);
        $result['days'] = $count['days'];
        $result['calendar_days'] = $count['calendar_days'];
        $result['dates'] = $count['dates'];
        $result['balance'] = HrLeaveBalances::balanceFor($employee, $type, (int) substr($start, 0, 4), $lock);

        $checks = [
            fn () => $count['not_employed'] !== []
                ? new HrException('leave_outside_employment', 'start_date', 422, ['date' => $count['not_employed'][0]])
                : null,
            fn () => $count['days'] <= 0 ? new HrException('leave_no_working_days', 'start_date') : null,
            fn () => $type->max_days_per_request !== null && $count['days'] > $type->max_days_per_request
                ? new HrException('leave_exceeds_max_days', 'end_date', 422, ['max' => $type->max_days_per_request])
                : null,
            fn () => self::payrollLockProblem($employee->center_id, $start),
            fn () => self::lockedDayProblem($employee, $start, $end),
            fn () => self::overlapProblem($employee, $start, $end),
            fn () => $result['balance'] !== null && $count['days'] > $result['balance']->available()
                ? new HrException('leave_insufficient_balance', 'end_date', 422, [
                    'available' => HrLeaveBalances::format($result['balance']->available()),
                    'days' => $count['days'],
                ])
                : null,
        ];
        foreach ($checks as $check) {
            $problem = $check();
            if ($problem !== null) {
                $result['problems'][] = $problem;
            }
        }

        return $result;
    }

    private static function payrollLockProblem(int $centerId, string $start): ?HrException
    {
        $locked = HrEffectiveDating::lockedUntil($centerId);

        return $locked !== null && $start <= $locked->toDateString()
            ? new HrException('leave_payroll_locked', 'start_date', 422, ['date' => $locked->toDateString()])
            : null;
    }

    private static function lockedDayProblem(Employee $employee, string $start, string $end): ?HrException
    {
        $date = HrAttendanceProcessor::firstLockedDate($employee->id, $start, $end);

        return $date !== null ? new HrException('attendance_day_locked', 'start_date', 422, ['date' => $date]) : null;
    }

    private static function overlapProblem(Employee $employee, string $start, string $end): ?HrException
    {
        $other = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', LeaveRequest::ACTIVE_STATUSES)
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->orderBy('start_date')
            ->first();

        return $other !== null
            ? new HrException('leave_overlaps', 'start_date', 422, [
                'start' => $other->start_date->toDateString(),
                'end' => $other->end_date->toDateString(),
            ], ['leave_request_id' => $other->id])
            : null;
    }

    private static function assertNotPayrollLocked(int $centerId, string $start): void
    {
        $problem = self::payrollLockProblem($centerId, $start);
        if ($problem !== null) {
            throw $problem;
        }
    }

    private static function assertDaysNotLocked(Employee $employee, string $start, string $end): void
    {
        $problem = self::lockedDayProblem($employee, $start, $end);
        if ($problem !== null) {
            throw $problem;
        }
    }

    private static function storeAttachment(LeaveRequest $request, UploadedFile $file, User $actor): LeaveRequestAttachment
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $directory = "hr/center_{$request->center_id}/leave/{$request->id}";
        $path = $file->storeAs($directory, Str::uuid()->toString().'.'.$extension, self::DISK);
        if ($path === false) {
            throw new HrException('document_upload_failed', 'attachments');
        }

        try {
            return LeaveRequestAttachment::create([
                'center_id' => $request->center_id,
                'leave_request_id' => $request->id,
                'disk' => self::DISK,
                'path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                'mime_type' => $file->getClientMimeType(),
                'size' => (int) $file->getSize(),
                'uploaded_by' => $actor->id,
            ]);
        } catch (\Throwable $e) {
            Storage::disk(self::DISK)->delete($path);
            throw $e;
        }
    }
}
