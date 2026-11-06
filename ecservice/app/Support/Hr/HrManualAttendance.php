<?php

namespace App\Support\Hr;

use App\Models\Hr\AttendancePolicy;
use App\Models\Hr\AttendanceRecord;
use App\Models\Hr\AttendanceRecordChange;
use App\Models\Hr\Employee;
use App\Models\Hr\Shift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Attendance entered by HR (manual attendance) and HR corrections of attendance records, in the same
 * hr_attendance_records as electronic attendance.
 *
 *  - No GPS: manual times are stored with verification `manual` and no coordinates.
 *  - One record per employee and attendance date whatever the method (unique index + check under a lock on the
 *    employee, shared with electronic check-in), and no overlap with the records of the previous / next day.
 *  - Times are entered as HH:MM in the branch timezone. Without an explicit date, a check-in is placed on the
 *    occurrence (day before, the day, day after) nearest the scheduled shift, and a check-out on the first
 *    occurrence after the check-in, so overnight shifts need no extra input. Both must fall inside the day's
 *    punch window, the check-out after the check-in, within 24 hours, and neither in the future.
 *  - Lateness, early departure and worked time come from HrShiftCalculator with the day's shift (or the shift
 *    chosen instead) and the grace periods and counting rules in force that day; the attendance status follows.
 *  - Who may: edit_hr-attendance-records within the user's branches; dates older than
 *    attendance.manual_entry_max_days_back need admin_hr-attendance-records; nothing on or before
 *    payroll.changes_locked_until; the user's own record needs edit_hr-own-attendance-records.
 *  - Creating a record needs an employed employee whose attendance rules allow manual attendance (exempt
 *    employees never). Electronic records are corrected, not replaced: open sessions cannot be changed, recorded
 *    times cannot be removed, the shift stays, and the method stays electronic.
 *  - Every creation and change is stored in hr_attendance_record_changes (previous / new values, reason, user,
 *    time) and in the HR audit log.
 */
final class HrManualAttendance
{
    public const EDIT_PERMISSION = 'edit_hr-attendance-records';
    public const HISTORICAL_PERMISSION = 'admin_hr-attendance-records';
    public const OWN_RECORDS_PERMISSION = 'edit_hr-own-attendance-records';

    public const MAX_DURATION_MINUTES = 1440;

    /** Manual times may run this far ahead of the server clock (clock drift between the HR user and the server). */
    private const FUTURE_TOLERANCE_SECONDS = 60;

    /** Fields of the change history, in display order. */
    private const TRACKED = [
        'shift', 'check_in', 'check_out', 'status', 'attendance_status', 'late_minutes', 'early_departure_minutes',
        'worked_minutes', 'entry_reason', 'entry_reason_notes', 'notes',
    ];

    public function __construct(
        private readonly int $centerId,
        private readonly ?User $actor,
        /** Branches the actor may manage (null = all). */
        private readonly ?array $branchScope,
    ) {
    }

    public function canEnterHistorical(): bool
    {
        return $this->actor === null || can(self::HISTORICAL_PERMISSION, $this->actor);
    }

    /**
     * Validates and calculates without saving: the scheduled day, the resolved times and the resulting values.
     *
     * @param  array<string, mixed>  $input  employee_id, attendance_date, shift_id, check_in, check_in_date,
     *                                       check_out, check_out_date (record: only what changes)
     * @return array<string, mixed>
     *
     * @throws HrException
     */
    public function preview(array $input, ?AttendanceRecord $record, CarbonInterface $now): array
    {
        $now = self::normalize($now);
        if ($record === null) {
            $employee = $this->employee($input['employee_id'] ?? null);
            $this->assertNoRecord($employee, (string) $input['attendance_date']);
            $plan = $this->plan($employee, (string) $input['attendance_date'], $input, null, $now, false);
        } else {
            $plan = $this->plan($this->recordEmployee($record), $record->attendance_date->toDateString(), $input, $record, $now, false);
        }

        return $this->describe($plan);
    }

    /**
     * @param  array<string, mixed>  $input  employee_id, attendance_date, shift_id, check_in(_date), check_out(_date),
     *                                       notes, entry_reason, entry_reason_notes
     *
     * @throws HrException
     */
    public function create(array $input, string $source, CarbonInterface $now): AttendanceRecord
    {
        $now = self::normalize($now);
        $employee = $this->employee($input['employee_id'] ?? null);

        return DB::transaction(function () use ($employee, $input, $source, $now) {
            Employee::query()->whereKey($employee->id)->lockForUpdate()->first();

            $this->assertNoRecord($employee, (string) $input['attendance_date']);
            $plan = $this->plan($employee, (string) $input['attendance_date'], $input, null, $now);

            $record = new AttendanceRecord([
                'center_id' => $employee->center_id,
                'employee_id' => $employee->id,
                'attendance_date' => $plan['date'],
                'attendance_method' => AttendanceRecord::METHOD_MANUAL,
                'open_session_key' => null,
                'attendance_config_id' => $plan['rules']->configId,
                'attendance_policy_id' => $plan['rules']->policyId,
                'attendance_policy_version_id' => $plan['rules']->policyVersionId,
                'gps_requirement' => Employee::GPS_NONE,
                'missing_checkout_action' => $plan['rules']->missingCheckoutAction,
                'check_out_deadline_at' => null,
                'work_location_id' => $employee->effectiveWorkLocation($plan['date'])?->id,
                'entry_source' => in_array($source, AttendanceRecord::ENTRY_SOURCES, true) ? $source : AttendanceRecord::SOURCE_FORM,
                'entry_reason' => $input['entry_reason'] ?? null,
                'entry_reason_notes' => self::text($input['entry_reason_notes'] ?? null),
                'notes' => self::text($input['notes'] ?? null),
                'created_by' => $this->actor?->id,
            ]);
            $record->setRelation('employee', $employee);
            $this->apply($record, $plan, true, true);
            $this->save($record);

            $this->log($record, AttendanceRecordChange::ACTION_CREATED, null, $this->snapshot($record), $record->entry_source, $this->creationReason($record));

            return $record;
        });
    }

    /**
     * Changes a record. Keys missing from $input keep their current value; `reason` (why it changes) is required.
     *
     * @throws HrException
     */
    public function update(AttendanceRecord $record, array $input, CarbonInterface $now): AttendanceRecord
    {
        $now = self::normalize($now);
        $employee = $this->recordEmployee($record);

        return DB::transaction(function () use ($record, $employee, $input, $now) {
            Employee::query()->whereKey($employee->id)->lockForUpdate()->first();
            $record = AttendanceRecord::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            $record->setRelation('employee', $employee);

            $plan = $this->plan($employee, $record->attendance_date->toDateString(), $input, $record, $now);
            $before = $this->snapshot($record);

            // Electronic records are only recalculated when HR changes one of their times.
            $manual = $record->isManual();
            $inChanged = ! self::sameInstant($record->check_in_at, $plan['check_in_at']);
            $outChanged = ! self::sameInstant($record->check_out_at, $plan['check_out_at']);
            if ($manual || $inChanged || $outChanged) {
                $this->apply($record, $plan, $manual || $inChanged, $manual || $outChanged);
            }
            if ($manual) {
                foreach (['entry_reason', 'entry_reason_notes'] as $field) {
                    if (array_key_exists($field, $input)) {
                        $record->{$field} = $field === 'entry_reason' ? $input[$field] : self::text($input[$field]);
                    }
                }
            }
            if (array_key_exists('notes', $input)) {
                $record->notes = self::text($input['notes']);
            }

            [$old, $new] = $this->diff($before, $this->snapshot($record));
            if ($new === [] && ! $record->isDirty()) {
                throw new HrException('attendance_no_changes');
            }

            $record->fill(['updated_by' => $this->actor?->id, 'modified_at' => $now]);
            $this->save($record);

            $action = match (true) {
                $before['check_in'] === null && $record->check_in_at !== null && ! $outChanged => AttendanceRecordChange::ACTION_CHECK_IN_CORRECTED,
                $before['check_out'] === null && $record->check_out_at !== null && ! $inChanged => AttendanceRecordChange::ACTION_CHECK_OUT_CORRECTED,
                default => AttendanceRecordChange::ACTION_UPDATED,
            };
            $this->log($record, $action, $old, $new, AttendanceRecord::SOURCE_FORM, (string) $input['reason']);

            return $record;
        });
    }

    /**
     * Creates every entry on its own: a failing entry is reported and the others are still saved.
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @param  array<string, mixed>  $common  values shared by every entry (entry_reason, entry_reason_notes)
     * @return array{created: int, failed: int, results: array<int, array<string, mixed>>}
     */
    public function createMany(array $entries, array $common, string $source, CarbonInterface $now): array
    {
        $results = [];
        foreach ($entries as $key => $entry) {
            try {
                $record = $this->create(array_merge($common, array_filter($entry, fn ($value) => $value !== null && $value !== '')), $source, $now);
                $results[] = ['key' => $key, 'status' => 'created', 'record_id' => $record->id, 'attendance_status' => $record->attendance_status];
            } catch (HrException $e) {
                $results[] = ['key' => $key] + self::failure($e);
            }
        }

        $created = count(array_filter($results, fn ($row) => $row['status'] === 'created'));

        return ['created' => $created, 'failed' => count($results) - $created, 'results' => $results];
    }

    /** @return array{status: string, reason: string, field: string, message: string, details: array} */
    public static function failure(HrException $e): array
    {
        return [
            'status' => 'failed',
            'reason' => $e->reason,
            'field' => $e->field,
            'message' => $e->localizedMessage(),
            'details' => $e->details,
        ];
    }

    /**
     * @return array{employee: Employee, date: string, timezone: string, scheduled: ScheduledShiftDay, day: ScheduledShiftDay,
     *   rules: AttendanceRuleSet, shift_overridden: bool, check_in_at: ?CarbonImmutable, check_out_at: ?CarbonImmutable,
     *   evaluation: array, record: ?AttendanceRecord, warnings: string[]}
     */
    private function plan(Employee $employee, string $date, array $input, ?AttendanceRecord $record, CarbonImmutable $now, bool $requireTimes = true): array
    {
        $this->assertMayEdit($employee);
        $timezone = HrElectronicAttendance::timezoneFor($employee);
        $this->assertDateAllowed($date, $timezone, $now);
        if (HrAttendanceProcessor::firstLockedDate($employee->id, $date, $date) !== null) {
            throw new HrException('attendance_day_locked', 'attendance_date', 422, ['date' => $date]);
        }

        if ($record !== null && $record->isOpen()) {
            throw new HrException('attendance_record_open', 'error', 409);
        }

        $scheduled = HrShiftSchedule::dayFor($employee, $date);
        if ($scheduled->status === ScheduledShiftDay::STATUS_NOT_EMPLOYED) {
            throw new HrException('attendance_not_employed_on_date', 'attendance_date');
        }
        $rules = $scheduled->rules ?? HrAttendanceRules::on($employee, $date);
        if ($record === null) {
            $this->assertManualAllowed($rules);
        }
        if ($scheduled->isWorking()) {
            $timezone = $scheduled->startAt->getTimezone()->getName();
        }

        [$day, $overridden] = $this->dayFor($employee, $date, $scheduled, $rules, $timezone, $input, $record);

        $checkInDate = self::text($input['check_in_date'] ?? null);
        $checkOutDate = self::text($input['check_out_date'] ?? null);
        $in = $this->checkInAt($this->timeInput($input, 'check_in', $record?->check_in_at, $timezone), $checkInDate, $date, $day, $timezone);
        $out = $this->checkOutAt($this->timeInput($input, 'check_out', $record?->check_out_at, $timezone), $checkOutDate, $date, $day, $timezone, $in);

        if ($in === null && $out === null && $requireTimes) {
            throw new HrException('attendance_time_required', 'check_in');
        }
        if ($record !== null && ! $record->isManual()) {
            foreach (['check_in' => [$record->check_in_at, $in], 'check_out' => [$record->check_out_at, $out]] as $field => [$current, $next]) {
                if ($current !== null && $next === null) {
                    throw new HrException('attendance_electronic_time_required', $field);
                }
            }
        }
        if ($in !== null || $out !== null) {
            $this->assertTimes($in, $out, $date, $day, $now);
            $this->assertNoOverlap($employee, $date, $in, $out, $record, $timezone);
        }

        $evaluation = HrShiftCalculator::evaluate($day, $in, $out);

        return [
            'employee' => $employee,
            'date' => $date,
            'timezone' => $timezone,
            'scheduled' => $scheduled,
            'day' => $day,
            'rules' => $rules,
            'shift_overridden' => $overridden,
            'check_in_at' => $in,
            'check_out_at' => $out,
            'evaluation' => $evaluation,
            'record' => $record,
            'warnings' => $this->warnings($day, $rules, $overridden, $in, $out, $evaluation, $date, $timezone, $now, $record),
        ];
    }

    /**
     * The day the times are compared with: the scheduled day, or the chosen shift's hours on that date (manual
     * records only; electronic records keep the shift they were recorded with).
     *
     * @return array{0: ScheduledShiftDay, 1: bool}
     */
    private function dayFor(Employee $employee, string $date, ScheduledShiftDay $scheduled, AttendanceRuleSet $rules, string $timezone, array $input, ?AttendanceRecord $record): array
    {
        $current = $record?->shift_id;
        $requested = array_key_exists('shift_id', $input) ? ($input['shift_id'] !== null ? (int) $input['shift_id'] : null) : $current;
        $scheduledShift = $scheduled->isWorking() ? $scheduled->shift?->shiftId : null;

        if ($record !== null && ! $record->isManual()) {
            if ($requested !== $current) {
                throw new HrException('attendance_electronic_shift_locked', 'shift_id');
            }

            return [$scheduled, false];
        }
        if ($requested === null || $requested === $scheduledShift) {
            return [$scheduled, false];
        }

        $shift = Shift::query()->forCenter($this->centerId)
            ->when($requested === $current, fn ($query) => $query->withTrashed())
            ->with(['versions' => fn ($query) => $query->orderBy('effective_from')])
            ->find($requested);
        if ($shift === null || $shift->isRotating() || (! $shift->is_active && $requested !== $current)) {
            throw new HrException('attendance_shift_invalid', 'shift_id');
        }
        if ($shift->branch_id !== null && $employee->branch_id !== null && (int) $shift->branch_id !== (int) $employee->branch_id) {
            throw new HrException('attendance_shift_other_branch', 'shift_id');
        }

        $version = null;
        foreach ($shift->versions as $candidate) {
            if ($candidate->effective_from->toDateString() > $date) {
                break;
            }
            $version = $candidate;
        }
        $definition = ShiftDefinition::fromShift($shift, $version ?? $shift->versions->first());

        return [ScheduledShiftDay::working(
            $date,
            $definition,
            $timezone,
            $rules->lateGrace($definition->graceMinutes),
            $rules->earlyLeaveGrace($definition->earlyLeaveGraceMinutes),
            ScheduledShiftDay::SOURCE_ASSIGNMENT,
            ['assigned_shift_id' => $scheduled->assignedShiftId],
            $rules,
        ), true];
    }

    /**
     * The entered time ("H:i"), or the record's stored instant when the key is absent or repeats the stored time
     * (and date): re-entering 07:58 keeps an electronic 07:58:23 check-in and its GPS verification.
     */
    private function timeInput(array $input, string $key, ?CarbonInterface $current, string $timezone): CarbonInterface|string|null
    {
        if (! array_key_exists($key, $input)) {
            return $current;
        }
        $value = self::text($input[$key]);
        if ($value !== null && $current !== null) {
            $local = CarbonImmutable::instance($current)->setTimezone($timezone);
            $date = self::text($input[$key.'_date'] ?? null);
            if (substr($value, 0, 5) === $local->format('H:i') && ($date === null || $date === $local->toDateString())) {
                return $current;
            }
        }

        return $value;
    }

    private function checkInAt(mixed $time, ?string $date, string $attendanceDate, ScheduledShiftDay $day, string $timezone): ?CarbonImmutable
    {
        if ($time instanceof CarbonInterface) {
            return CarbonImmutable::instance($time)->setTimezone($timezone);
        }
        if ($time === null) {
            return null;
        }
        if ($date !== null) {
            return self::at($date, (string) $time, $timezone);
        }
        if (! $day->isWorking()) {
            return self::at($attendanceDate, (string) $time, $timezone);
        }

        return $this->nearest($attendanceDate, (string) $time, $timezone, $day);
    }

    private function checkOutAt(mixed $time, ?string $date, string $attendanceDate, ScheduledShiftDay $day, string $timezone, ?CarbonImmutable $in): ?CarbonImmutable
    {
        if ($time instanceof CarbonInterface) {
            $time = CarbonImmutable::instance($time)->setTimezone($timezone);
            // A stored check-out keeps its instant unless the check-in moved past it: then the same clock time is
            // placed after the new check-in.
            if ($in === null || $time->gt($in)) {
                return $time;
            }
            $time = $time->format('H:i');
        }
        if ($time === null) {
            return null;
        }
        if ($date !== null) {
            return self::at($date, (string) $time, $timezone);
        }
        if ($in !== null) {
            $candidate = self::at($in->toDateString(), (string) $time, $timezone);

            return $candidate->lte($in) ? $candidate->addDay() : $candidate;
        }
        if (! $day->isWorking()) {
            return self::at($attendanceDate, (string) $time, $timezone);
        }

        return $this->nearest($attendanceDate, (string) $time, $timezone, $day);
    }

    /** The occurrence of $time (the day before, the day, the day after) nearest the shift; the day itself on a tie. */
    private function nearest(string $date, string $time, string $timezone, ScheduledShiftDay $day): CarbonImmutable
    {
        $best = null;
        foreach ([0, 1, -1] as $offset) {
            $candidate = self::at($date, $time, $timezone)->addDays($offset);
            if ($best === null || $day->distanceMinutes($candidate) < $day->distanceMinutes($best)) {
                $best = $candidate;
            }
        }

        return $best;
    }

    private function assertTimes(?CarbonImmutable $in, ?CarbonImmutable $out, string $date, ScheduledShiftDay $day, CarbonImmutable $now): void
    {
        $limit = $now->addSeconds(self::FUTURE_TOLERANCE_SECONDS);
        foreach (['check_in' => $in, 'check_out' => $out] as $field => $at) {
            if ($at !== null && $at->gt($limit)) {
                throw new HrException('attendance_time_in_future', $field);
            }
        }
        if ($in !== null && $out !== null) {
            if ($out->lte($in)) {
                throw new HrException('attendance_check_out_before_check_in', 'check_out');
            }
            if (HrShiftMath::minutesBetween($in, $out) > self::MAX_DURATION_MINUTES) {
                throw new HrException('attendance_duration_too_long', 'check_out', 422, ['hours' => self::MAX_DURATION_MINUTES / 60]);
            }
        }

        if ($day->isWorking()) {
            [$from, $to] = $this->window($day);
            foreach (['check_in' => $in, 'check_out' => $out] as $field => $at) {
                if ($at !== null && ($at->lt($from) || $at->gt($to))) {
                    throw new HrException('attendance_time_outside_day', $field, 422, [
                        'from' => $from->format('Y-m-d H:i'),
                        'to' => $to->format('Y-m-d H:i'),
                    ]);
                }
            }

            return;
        }

        // Without a scheduled shift the attendance starts on the attendance date itself.
        $first = $in ?? $out;
        if ($first->toDateString() !== $date) {
            throw new HrException('attendance_time_not_on_date', $in !== null ? 'check_in' : 'check_out', 422, ['date' => $date]);
        }
    }

    /**
     * Times attributable to a working day: the shift widened by attendance.punch_window_minutes, or by the
     * attendance rules' check-in / check-out windows when those reach further.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function window(ScheduledShiftDay $day): array
    {
        $minutes = (int) HrSettings::get($this->centerId, 'attendance.punch_window_minutes');
        $from = $day->startAt->subMinutes($minutes);
        $to = $day->endAt->addMinutes($minutes);
        if ($day->rules !== null) {
            $from = $from->min($day->rules->checkInWindow($day)[0]);
            $to = $to->max($day->rules->checkOutWindow($day)[1]);
        }

        return [$from, $to];
    }

    /** The record must not overlap the employee's records of the previous or next day. */
    private function assertNoOverlap(Employee $employee, string $date, ?CarbonImmutable $in, ?CarbonImmutable $out, ?AttendanceRecord $record, string $timezone): void
    {
        $start = $in ?? $out;
        $end = $out ?? $in;
        $day = CarbonImmutable::parse($date);

        $neighbours = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', '>=', $day->subDay()->toDateString())
            ->whereDate('attendance_date', '<=', $day->addDay()->toDateString())
            ->when($record !== null, fn ($query) => $query->whereKeyNot($record->id))
            ->get();

        foreach ($neighbours as $other) {
            $otherStart = $other->check_in_at ?? $other->check_out_at;
            $otherEnd = $other->check_out_at ?? ($other->isOpen() ? CarbonImmutable::now()->max($otherStart) : $other->check_in_at);
            if ($otherStart === null) {
                continue;
            }
            if ($otherStart->lt($end) && $start->lt($otherEnd)) {
                throw new HrException('attendance_overlaps_record', $in !== null ? 'check_in' : 'check_out', 422, [
                    'date' => $other->attendance_date->toDateString(),
                    'from' => $otherStart->copy()->setTimezone($timezone)->format('H:i'),
                    'to' => $otherEnd->copy()->setTimezone($timezone)->format('H:i'),
                ], ['record_id' => $other->id]);
            }
        }
    }

    private function assertNoRecord(Employee $employee, string $date): void
    {
        $existing = AttendanceRecord::query()->where('employee_id', $employee->id)->whereDate('attendance_date', $date)->first();
        if ($existing !== null) {
            throw new HrException('attendance_record_exists', 'attendance_date', 409, ['date' => $date], [
                'record_id' => $existing->id,
                'attendance_method' => $existing->attendance_method,
            ]);
        }
    }

    private function assertMayEdit(Employee $employee): void
    {
        if (! HrBranchScope::allows($this->branchScope, $employee->branch_id)) {
            throw new HrException('branch_not_authorized', 'employee_id', 403);
        }
        if ($this->actor !== null && (int) $employee->user_id === (int) $this->actor->id && ! can(self::OWN_RECORDS_PERMISSION, $this->actor)) {
            throw new HrException('attendance_own_record', 'employee_id', 403);
        }
    }

    /** Not in the future, not on or before the payroll lock, and within the allowed history unless authorized. */
    private function assertDateAllowed(string $date, string $timezone, CarbonImmutable $now): void
    {
        $today = $now->setTimezone($timezone)->toDateString();
        if ($date > $today) {
            throw new HrException('attendance_date_in_future', 'attendance_date');
        }

        $locked = HrEffectiveDating::lockedUntil($this->centerId);
        if ($locked !== null && $date <= $locked->toDateString()) {
            throw new HrException('attendance_date_locked', 'attendance_date', 422, ['date' => $locked->toDateString()]);
        }

        $days = (int) HrSettings::get($this->centerId, 'attendance.manual_entry_max_days_back');
        $oldest = CarbonImmutable::parse($today)->subDays($days)->toDateString();
        if ($date < $oldest && ! $this->canEnterHistorical()) {
            throw new HrException('attendance_date_too_old', 'attendance_date', 403, ['days' => $days, 'date' => $oldest]);
        }
    }

    private function assertManualAllowed(AttendanceRuleSet $rules): void
    {
        if ($rules->attendanceMode === Employee::ATTENDANCE_EXEMPT) {
            throw new HrException('attendance_employee_exempt', 'employee_id');
        }
        if (! $rules->allowManualAttendance) {
            throw new HrException('attendance_manual_not_allowed', 'employee_id');
        }
    }

    /** Writes the planned times and the calculation onto the record; $setIn / $setOut: which side HR entered. */
    private function apply(AttendanceRecord $record, array $plan, bool $setIn, bool $setOut): void
    {
        /** @var ScheduledShiftDay $day */
        $day = $plan['day'];
        $in = $plan['check_in_at'];
        $out = $plan['check_out_at'];
        $evaluation = $plan['evaluation'];
        $rules = $plan['rules'];

        if ($record->isManual() || ! $record->exists) {
            $record->fill([
                'shift_id' => $day->isWorking() ? $day->shift?->shiftId : null,
                'scheduled_start_at' => $day->isWorking() ? self::normalize($day->startAt) : null,
                'scheduled_end_at' => $day->isWorking() ? self::normalize($day->endAt) : null,
                'shift_overridden' => $plan['shift_overridden'],
            ]);
        }
        if ($setIn) {
            $record->fill([
                'check_in_at' => $in !== null ? self::normalize($in) : null,
                'check_in_verification' => $in !== null ? HrGeofence::MANUAL : null,
            ]);
        }
        if ($setOut) {
            $record->fill([
                'check_out_at' => $out !== null ? self::normalize($out) : null,
                'check_out_verification' => $out !== null ? HrGeofence::MANUAL : null,
            ]);
            $record->removeReviewReasons([AttendanceRecord::REVIEW_MISSING_CHECK_OUT, AttendanceRecord::REVIEW_AUTO_CHECK_OUT]);
        }

        $record->fill([
            'status' => match (true) {
                $in !== null && $out !== null => AttendanceRecord::STATUS_CHECKED_OUT,
                $in !== null => AttendanceRecord::STATUS_MISSING_CHECK_OUT,
                default => AttendanceRecord::STATUS_MISSING_CHECK_IN,
            },
            'open_session_key' => null,
            'late_minutes' => $in !== null ? $evaluation['late_minutes'] : 0,
            'early_departure_minutes' => $out !== null ? $evaluation['early_departure_minutes'] : 0,
            'worked_minutes' => $in !== null && $out !== null ? $evaluation['worked_minutes'] : null,
        ]);
        if ($out === null && ! $record->isManual()) {
            $record->addReviewReasons([AttendanceRecord::REVIEW_MISSING_CHECK_OUT]);
        }

        $record->removeReviewReasons([AttendanceRecord::REVIEW_LATE_JUSTIFICATION, AttendanceRecord::REVIEW_EARLY_JUSTIFICATION]);
        $record->addReviewReasons([
            $record->late_minutes > 0 && $rules->lateAction === AttendancePolicy::ACTION_REQUIRE_JUSTIFICATION ? AttendanceRecord::REVIEW_LATE_JUSTIFICATION : null,
            $record->early_departure_minutes > 0 && $rules->earlyLeaveAction === AttendancePolicy::ACTION_REQUIRE_JUSTIFICATION ? AttendanceRecord::REVIEW_EARLY_JUSTIFICATION : null,
        ]);
        $record->attendance_status = AttendanceRecord::attendanceStatusFor($record);
    }

    /** @return string[] */
    private function warnings(ScheduledShiftDay $day, AttendanceRuleSet $rules, bool $overridden, ?CarbonImmutable $in, ?CarbonImmutable $out, array $evaluation, string $date, string $timezone, CarbonImmutable $now, ?AttendanceRecord $record): array
    {
        $days = (int) HrSettings::get($this->centerId, 'attendance.manual_entry_max_days_back');
        $oldest = CarbonImmutable::parse($now->setTimezone($timezone)->toDateString())->subDays($days)->toDateString();

        return array_values(array_filter([
            $overridden ? 'shift_overridden' : null,
            ! $day->isWorking() ? 'off_schedule' : null,
            $record === null && $rules->attendanceMode === Employee::ATTENDANCE_ELECTRONIC ? 'electronic_employee' : null,
            $in === null && $out !== null ? 'missing_check_in' : null,
            $out === null && $in !== null ? 'missing_check_out' : null,
            ($in !== null && $evaluation['is_late'] && $rules->lateAction === AttendancePolicy::ACTION_REQUIRE_JUSTIFICATION)
                || ($out !== null && $evaluation['is_early_departure'] && $rules->earlyLeaveAction === AttendancePolicy::ACTION_REQUIRE_JUSTIFICATION)
                ? 'requires_justification' : null,
            $date < $oldest ? 'historical' : null,
        ]));
    }

    /** What the form shows before saving. */
    private function describe(array $plan): array
    {
        /** @var ScheduledShiftDay $scheduled */
        $scheduled = $plan['scheduled'];
        /** @var ScheduledShiftDay $day */
        $day = $plan['day'];
        $in = $plan['check_in_at'];
        $out = $plan['check_out_at'];
        $evaluation = $plan['evaluation'];
        $record = new AttendanceRecord([
            'status' => $in !== null && $out !== null ? AttendanceRecord::STATUS_CHECKED_OUT : AttendanceRecord::STATUS_MISSING_CHECK_OUT,
            'check_in_at' => $in,
            'check_out_at' => $out,
            'scheduled_start_at' => $day->isWorking() ? $day->startAt : null,
            'late_minutes' => $in !== null ? $evaluation['late_minutes'] : 0,
            'early_departure_minutes' => $out !== null ? $evaluation['early_departure_minutes'] : 0,
        ]);

        return [
            'employee' => [
                'id' => $plan['employee']->id,
                'employee_number' => $plan['employee']->employee_number,
                'name' => $plan['employee']->user?->name,
            ],
            'attendance_date' => $plan['date'],
            'timezone' => $plan['timezone'],
            'attendance_mode' => $plan['rules']->attendanceMode,
            'scheduled_day' => $scheduled->toArray(),
            'day' => $day->toArray(),
            'shift_overridden' => $plan['shift_overridden'],
            'check_in' => self::moment($in),
            'check_out' => self::moment($out),
            'duration_minutes' => $in !== null && $out !== null ? HrShiftMath::minutesBetween($in, $out) : null,
            'worked_minutes' => $in !== null && $out !== null ? $evaluation['worked_minutes'] : null,
            'late_minutes' => $record->late_minutes,
            'early_departure_minutes' => $record->early_departure_minutes,
            'break_minutes' => $evaluation['break_minutes'],
            'outside_shift_minutes' => $evaluation['outside_shift_minutes'],
            'attendance_status' => $in !== null || $out !== null ? AttendanceRecord::attendanceStatusFor($record) : null,
            'warnings' => $plan['warnings'],
        ];
    }

    private static function moment(?CarbonImmutable $at): ?array
    {
        return $at === null ? null : ['at' => $at->toIso8601String(), 'date' => $at->toDateString(), 'time' => $at->format('H:i')];
    }

    /** @return array<string, mixed> */
    private function snapshot(AttendanceRecord $record): array
    {
        $timezone = HrElectronicAttendance::timezoneFor($record->employee);
        $local = fn (?CarbonInterface $at) => $at?->copy()->setTimezone($timezone)->format('Y-m-d H:i');
        $shift = $record->shift_id !== null ? Shift::withTrashed()->find($record->shift_id) : null;

        return [
            'shift' => $shift !== null ? trim($shift->code.' '.$shift->name) : null,
            'check_in' => $local($record->check_in_at),
            'check_out' => $local($record->check_out_at),
            'status' => $record->status,
            'attendance_status' => AttendanceRecord::attendanceStatusFor($record),
            'late_minutes' => (int) $record->late_minutes,
            'early_departure_minutes' => (int) $record->early_departure_minutes,
            'worked_minutes' => $record->worked_minutes !== null ? (int) $record->worked_minutes : null,
            'entry_reason' => $record->entry_reason,
            'entry_reason_notes' => $record->entry_reason_notes,
            'notes' => $record->notes,
        ];
    }

    /** @return array{0: array, 1: array} changed fields only */
    private function diff(array $before, array $after): array
    {
        $old = [];
        $new = [];
        foreach (self::TRACKED as $key) {
            if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
                $old[$key] = $before[$key] ?? null;
                $new[$key] = $after[$key] ?? null;
            }
        }

        return [$old, $new];
    }

    private function log(AttendanceRecord $record, string $action, ?array $old, array $new, string $source, string $reason): void
    {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();
        if ($old === null) {
            $new = array_filter($new, fn ($value) => $value !== null);
        }

        AttendanceRecordChange::create([
            'center_id' => $record->center_id,
            'attendance_record_id' => $record->id,
            'employee_id' => $record->employee_id,
            'action' => $action,
            'source' => $source,
            'entry_reason' => $record->entry_reason,
            'reason' => $reason,
            'old_values' => $old,
            'new_values' => $new,
            'changed_by' => $this->actor?->id,
            'via_impersonation' => $this->actor !== null && isImpersonating($this->actor),
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
        ]);

        HrAuditLogger::record(
            $record,
            $old === null ? HrAuditLogger::EVENT_CREATED : HrAuditLogger::EVENT_UPDATED,
            $old,
            $new + ['reason' => $reason],
        );
    }

    private function creationReason(AttendanceRecord $record): string
    {
        $label = __('tr.hr.attendance_entry_reasons.'.$record->entry_reason);

        return $record->entry_reason_notes ? "{$label}: {$record->entry_reason_notes}" : $label;
    }

    private function employee(mixed $id): Employee
    {
        $employee = is_numeric($id)
            ? Employee::query()->forCenter($this->centerId)->with(['branch', 'user', 'employmentPeriods'])->find((int) $id)
            : null;
        if ($employee === null || ! HrBranchScope::allows($this->branchScope, $employee->branch_id)) {
            throw new HrException('attendance_employee_not_found', 'employee_id');
        }

        return $employee;
    }

    private function recordEmployee(AttendanceRecord $record): Employee
    {
        $employee = Employee::withTrashed()->with(['branch', 'user', 'employmentPeriods'])->findOrFail($record->employee_id);
        $record->setRelation('employee', $employee);

        return $employee;
    }

    private function save(AttendanceRecord $record): void
    {
        try {
            $record->save();
        } catch (QueryException $e) {
            if (in_array((string) $e->getCode(), ['23000', '23505'], true) || str_contains($e->getMessage(), 'UNIQUE')) {
                throw new HrException('attendance_record_exists', 'attendance_date', 409, ['date' => $record->attendance_date?->toDateString() ?? '']);
            }

            throw $e;
        }
    }

    private static function sameInstant(?CarbonInterface $a, ?CarbonInterface $b): bool
    {
        return $a === null || $b === null ? $a === $b : $a->getTimestamp() === $b->getTimestamp();
    }

    private static function at(string $date, string $time, string $timezone): CarbonImmutable
    {
        $at = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && preg_match('/^([01]\d|2[0-3]):[0-5]\d/', $time)
            ? CarbonImmutable::createFromFormat('Y-m-d H:i', $date.' '.substr($time, 0, 5), $timezone)
            : false;
        if ($at === false) {
            throw new HrException('attendance_time_invalid', 'check_in');
        }

        return $at->startOfMinute();
    }

    private static function text(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }

    /** Datetimes are stored and compared in the application timezone (Eloquent does not convert). */
    private static function normalize(CarbonInterface $at): CarbonImmutable
    {
        return CarbonImmutable::instance($at)->setTimezone(config('app.timezone'));
    }
}
