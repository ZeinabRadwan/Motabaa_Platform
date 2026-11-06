<?php

namespace App\Support\Hr;

use App\Models\Hr\AttendancePolicy;
use App\Models\Hr\AttendancePunch;
use App\Models\Hr\AttendanceRecord;
use App\Models\Hr\Employee;
use App\Models\Hr\WorkLocation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Website (browser GPS) check-in and check-out of one employee.
 *
 *  - Only employees whose attendance method is electronic on the attendance date may punch; manual and exempt
 *    employees are refused. A day already recorded manually by HR (HrManualAttendance) cannot be punched.
 *  - Punch times are the server clock; dates and windows are local to the employee's branch timezone
 *    (general.timezone when the branch has none). Stored datetimes use the application timezone.
 *  - A check-in belongs to the working day (yesterday's overnight shift or today's) whose check-in window
 *    contains it, and is refused outside every window. One record per employee and attendance date, and at most
 *    one open session per employee (row lock + unique index).
 *  - The GPS fix is checked against the work location in force on the attendance date (HrGeofence): "required"
 *    rejects unverified attempts, "flexible" accepts and flags them, "none" records without verifying. A GPS
 *    failure is never turned into an absence: the attempt is only logged.
 *  - A repeated check-in inside the policy's duplicate window returns the existing record (safe retry after a
 *    network failure). A second check-out follows the duplicate punch action: first_in_last_out moves the
 *    check-out, flag_for_review moves it and flags the record, reject refuses it.
 *  - An open session past its check-out window is closed by the policy's missing check-out action: auto check-out
 *    at the shift end, or left without a check-out and flagged (mark_absent is stored for attendance processing).
 *  - Every attempt (accepted, rejected, duplicate) is logged in hr_attendance_punches.
 *  - An accepted check-in starts the periodic location checks of the session and the check-out (or the missing
 *    check-out action) stops them (HrLocationMonitor).
 */
final class HrElectronicAttendance
{
    private readonly string $timezone;

    public function __construct(private readonly Employee $employee)
    {
        $this->timezone = self::timezoneFor($employee);
    }

    /** The signed-in user's employee profile in the center, if any. */
    public static function employeeFor(User $user, int $centerId): ?Employee
    {
        return Employee::query()->forCenter($centerId)->where('user_id', $user->id)->with(['branch', 'user'])->first();
    }

    public static function timezoneFor(Employee $employee): string
    {
        return $employee->branch?->timezone
            ?: (string) (HrSettings::get($employee->center_id, 'general.timezone') ?: config('app.timezone'));
    }

    /** Closes every open session past its check-out window (scheduler). */
    public static function closeExpiredSessions(CarbonInterface $now, ?int $centerId = null): int
    {
        $now = self::normalize($now);
        $employeeIds = AttendanceRecord::query()
            ->open()
            ->where('check_out_deadline_at', '<', $now)
            ->when($centerId !== null, fn ($query) => $query->where('center_id', $centerId))
            ->distinct()
            ->pluck('employee_id');

        $closed = 0;
        Employee::query()->with('branch')->whereIn('id', $employeeIds)->get()->each(function (Employee $employee) use ($now, &$closed) {
            $service = new self($employee);
            $closed += DB::transaction(fn () => $service->lockEmployee()->closeExpired($now));
        });

        return $closed;
    }

    /**
     * What the attendance page shows: the scheduled day in question, its record, the work location, the windows
     * and which action is available now.
     *
     * @return array<string, mixed> `record` is an AttendanceRecord model (or null)
     */
    public function status(CarbonInterface $now): array
    {
        $now = self::normalize($now);
        DB::transaction(function () use ($now) {
            $this->lockEmployee()->closeExpired($now);
            $this->monitor()->catchUp($now);
        });

        [$day, $record] = $this->current($now);
        $rules = $this->rulesFor($day);
        $ineligible = $this->ineligibility($rules);
        $location = $record !== null ? $record->workLocation : $this->employee->effectiveWorkLocation($day->date);
        $checkIn = $day->isWorking() ? $rules->checkInWindow($day) : null;
        $checkOut = $day->isWorking() ? $rules->checkOutWindow($day) : null;

        $canCheckIn = $ineligible === null && $record === null && $checkIn !== null && $this->inside($now, $checkIn);
        $canCheckOut = $ineligible === null && $record !== null && ($record->isOpen()
            ? $checkOut === null || $now->gte($checkOut[0])
            : $this->canCheckOutAgain($record, $rules, $now));

        return [
            'server_time' => $this->local($now)->toIso8601String(),
            'timezone' => $this->timezone,
            'today' => $this->localDate($now),
            'eligible' => $ineligible === null,
            'ineligible_reason' => $ineligible,
            'employee' => [
                'id' => $this->employee->id,
                'employee_number' => $this->employee->employee_number,
                'name' => $this->employee->user?->name,
            ],
            'attendance_mode' => $rules->attendanceMode,
            'gps_requirement' => $rules->gpsRequirement,
            'max_accuracy_meters' => $this->maxAccuracy(),
            'day' => $day->toArray(),
            'check_in_window' => $this->window($checkIn),
            'check_out_window' => $this->window($checkOut),
            'work_location' => $this->locationSummary($location, $record),
            'record' => $record,
            'can_check_in' => $canCheckIn,
            'can_check_out' => $canCheckOut,
            'hint' => $ineligible === null ? $this->hint($day, $record, $checkIn, $checkOut, $now) : null,
            'location_monitoring' => $this->monitor()->present($record, $now),
        ];
    }

    public function monitor(): HrLocationMonitor
    {
        return new HrLocationMonitor($this->employee);
    }

    /**
     * @return array{record: AttendanceRecord, duplicate: bool, verification: ?string}
     *
     * @throws HrException
     */
    public function checkIn(AttendancePunchInput $input, CarbonInterface $now): array
    {
        $now = self::normalize($now);
        $punch = $this->newPunch(AttendancePunch::TYPE_CHECK_IN, $input, $now);

        return $this->attempt($punch, function () use ($input, $now, $punch) {
            $this->closeExpired($now);

            $open = $this->openRecord();
            if ($open !== null) {
                $punch->fill(['attendance_record_id' => $open->id, 'attendance_date' => $open->attendance_date]);
                if ($this->isDuplicate($open->check_in_at, $now, $this->rulesFor($this->dayOf($open)))) {
                    return $this->duplicate($punch, $open);
                }
                throw new HrException('attendance_already_checked_in', 'error', 422, [
                    'date' => $open->attendance_date->toDateString(),
                    'time' => $this->localTime($open->check_in_at),
                ]);
            }

            $day = $this->checkInDay($now);
            $rules = $day?->rules ?? HrAttendanceRules::on($this->employee, $this->localDate($now));
            $this->assertEligible($rules);
            if ($day === null) {
                throw $this->checkInWindowError($now);
            }

            $punch->attendance_date = $day->date;
            $existing = $this->recordOn($day->date);
            if ($existing !== null) {
                $punch->attendance_record_id = $existing->id;
                if ($existing->isManual()) {
                    throw new HrException('attendance_recorded_manually');
                }
                if ($this->isDuplicate($existing->check_in_at, $now, $rules)) {
                    return $this->duplicate($punch, $existing);
                }
                throw new HrException('attendance_already_recorded', 'error', 422, ['time' => $this->localTime($existing->check_in_at)]);
            }

            $location = $this->employee->effectiveWorkLocation($day->date);
            $check = $this->verify($rules->gpsRequirement, $location, $input, $punch);
            $evaluation = HrShiftCalculator::evaluate($day, $now, null);

            $record = new AttendanceRecord([
                'center_id' => $this->employee->center_id,
                'employee_id' => $this->employee->id,
                'attendance_date' => $day->date,
                'attendance_method' => AttendanceRecord::METHOD_ELECTRONIC,
                'status' => AttendanceRecord::STATUS_CHECKED_IN,
                'open_session_key' => $this->employee->id,
                'shift_id' => $day->shift?->shiftId,
                'scheduled_start_at' => self::normalize($day->startAt),
                'scheduled_end_at' => self::normalize($day->endAt),
                'attendance_config_id' => $rules->configId,
                'attendance_policy_id' => $rules->policyId,
                'attendance_policy_version_id' => $rules->policyVersionId,
                'gps_requirement' => $rules->gpsRequirement,
                'missing_checkout_action' => $rules->missingCheckoutAction,
                'check_out_deadline_at' => self::normalize($rules->checkOutWindow($day)[1]),
                'work_location_id' => $location?->id,
                'location_radius_meters' => $check->radiusMeters,
                'check_in_at' => $now,
                'check_in_latitude' => $input->latitude,
                'check_in_longitude' => $input->longitude,
                'check_in_accuracy_meters' => $input->accuracy,
                'check_in_distance_meters' => $check->distanceMeters,
                'check_in_verification' => $check->status,
                'check_in_ip' => $input->ip,
                'check_in_user_agent' => $input->userAgent,
                'late_minutes' => $evaluation['late_minutes'],
            ]);
            $record->addReviewReasons([
                $check->flagged ? $check->status : null,
                $evaluation['is_late'] && $rules->lateAction === AttendancePolicy::ACTION_REQUIRE_JUSTIFICATION ? AttendanceRecord::REVIEW_LATE_JUSTIFICATION : null,
            ]);
            $this->save($record);
            $this->monitor()->start($record, $check, LocationReading::fromPunch($input), $now);

            return $this->accepted($punch, $record, $check);
        });
    }

    /**
     * @return array{record: AttendanceRecord, duplicate: bool, verification: ?string}
     *
     * @throws HrException
     */
    public function checkOut(AttendancePunchInput $input, CarbonInterface $now): array
    {
        $now = self::normalize($now);
        $punch = $this->newPunch(AttendancePunch::TYPE_CHECK_OUT, $input, $now);

        return $this->attempt($punch, function () use ($input, $now, $punch) {
            $this->closeExpired($now);

            $repeated = false;
            $record = $this->openRecord();
            if ($record === null) {
                $record = $this->lastCheckedOut($now);
                if ($record === null) {
                    $this->assertEligible(HrAttendanceRules::on($this->employee, $this->localDate($now)));
                    throw new HrException($this->recentlyMissed($now) ? 'attendance_check_out_closed' : 'attendance_not_checked_in');
                }
                $repeated = true;
            }

            $punch->fill(['attendance_record_id' => $record->id, 'attendance_date' => $record->attendance_date]);
            $day = $this->dayOf($record);
            $rules = $this->rulesFor($day);
            $this->assertEligible($rules);

            if ($repeated) {
                if ($this->isDuplicate($record->check_out_at, $now, $rules)) {
                    return $this->duplicate($punch, $record);
                }
                if ($rules->duplicatePunchAction === AttendancePolicy::DUPLICATE_REJECT) {
                    throw new HrException('attendance_already_checked_out', 'error', 422, ['time' => $this->localTime($record->check_out_at)]);
                }
            }

            $window = $day->isWorking() ? $rules->checkOutWindow($day) : null;
            if ($window !== null && $now->lt($window[0])) {
                throw new HrException('attendance_check_out_not_open', 'error', 422, ['time' => $this->localTime($window[0])], [
                    'opens_at' => $this->local($window[0])->toIso8601String(),
                ]);
            }

            $check = $this->verify($record->gps_requirement, $record->workLocation, $input, $punch);
            $evaluation = HrShiftCalculator::evaluate($day, $record->check_in_at, $now);

            $record->fill([
                'status' => AttendanceRecord::STATUS_CHECKED_OUT,
                'open_session_key' => null,
                'check_out_at' => $now,
                'check_out_latitude' => $input->latitude,
                'check_out_longitude' => $input->longitude,
                'check_out_accuracy_meters' => $input->accuracy,
                'check_out_distance_meters' => $check->distanceMeters,
                'check_out_verification' => $check->status,
                'check_out_ip' => $input->ip,
                'check_out_user_agent' => $input->userAgent,
                'early_departure_minutes' => $evaluation['early_departure_minutes'],
                'worked_minutes' => $evaluation['worked_minutes'],
            ]);
            $record->addReviewReasons([
                $check->flagged ? $check->status : null,
                $evaluation['is_early_departure'] && $rules->earlyLeaveAction === AttendancePolicy::ACTION_REQUIRE_JUSTIFICATION ? AttendanceRecord::REVIEW_EARLY_JUSTIFICATION : null,
                $repeated && $rules->duplicatePunchAction === AttendancePolicy::DUPLICATE_FLAG ? AttendanceRecord::REVIEW_REPEATED_CHECK_OUT : null,
            ]);
            $this->save($record);
            $this->monitor()->stop($record, $now, $check, LocationReading::fromPunch($input));

            return $this->accepted($punch, $record, $check);
        });
    }

    /** Applies the missing check-out action to this employee's open sessions past their check-out window. */
    public function closeExpired(CarbonInterface $now): int
    {
        $expired = AttendanceRecord::query()
            ->where('employee_id', $this->employee->id)
            ->open()
            ->where('check_out_deadline_at', '<', self::normalize($now))
            ->get();

        foreach ($expired as $record) {
            if ($record->missing_checkout_action === AttendancePolicy::MISSING_CHECKOUT_AUTO && $record->scheduled_end_at !== null) {
                $checkOut = $record->scheduled_end_at->max($record->check_in_at);
                $evaluation = HrShiftCalculator::evaluate($this->dayOf($record), $record->check_in_at, $checkOut);
                $record->fill([
                    'status' => AttendanceRecord::STATUS_CHECKED_OUT,
                    'check_out_at' => $checkOut,
                    'check_out_verification' => HrGeofence::AUTO_CLOSED,
                    'early_departure_minutes' => $evaluation['early_departure_minutes'],
                    'worked_minutes' => $evaluation['worked_minutes'],
                ]);
                $record->addReviewReasons([AttendanceRecord::REVIEW_AUTO_CHECK_OUT]);
            } else {
                $record->status = AttendanceRecord::STATUS_MISSING_CHECK_OUT;
                $record->addReviewReasons([AttendanceRecord::REVIEW_MISSING_CHECK_OUT]);
            }
            $record->open_session_key = null;
            $record->save();
            $this->monitor()->stop($record, $record->check_out_at
                ?? ($record->scheduled_end_at !== null ? self::normalize($record->scheduled_end_at)->min(self::normalize($now)) : $now));
        }

        return $expired->count();
    }

    /** Serializes punches of this employee until the surrounding transaction ends. */
    private function lockEmployee(): self
    {
        Employee::query()->whereKey($this->employee->id)->lockForUpdate()->first();

        return $this;
    }

    private function attempt(AttendancePunch $punch, callable $action): array
    {
        try {
            return DB::transaction(function () use ($action) {
                $this->lockEmployee();

                return $action();
            });
        } catch (HrException $e) {
            $punch->fill(['result' => AttendancePunch::RESULT_REJECTED, 'reason' => $e->reason])->save();

            throw $e;
        }
    }

    /** @return array{0: ScheduledShiftDay, 1: ?AttendanceRecord} */
    private function current(CarbonImmutable $now): array
    {
        $open = $this->openRecord();
        if ($open !== null) {
            return [$this->dayOf($open), $open];
        }

        $candidate = $this->checkInDay($now);
        if ($candidate !== null) {
            return [$candidate, $this->recordOn($candidate->date)];
        }

        // Just after a check-out (e.g. 06:05 after a night shift) the closed record stays in view until its
        // check-out window ends.
        $recent = AttendanceRecord::query()
            ->where('employee_id', $this->employee->id)
            ->where('check_out_deadline_at', '>=', $now)
            ->latest('check_in_at')
            ->first();
        if ($recent !== null) {
            return [$this->dayOf($recent), $recent];
        }

        $today = HrShiftSchedule::dayFor($this->employee, $this->localDate($now));
        $record = $this->recordOn($today->date);
        if ($record === null && ! $today->isWorking()) {
            $previous = $this->recordOn($this->local($now)->subDay()->toDateString());
            if ($previous !== null) {
                return [$this->dayOf($previous), $previous];
            }
        }

        return [$today, $record];
    }

    /**
     * The working day (yesterday's or today's) whose check-in window contains $now; a day without a record
     * wins over one already recorded, then the nearer shift.
     */
    private function checkInDay(CarbonImmutable $now): ?ScheduledShiftDay
    {
        $local = $this->local($now);
        $days = HrShiftSchedule::forEmployee($this->employee, $local->subDay()->toDateString(), $local->toDateString());
        $recorded = AttendanceRecord::query()
            ->where('employee_id', $this->employee->id)
            ->whereDate('attendance_date', '>=', $local->subDay()->toDateString())
            ->pluck('attendance_date')
            ->map(fn ($date) => substr((string) $date, 0, 10))
            ->all();

        $best = null;
        $bestKey = null;
        foreach ($days as $day) {
            $window = $day->isWorking() ? $day->rules?->checkInWindow($day) : null;
            if ($window === null || ! $this->inside($now, $window)) {
                continue;
            }
            $key = [in_array($day->date, $recorded, true) ? 1 : 0, $day->distanceMinutes($now)];
            if ($bestKey === null || $key < $bestKey) {
                [$best, $bestKey] = [$day, $key];
            }
        }

        return $best;
    }

    private function checkInWindowError(CarbonImmutable $now): HrException
    {
        $today = HrShiftSchedule::dayFor($this->employee, $this->localDate($now));
        $window = $today->isWorking() ? $this->rulesFor($today)->checkInWindow($today) : null;
        if ($window === null) {
            return new HrException('attendance_no_shift');
        }
        if ($now->lt($window[0])) {
            return new HrException('attendance_check_in_not_open', 'error', 422, ['time' => $this->localTime($window[0])], [
                'opens_at' => $this->local($window[0])->toIso8601String(),
            ]);
        }

        return new HrException('attendance_check_in_closed', 'error', 422, ['time' => $this->localTime($window[1])]);
    }

    private function verify(string $requirement, ?WorkLocation $location, AttendancePunchInput $input, AttendancePunch $punch): HrGeofence
    {
        $site = $location !== null && $location->hasCoordinates() ? [
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'radius' => $location->radius_meters ?: (int) HrSettings::get($this->employee->center_id, 'attendance.default_gps_radius_meters'),
        ] : null;
        $max = $this->maxAccuracy();
        $check = HrGeofence::check($requirement, $site, $input->fix(), $max);
        $punch->fill(['work_location_id' => $location?->id, 'distance_meters' => $check->distanceMeters, 'verification' => $check->status]);

        if ($check->accepted) {
            return $check;
        }

        $reason = match ($check->status) {
            HrGeofence::OUTSIDE_RADIUS => 'attendance_outside_radius',
            HrGeofence::LOW_ACCURACY => 'attendance_low_accuracy',
            HrGeofence::LOCATION_NOT_CONFIGURED => 'attendance_location_not_configured',
            default => $input->locationError === 'permission_denied' ? 'attendance_location_denied' : 'attendance_location_unavailable',
        };

        throw new HrException($reason, 'location', 422, [
            'distance' => (int) round((float) $check->distanceMeters),
            'radius' => (int) $check->radiusMeters,
            'accuracy' => (int) round((float) $check->accuracyMeters),
            'max' => $max,
        ], [
            'verification' => $check->status,
            'distance_meters' => $check->distanceMeters,
            'radius_meters' => $check->radiusMeters,
            'accuracy_meters' => $check->accuracyMeters,
            'max_accuracy_meters' => $max,
        ]);
    }

    private function assertEligible(AttendanceRuleSet $rules): void
    {
        $reason = $this->ineligibility($rules);
        if ($reason !== null) {
            throw new HrException("attendance_ineligible_{$reason}", 'error', 403);
        }
    }

    /** Why the employee cannot punch electronically under $rules (null when they can). */
    private function ineligibility(AttendanceRuleSet $rules): ?string
    {
        return match (true) {
            ! $this->employee->isEmployed() => 'not_employed',
            $rules->attendanceMode === Employee::ATTENDANCE_MANUAL => 'manual',
            $rules->attendanceMode === Employee::ATTENDANCE_EXEMPT => 'exempt',
            default => null,
        };
    }

    private function canCheckOutAgain(AttendanceRecord $record, AttendanceRuleSet $rules, CarbonImmutable $now): bool
    {
        return $record->status === AttendanceRecord::STATUS_CHECKED_OUT
            && $record->check_out_verification !== HrGeofence::AUTO_CLOSED
            && $rules->duplicatePunchAction !== AttendancePolicy::DUPLICATE_REJECT
            && $record->check_out_deadline_at !== null
            && $now->lte($record->check_out_deadline_at);
    }

    private function hint(ScheduledShiftDay $day, ?AttendanceRecord $record, ?array $checkIn, ?array $checkOut, CarbonImmutable $now): ?array
    {
        if ($record !== null) {
            if ($record->isManual()) {
                return ['key' => 'recorded_manually'];
            }
            if ($record->isOpen()) {
                return $checkOut !== null && $now->lt($checkOut[0]) ? ['key' => 'check_out_opens', 'time' => $this->localTime($checkOut[0])] : null;
            }

            return ['key' => $record->status === AttendanceRecord::STATUS_MISSING_CHECK_OUT ? 'missing_check_out' : 'checked_out'];
        }
        if ($checkIn === null) {
            return ['key' => $day->status === ScheduledShiftDay::STATUS_NOT_EMPLOYED ? 'not_employed_yet' : 'no_shift'];
        }
        if ($now->lt($checkIn[0])) {
            return ['key' => 'check_in_opens', 'time' => $this->localTime($checkIn[0])];
        }
        if ($now->gt($checkIn[1])) {
            return ['key' => 'check_in_closed', 'time' => $this->localTime($checkIn[1])];
        }

        return null;
    }

    private function locationSummary(?WorkLocation $location, ?AttendanceRecord $record): ?array
    {
        if ($location === null) {
            return null;
        }

        return [
            'id' => $location->id,
            'name' => $location->name,
            'address' => $location->address,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'radius_meters' => $record?->location_radius_meters
                ?? ($location->radius_meters ?: (int) HrSettings::get($this->employee->center_id, 'attendance.default_gps_radius_meters')),
            'has_coordinates' => $location->hasCoordinates(),
        ];
    }

    private function window(?array $window): ?array
    {
        return $window === null ? null : [
            'from' => $this->local($window[0])->toIso8601String(),
            'to' => $this->local($window[1])->toIso8601String(),
            'from_time' => $this->localTime($window[0]),
            'to_time' => $this->localTime($window[1]),
        ];
    }

    private function openRecord(): ?AttendanceRecord
    {
        return AttendanceRecord::query()->where('employee_id', $this->employee->id)->open()->latest('check_in_at')->first();
    }

    private function recordOn(string $date): ?AttendanceRecord
    {
        return AttendanceRecord::query()->where('employee_id', $this->employee->id)->whereDate('attendance_date', $date)->first();
    }

    /** The latest record checked out by the employee whose check-out window is still open. */
    private function lastCheckedOut(CarbonImmutable $now): ?AttendanceRecord
    {
        return AttendanceRecord::query()
            ->where('employee_id', $this->employee->id)
            ->where('status', AttendanceRecord::STATUS_CHECKED_OUT)
            ->where('check_out_verification', '!=', HrGeofence::AUTO_CLOSED)
            ->where('check_out_deadline_at', '>=', $now)
            ->latest('check_in_at')
            ->first();
    }

    /** Whether a session of today or yesterday was closed because its check-out window passed. */
    private function recentlyMissed(CarbonImmutable $now): bool
    {
        return AttendanceRecord::query()
            ->where('employee_id', $this->employee->id)
            ->where('attendance_method', AttendanceRecord::METHOD_ELECTRONIC)
            ->where('status', AttendanceRecord::STATUS_MISSING_CHECK_OUT)
            ->whereDate('attendance_date', '>=', $this->local($now)->subDay()->toDateString())
            ->exists();
    }

    private function dayOf(AttendanceRecord $record): ScheduledShiftDay
    {
        return HrShiftSchedule::dayFor($this->employee, $record->attendance_date->toDateString());
    }

    private function rulesFor(ScheduledShiftDay $day): AttendanceRuleSet
    {
        return $day->rules ?? HrAttendanceRules::on($this->employee, $day->date);
    }

    private function isDuplicate(?CarbonInterface $previous, CarbonImmutable $now, AttendanceRuleSet $rules): bool
    {
        return $previous !== null && abs($now->getTimestamp() - $previous->getTimestamp()) <= $rules->duplicateWindowMinutes * 60;
    }

    private function newPunch(string $type, AttendancePunchInput $input, CarbonImmutable $now): AttendancePunch
    {
        return new AttendancePunch([
            'center_id' => $this->employee->center_id,
            'employee_id' => $this->employee->id,
            'type' => $type,
            'attempted_at' => $now,
            'latitude' => $input->latitude,
            'longitude' => $input->longitude,
            'accuracy_meters' => $input->accuracy,
            'location_error' => $input->fix() === null ? $input->locationError : null,
            'ip_address' => $input->ip,
            'user_agent' => $input->userAgent,
        ]);
    }

    private function duplicate(AttendancePunch $punch, AttendanceRecord $record): array
    {
        $punch->fill(['result' => AttendancePunch::RESULT_DUPLICATE, 'attendance_record_id' => $record->id])->save();

        return ['record' => $record, 'duplicate' => true, 'verification' => null];
    }

    private function accepted(AttendancePunch $punch, AttendanceRecord $record, HrGeofence $check): array
    {
        $punch->fill(['result' => AttendancePunch::RESULT_ACCEPTED, 'attendance_record_id' => $record->id])->save();

        return ['record' => $record, 'duplicate' => false, 'verification' => $check->status];
    }

    private function save(AttendanceRecord $record): void
    {
        try {
            $record->save();
        } catch (QueryException $e) {
            if (in_array((string) $e->getCode(), ['23000', '23505'], true) || str_contains($e->getMessage(), 'UNIQUE')) {
                throw new HrException('attendance_concurrent_punch', 'error', 409);
            }

            throw $e;
        }
    }

    private function maxAccuracy(): int
    {
        return (int) HrSettings::get($this->employee->center_id, 'attendance.max_gps_accuracy_meters');
    }

    private function inside(CarbonInterface $at, array $window): bool
    {
        return $at->getTimestamp() >= $window[0]->getTimestamp() && $at->getTimestamp() <= $window[1]->getTimestamp();
    }

    private function local(CarbonInterface $at): CarbonImmutable
    {
        return CarbonImmutable::instance($at)->setTimezone($this->timezone);
    }

    private function localDate(CarbonInterface $at): string
    {
        return $this->local($at)->toDateString();
    }

    private function localTime(CarbonInterface $at): string
    {
        return $this->local($at)->format('H:i');
    }

    /** Datetimes are stored and compared in the application timezone (Eloquent does not convert). */
    private static function normalize(CarbonInterface $at): CarbonImmutable
    {
        return CarbonImmutable::instance($at)->setTimezone(config('app.timezone'));
    }
}
