<?php

namespace App\Support\Hr;

use App\Models\Hr\AttendancePolicy;
use App\Models\Hr\AttendanceRecord;
use App\Models\Hr\Employee;
use App\Models\Hr\LocationCheck;
use App\Models\Hr\LocationException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Periodic location verification of one employee's electronic attendance sessions.
 *
 *  - Monitoring starts with an accepted check-in when the session has a GPS requirement (required or flexible),
 *    its work location has coordinates and monitoring is enabled in the HR settings. The session keeps the interval
 *    in force at check-in. The check-in location is the session's first check.
 *  - Checks are due every interval while the session is open and the shift has not ended (HrLocationSchedule).
 *    Nothing is requested or accepted outside an open session: employees are not tracked outside work.
 *  - A due check that receives no location is stored as `pending` (source `system`) and stays pending: a missing
 *    location is never counted as a verification, and never turned into an absence.
 *  - Every received location is stored, whatever its result: within / outside the radius, permission denied, GPS
 *    unavailable, or verification failed (low accuracy, mocked location, no coordinates).
 *  - Consecutive checks outside the radius form one exception for HR, closed by a check inside the radius or the
 *    end of the session, and flag the record for review.
 *  - Monitoring stops at check-out (or when the session is closed for a missing check-out).
 *
 * Callers must hold a transaction; methods lock the employee row like HrElectronicAttendance does.
 */
final class HrLocationMonitor
{
    /** Mobile samples older than this are refused. */
    public const MAX_SAMPLE_AGE_HOURS = 24;

    /** How far a device clock may run ahead of the server. */
    public const MAX_FUTURE_SKEW_SECONDS = 120;

    public function __construct(private readonly Employee $employee)
    {
    }

    /** The check interval for sessions starting now in the center, or null when monitoring is disabled. */
    public static function intervalFor(int $centerId): ?int
    {
        if (! HrSettings::get($centerId, 'attendance.location_monitoring_enabled')) {
            return null;
        }

        return max(5, (int) HrSettings::get($centerId, 'attendance.location_check_interval_minutes'));
    }

    /** Why a session is not monitored: disabled, gps_not_required or no_work_location (null when it is). */
    public static function exclusionFor(AttendanceRecord $record, ?int $interval): ?string
    {
        $location = $record->workLocation;

        return match (true) {
            $interval === null => 'disabled',
            $record->gps_requirement === AttendancePolicy::GPS_NONE => 'gps_not_required',
            $location === null || ! $location->hasCoordinates() => 'no_work_location',
            default => null,
        };
    }

    /**
     * The location check status (and detail) for a geofence result and the client's geolocation error.
     *
     * @return array{0: string, 1: ?string}
     */
    public static function statusFor(HrGeofence $check, ?string $locationError, bool $mocked = false): array
    {
        if ($mocked) {
            return [LocationCheck::STATUS_VERIFICATION_FAILED, 'mocked_location'];
        }

        return match ($check->status) {
            HrGeofence::VERIFIED => [LocationCheck::STATUS_WITHIN_RADIUS, null],
            HrGeofence::OUTSIDE_RADIUS => [LocationCheck::STATUS_OUTSIDE_RADIUS, null],
            HrGeofence::LOW_ACCURACY => [LocationCheck::STATUS_VERIFICATION_FAILED, 'low_accuracy'],
            HrGeofence::LOCATION_NOT_CONFIGURED => [LocationCheck::STATUS_VERIFICATION_FAILED, 'location_not_configured'],
            HrGeofence::LOCATION_UNAVAILABLE => $locationError === 'permission_denied'
                ? [LocationCheck::STATUS_PERMISSION_DENIED, 'permission_denied']
                : [LocationCheck::STATUS_GPS_UNAVAILABLE, $locationError ?: 'position_unavailable'],
            default => [LocationCheck::STATUS_VERIFICATION_FAILED, $check->status],
        };
    }

    /** Stores the checks that fell due without a location in every open session (scheduler, dashboard). */
    public static function catchUpAll(CarbonInterface $now, ?int $centerId = null): int
    {
        $now = self::normalize($now);
        $employeeIds = AttendanceRecord::query()
            ->open()
            ->whereNotNull('next_location_check_at')
            ->where('next_location_check_at', '<=', $now)
            ->when($centerId !== null, fn ($query) => $query->where('center_id', $centerId))
            ->distinct()
            ->pluck('employee_id');

        $stored = 0;
        Employee::query()->whereIn('id', $employeeIds)->get()->each(function (Employee $employee) use ($now, &$stored) {
            $stored += DB::transaction(fn () => (new self($employee))->catchUp($now));
        });

        return $stored;
    }

    /** Stores the checks of the employee's open session that fell due without a location. */
    public function catchUp(CarbonInterface $now): int
    {
        $this->lock();
        $record = $this->openRecord();

        return $record !== null ? $this->materialize($record, self::normalize($now)) : 0;
    }

    /** Starts monitoring a session that was just checked in; the check-in location is its first check. */
    public function start(AttendanceRecord $record, HrGeofence $check, LocationReading $reading, CarbonInterface $now): void
    {
        $now = self::normalize($now);
        $record->loadMissing('workLocation');
        $interval = self::intervalFor($record->center_id);
        if (self::exclusionFor($record, $interval) !== null) {
            return;
        }

        $record->location_check_interval_minutes = $interval;
        $record->next_location_check_at = HrLocationSchedule::nextAfter($now, $interval, $this->until($record));
        $this->store($record, LocationCheck::KIND_CHECK_IN, $reading, $check, $now, $now);
    }

    /**
     * Stops monitoring a session that was checked out at $closedAt (or closed by the missing check-out action, without
     * a location): slots that fell due before stay pending, the check-out location is stored, open exceptions end.
     */
    public function stop(AttendanceRecord $record, CarbonInterface $closedAt, ?HrGeofence $check = null, ?LocationReading $reading = null): void
    {
        if (! $record->isMonitored()) {
            return;
        }
        $closedAt = self::normalize($closedAt);

        $this->materialize($record, $closedAt);
        $record->next_location_check_at = null;
        if ($check !== null && $reading !== null) {
            $this->store($record, LocationCheck::KIND_CHECK_OUT, $reading, $check, $closedAt, self::normalize(now()));
        }

        LocationException::query()
            ->where('attendance_record_id', $record->id)
            ->where('status', LocationException::STATUS_OPEN)
            ->get()
            ->each(fn (LocationException $exception) => $exception->fill([
                'status' => LocationException::STATUS_CLOSED,
                'ended_at' => $closedAt->max($exception->last_seen_at),
            ])->save());

        $this->refresh($record);
        $record->save();
    }

    /**
     * A periodic check from the attendance page (server clock). Refused when there is no open monitored session or
     * no check is due; a retried request with the same client id returns the stored check.
     *
     * @return array{check: LocationCheck, duplicate: bool, record: AttendanceRecord}
     *
     * @throws HrException
     */
    public function submitWeb(LocationReading $reading, CarbonInterface $now): array
    {
        $now = self::normalize($now);

        return DB::transaction(function () use ($reading, $now) {
            $this->lock();
            $existing = $this->byClientId($reading->clientId);
            if ($existing !== null) {
                return ['check' => $existing, 'duplicate' => true, 'record' => $existing->record];
            }

            $record = $this->openRecord() ?? throw new HrException('location_check_no_session', 'error', 409);
            if (! $record->isMonitored()) {
                throw new HrException('location_monitoring_off', 'error', 409);
            }
            if (! $this->isDue($record, $now)) {
                $next = $record->next_location_check_at;
                if ($next === null) {
                    throw new HrException('location_monitoring_ended', 'error', 409);
                }
                throw new HrException('location_check_not_due', 'error', 409, ['time' => $this->localTime($next)], [
                    'next_check_at' => $this->local($next)->toIso8601String(),
                ]);
            }

            return ['check' => $this->apply($record, $reading, $now, $now), 'duplicate' => false, 'record' => $record];
        });
    }

    /**
     * Samples from a registered mobile device (device clock). Each is accepted, a duplicate (same client id) or
     * rejected with a reason; rejected samples are not stored, so nothing is kept from outside a work session.
     *
     * @param  LocationReading[]  $readings
     * @return array<int, array{client_id: ?string, result: string, reason: ?string, check: ?LocationCheck}>
     */
    public function submitMobile(array $readings, CarbonInterface $now): array
    {
        $now = self::normalize($now);
        usort($readings, fn (LocationReading $a, LocationReading $b) => $a->recordedAt <=> $b->recordedAt);

        return DB::transaction(function () use ($readings, $now) {
            $this->lock();

            return array_map(fn (LocationReading $reading) => $this->mobileSample($reading, $now), $readings);
        });
    }

    /**
     * Monitoring state of a session for the employee's attendance page and the mobile application.
     *
     * @return array<string, mixed>
     */
    public function present(?AttendanceRecord $record, CarbonInterface $now): array
    {
        $now = self::normalize($now);
        $interval = self::intervalFor($this->employee->center_id);
        $state = [
            'enabled' => $interval !== null,
            'interval_minutes' => $record?->location_check_interval_minutes ?? $interval,
            'monitored' => false,
            'active' => false,
            'excluded_reason' => null,
            'status' => null,
            'next_check_at' => null,
            'next_check_time' => null,
            'due' => false,
            'overdue' => false,
            'missed_count' => 0,
            'open_exception' => false,
            'last_check' => null,
            'checks' => [],
        ];
        if ($record === null) {
            return $state;
        }

        $record->loadMissing(['workLocation', 'lastLocationCheck']);
        if (! $record->isMonitored()) {
            return ['excluded_reason' => $record->isOpen() ? self::exclusionFor($record, $interval) : null] + $state;
        }

        $next = $record->next_location_check_at;
        $active = $record->isOpen() && ($next !== null || $this->hasRecentPendingSlot($record, $now));
        $checks = $record->locationChecks()
            ->orderByRaw('COALESCE(checked_at, due_at) DESC')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return [
            'monitored' => true,
            'active' => $active,
            'status' => $record->location_status,
            'next_check_at' => $active && $next !== null ? $this->local($next)->toIso8601String() : null,
            'next_check_time' => $active && $next !== null ? $this->localTime($next) : null,
            'due' => $active && $this->isDue($record, $now),
            'overdue' => $active && HrLocationSchedule::isOverdue($this->oldestOpenSlot($record, $now), $now),
            'missed_count' => $record->locationChecks()
                ->where('status', LocationCheck::STATUS_PENDING)
                ->whereNotNull('due_at')
                ->when($record->isOpen(), fn ($query) => $query->where('due_at', '<', $now->subSeconds(HrLocationSchedule::OVERDUE_AFTER_SECONDS)))
                ->count(),
            'open_exception' => $record->locationExceptions()->where('status', LocationException::STATUS_OPEN)->exists(),
            'last_check' => $record->lastLocationCheck ? $this->compact($record->lastLocationCheck, $record, $now) : null,
            'checks' => $checks->map(fn (LocationCheck $check) => $this->compact($check, $record, $now))->values()->all(),
        ] + $state;
    }

    /** Whether a pending check was due long enough ago (or its session ended) to count as missed. */
    public function isMissed(LocationCheck $check, AttendanceRecord $record, CarbonImmutable $now): bool
    {
        return $check->isPending() && $check->due_at !== null
            && (! $record->isOpen() || HrLocationSchedule::isOverdue(CarbonImmutable::instance($check->due_at), $now));
    }

    private function mobileSample(LocationReading $reading, CarbonImmutable $now): array
    {
        $result = fn (string $result, ?string $reason = null, ?LocationCheck $check = null) => [
            'client_id' => $reading->clientId, 'result' => $result, 'reason' => $reason, 'check' => $check,
        ];

        $existing = $this->byClientId($reading->clientId);
        if ($existing !== null) {
            return $result('duplicate', null, $existing);
        }

        $at = self::normalize($reading->recordedAt ?? $now);
        if ($at->getTimestamp() > $now->getTimestamp() + self::MAX_FUTURE_SKEW_SECONDS) {
            return $result('rejected', 'timestamp_in_future');
        }
        if ($at->lt($now->subHours(self::MAX_SAMPLE_AGE_HOURS))) {
            return $result('rejected', 'timestamp_too_old');
        }

        $record = $this->sessionAt($at);
        if ($record === null) {
            return $result('rejected', 'outside_session');
        }
        if (! $record->isMonitored()) {
            return $result('rejected', 'monitoring_off');
        }
        $until = $this->until($record);
        if ($until !== null && $at->gte($until)) {
            return $result('rejected', 'outside_working_hours');
        }

        return $result('accepted', null, $this->apply($record, $reading, $at, $now));
    }

    /** Stores a periodic location taken at $checkedAt: it answers the slot it falls in, or is an extra check. */
    private function apply(AttendanceRecord $record, LocationReading $reading, CarbonImmutable $checkedAt, CarbonImmutable $now): LocationCheck
    {
        $interval = $record->location_check_interval_minutes;
        $this->materialize($record, $checkedAt->min($now)->addSeconds(HrLocationSchedule::EARLY_TOLERANCE_SECONDS));

        $pending = $record->locationChecks()->where('status', LocationCheck::STATUS_PENDING)->whereNotNull('due_at')->get();
        $slotAt = HrLocationSchedule::slotFor(
            $pending->map(fn (LocationCheck $check) => CarbonImmutable::instance($check->due_at))->all(),
            $checkedAt,
            $interval,
        );
        $slot = $slotAt === null ? null : $pending->first(fn (LocationCheck $check) => $check->due_at->getTimestamp() === $slotAt->getTimestamp());

        $check = $this->store($record, LocationCheck::KIND_PERIODIC, $reading, $this->geofence($record, $reading), $checkedAt, $now, $slot);

        if ($record->isOpen() && $this->isLatest($record, $check, $checkedAt)) {
            $record->next_location_check_at = HrLocationSchedule::nextAfter($checkedAt, $interval, $this->until($record));
            $record->save();
        }

        return $check;
    }

    private function store(
        AttendanceRecord $record,
        string $kind,
        LocationReading $reading,
        HrGeofence $geofence,
        CarbonImmutable $checkedAt,
        CarbonImmutable $now,
        ?LocationCheck $slot = null,
    ): LocationCheck {
        [$status, $reason] = self::statusFor($geofence, $reading->fix() === null ? $reading->locationError : null, $reading->isMocked);
        $check = $slot ?? new LocationCheck([
            'center_id' => $record->center_id,
            'employee_id' => $record->employee_id,
            'attendance_record_id' => $record->id,
            'kind' => $kind,
        ]);
        $check->fill([
            'work_location_id' => $record->work_location_id,
            'device_id' => $reading->deviceId,
            'source' => $reading->source,
            'status' => $status,
            'reason' => $reason,
            'checked_at' => $checkedAt,
            'received_at' => $now,
            'latitude' => $reading->latitude,
            'longitude' => $reading->longitude,
            'accuracy_meters' => $reading->accuracy,
            'distance_meters' => $geofence->distanceMeters,
            'radius_meters' => $geofence->radiusMeters ?? $record->location_radius_meters,
            'is_mocked' => $reading->isMocked,
            'client_id' => $reading->clientId,
            'ip_address' => $reading->ip,
            'user_agent' => $reading->userAgent,
        ])->save();

        $this->trackException($record, $check, $checkedAt);
        if ($status === LocationCheck::STATUS_OUTSIDE_RADIUS && $kind === LocationCheck::KIND_PERIODIC) {
            $record->addReviewReasons([AttendanceRecord::REVIEW_LEFT_WORK_LOCATION]);
        }
        $this->refresh($record);
        $record->save();

        return $check;
    }

    /** Outside the radius opens or extends the session's exception; inside the radius closes it. */
    private function trackException(AttendanceRecord $record, LocationCheck $check, CarbonImmutable $checkedAt): void
    {
        $open = LocationException::query()
            ->where('attendance_record_id', $record->id)
            ->where('status', LocationException::STATUS_OPEN)
            ->latest('started_at')
            ->first();

        if ($check->status === LocationCheck::STATUS_OUTSIDE_RADIUS) {
            if ($open === null) {
                LocationException::create([
                    'center_id' => $record->center_id,
                    'employee_id' => $record->employee_id,
                    'attendance_record_id' => $record->id,
                    'work_location_id' => $record->work_location_id,
                    'type' => LocationException::TYPE_OUTSIDE_RADIUS,
                    'status' => LocationException::STATUS_OPEN,
                    'started_at' => $checkedAt,
                    'last_seen_at' => $checkedAt,
                    'first_check_id' => $check->id,
                    'last_check_id' => $check->id,
                    'checks_count' => 1,
                    'max_distance_meters' => $check->distance_meters,
                    'review_status' => LocationException::REVIEW_PENDING,
                ]);

                return;
            }
            $later = $checkedAt->gte($open->last_seen_at);
            $open->fill([
                'started_at' => $checkedAt->min($open->started_at),
                'last_seen_at' => $checkedAt->max($open->last_seen_at),
                'last_check_id' => $later ? $check->id : $open->last_check_id,
                'checks_count' => $open->checks_count + 1,
                'max_distance_meters' => max((float) $open->max_distance_meters, (float) $check->distance_meters),
            ])->save();

            return;
        }

        if ($check->status === LocationCheck::STATUS_WITHIN_RADIUS && $open !== null && $checkedAt->gte($open->last_seen_at)) {
            $open->fill(['status' => LocationException::STATUS_CLOSED, 'ended_at' => $checkedAt])->save();
        }
    }

    /** Stores a pending check for every slot due up to $upTo and moves the schedule past them. */
    private function materialize(AttendanceRecord $record, CarbonImmutable $upTo): int
    {
        $next = $record->next_location_check_at;
        if (! $record->isMonitored() || $next === null || $next->gt($upTo)) {
            return 0;
        }

        [$slots, $after] = HrLocationSchedule::dueSlots(
            CarbonImmutable::instance($next),
            $upTo,
            $record->location_check_interval_minutes,
            $this->until($record),
        );
        foreach ($slots as $slot) {
            LocationCheck::query()->firstOrCreate(
                ['attendance_record_id' => $record->id, 'due_at' => $slot],
                [
                    'center_id' => $record->center_id,
                    'employee_id' => $record->employee_id,
                    'work_location_id' => $record->work_location_id,
                    'kind' => LocationCheck::KIND_PERIODIC,
                    'source' => LocationCheck::SOURCE_SYSTEM,
                    'status' => LocationCheck::STATUS_PENDING,
                    'radius_meters' => $record->location_radius_meters,
                ],
            );
        }
        $record->next_location_check_at = $after;
        $this->refresh($record);
        $record->save();

        return count($slots);
    }

    /** Current status = the latest check (a pending slot counts at its due time); last check = latest received. */
    private function refresh(AttendanceRecord $record): void
    {
        $record->location_status = $record->locationChecks()
            ->orderByRaw('COALESCE(checked_at, due_at) DESC')
            ->orderByDesc('id')
            ->value('status');
        $record->last_location_check_id = $record->locationChecks()
            ->whereNotNull('checked_at')
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->value('id');
        $record->unsetRelation('lastLocationCheck');
    }

    /** A check is due when the next slot has (almost) arrived or a slot within the last interval is unanswered. */
    private function isDue(AttendanceRecord $record, CarbonImmutable $now): bool
    {
        $next = $record->next_location_check_at;

        return HrLocationSchedule::isDue($next !== null ? CarbonImmutable::instance($next) : null, $now)
            || $this->hasRecentPendingSlot($record, $now);
    }

    private function hasRecentPendingSlot(AttendanceRecord $record, CarbonImmutable $now): bool
    {
        return $record->isMonitored() && $record->locationChecks()
            ->where('status', LocationCheck::STATUS_PENDING)
            ->where('due_at', '>', $now->subMinutes($record->location_check_interval_minutes))
            ->where('due_at', '<=', $now)
            ->exists();
    }

    /** The due time of the check the session is waiting for (the unanswered slot, or the next one). */
    private function oldestOpenSlot(AttendanceRecord $record, CarbonImmutable $now): ?CarbonImmutable
    {
        $pending = $record->locationChecks()
            ->where('status', LocationCheck::STATUS_PENDING)
            ->where('due_at', '>', $now->subMinutes($record->location_check_interval_minutes))
            ->where('due_at', '<=', $now)
            ->min('due_at');
        if ($pending !== null) {
            return CarbonImmutable::parse((string) $pending, config('app.timezone'));
        }

        return $record->next_location_check_at !== null ? self::normalize($record->next_location_check_at) : null;
    }

    private function isLatest(AttendanceRecord $record, LocationCheck $check, CarbonImmutable $checkedAt): bool
    {
        return ! $record->locationChecks()
            ->whereKeyNot($check->id)
            ->where(fn ($query) => $query->where('checked_at', '>', $checkedAt)
                ->orWhere(fn ($pending) => $pending->where('status', LocationCheck::STATUS_PENDING)->where('due_at', '>', $checkedAt)))
            ->exists();
    }

    private function geofence(AttendanceRecord $record, LocationReading $reading): HrGeofence
    {
        $location = $record->workLocation;
        $site = $location !== null && $location->hasCoordinates() ? [
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'radius' => $record->location_radius_meters
                ?: ($location->radius_meters ?: (int) HrSettings::get($record->center_id, 'attendance.default_gps_radius_meters')),
        ] : null;

        return HrGeofence::check(
            AttendancePolicy::GPS_REQUIRED,
            $site,
            $reading->fix(),
            (int) HrSettings::get($record->center_id, 'attendance.max_gps_accuracy_meters'),
        );
    }

    /** The session (open, checked out or closed without check-out) that was in progress at $at. */
    private function sessionAt(CarbonImmutable $at): ?AttendanceRecord
    {
        return AttendanceRecord::query()
            ->where('employee_id', $this->employee->id)
            ->where('attendance_method', AttendanceRecord::METHOD_ELECTRONIC)
            ->where('check_in_at', '<=', $at)
            ->where(fn ($query) => $query
                ->where('check_out_at', '>=', $at)
                ->orWhere(fn ($open) => $open->open())
                ->orWhere(fn ($missed) => $missed->where('status', AttendanceRecord::STATUS_MISSING_CHECK_OUT)->where('scheduled_end_at', '>=', $at)))
            ->latest('check_in_at')
            ->first();
    }

    /** End of monitoring: the scheduled shift end (checks are only due during working hours). */
    private function until(AttendanceRecord $record): ?CarbonImmutable
    {
        return $record->scheduled_end_at !== null ? self::normalize($record->scheduled_end_at) : null;
    }

    private function openRecord(): ?AttendanceRecord
    {
        return AttendanceRecord::query()->where('employee_id', $this->employee->id)->open()->latest('check_in_at')->first();
    }

    private function byClientId(?string $clientId): ?LocationCheck
    {
        return $clientId === null ? null : LocationCheck::query()
            ->where('employee_id', $this->employee->id)
            ->where('client_id', $clientId)
            ->first();
    }

    private function compact(LocationCheck $check, AttendanceRecord $record, CarbonImmutable $now): array
    {
        $at = $check->checked_at ?? $check->due_at;

        return [
            'id' => $check->id,
            'kind' => $check->kind,
            'source' => $check->source,
            'status' => $check->status,
            'reason' => $check->reason,
            'at' => $at !== null ? $this->local($at)->toIso8601String() : null,
            'time' => $at !== null ? $this->localTime($at) : null,
            'due_time' => $check->due_at !== null ? $this->localTime($check->due_at) : null,
            'distance_meters' => $check->distance_meters,
            'accuracy_meters' => $check->accuracy_meters,
            'radius_meters' => $check->radius_meters,
            'missed' => $this->isMissed($check, $record, $now),
        ];
    }

    private function lock(): void
    {
        Employee::query()->whereKey($this->employee->id)->lockForUpdate()->first();
    }

    private function local(CarbonInterface $at): CarbonImmutable
    {
        return CarbonImmutable::instance($at)->setTimezone(HrElectronicAttendance::timezoneFor($this->employee));
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
