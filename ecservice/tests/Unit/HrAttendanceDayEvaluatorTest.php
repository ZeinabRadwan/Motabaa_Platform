<?php

namespace Tests\Unit;

use App\Models\Hr\AttendanceDay;
use App\Models\Hr\AttendancePolicy;
use App\Models\Hr\AttendanceRecord;
use App\Models\Hr\Employee;
use App\Models\Hr\Holiday;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\LeaveType;
use App\Models\Hr\Shift;
use App\Support\Hr\AttendanceRuleSet;
use App\Support\Hr\HrAttendanceDayEvaluator;
use App\Support\Hr\HrAttendancePeriods;
use App\Support\Hr\ScheduledShiftDay;
use App\Support\Hr\ShiftDefinition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HrAttendanceDayEvaluatorTest extends TestCase
{
    private const TZ = 'Asia/Riyadh';

    /** Friday and Saturday. */
    private const WEEKEND = [5, 6];

    /** Sunday. */
    private const DATE = '2026-03-01';

    public function test_late_minutes_use_the_shift_start_and_grace_period(): void
    {
        $excessOnly = $this->working(rules: ['lateCounting' => AttendancePolicy::COUNTING_EXCESS_ONLY]);
        $result = $this->evaluate($excessOnly, $this->record('08:20', '17:00'));
        $this->assertSame(AttendanceDay::STATUS_LATE, $result['computed_status']);
        $this->assertSame(5, $result['computed_late_minutes'], 'shift 08:00, grace 15, arrival 08:20: 5 late minutes');

        $full = $this->working(rules: ['lateCounting' => AttendancePolicy::COUNTING_FULL]);
        $this->assertSame(20, $this->evaluate($full, $this->record('08:20', '17:00'))['computed_late_minutes'], 'full counting: the whole delay once the grace is exceeded');

        $withinGrace = $this->evaluate($excessOnly, $this->record('08:15', '17:00'));
        $this->assertSame(AttendanceDay::STATUS_PRESENT, $withinGrace['computed_status']);
        $this->assertSame(0, $withinGrace['computed_late_minutes']);
    }

    public function test_early_departure_and_working_duration(): void
    {
        $day = $this->working(rules: ['earlyLeaveGraceMinutes' => 10, 'lateCounting' => AttendancePolicy::COUNTING_EXCESS_ONLY]);
        $result = $this->evaluate($day, $this->record('08:20', '16:30'));

        $this->assertSame(AttendanceDay::STATUS_LATE_EARLY_DEPARTURE, $result['computed_status']);
        $this->assertSame(30, $result['computed_early_departure_minutes']);
        $this->assertSame(490, $result['worked_minutes'], '08:20 to 16:30');
        $this->assertSame(540, $result['expected_minutes']);
    }

    public function test_absence_needs_a_closed_working_day_without_attendance(): void
    {
        $day = $this->working();

        $open = $this->evaluate($day, null, now: $this->at(self::DATE.' 12:00'));
        $this->assertSame(AttendanceDay::STATUS_PENDING, $open['computed_status'], 'the employee may still check in');

        $absent = $this->evaluate($day, null);
        $this->assertSame(AttendanceDay::STATUS_ABSENT, $absent['computed_status']);
        $this->assertSame(AttendanceDay::REASON_NO_ATTENDANCE, $absent['status_reason']);
        $this->assertSame(1.0, HrAttendanceDayEvaluator::outcome($absent)['absence_days']);
    }

    public function test_rest_days_holidays_and_leave_are_never_absence(): void
    {
        $rest = new ScheduledShiftDay('2026-03-06', ScheduledShiftDay::STATUS_REST);
        $this->assertSame(AttendanceDay::STATUS_REST_DAY, $this->evaluate($rest, null)['computed_status']);

        $holiday = $this->evaluate($this->working(), null, holiday: $this->holiday());
        $this->assertSame(AttendanceDay::STATUS_HOLIDAY, $holiday['computed_status']);
        $this->assertSame(7, $holiday['holiday_id']);

        $unscheduledWeekday = new ScheduledShiftDay(self::DATE, ScheduledShiftDay::STATUS_UNSCHEDULED);
        $this->assertSame(AttendanceDay::STATUS_HOLIDAY, $this->evaluate($unscheduledWeekday, null, holiday: $this->holiday())['computed_status']);

        $onLeave = $this->evaluate($this->working(), null, leave: $this->leave());
        $this->assertSame(AttendanceDay::STATUS_ON_LEAVE, $onLeave['computed_status']);
        $this->assertSame(3, $onLeave['leave_request_id']);
        $this->assertTrue($onLeave['leave_is_paid']);

        foreach ([$holiday, $onLeave] as $result) {
            $this->assertSame(0.0, HrAttendanceDayEvaluator::outcome($result)['absence_days']);
        }
    }

    public function test_working_day_leave_does_not_cover_a_holiday_but_calendar_day_leave_does(): void
    {
        $workingDays = $this->evaluate($this->working(), null, leave: $this->leave(), holiday: $this->holiday());
        $this->assertSame(AttendanceDay::STATUS_HOLIDAY, $workingDays['computed_status'], 'the holiday is not deducted from the leave');

        $calendar = $this->evaluate($this->working(), null, leave: $this->leave(LeaveType::COUNT_CALENDAR_DAYS), holiday: $this->holiday());
        $this->assertSame(AttendanceDay::STATUS_ON_LEAVE, $calendar['computed_status']);

        $rest = new ScheduledShiftDay('2026-03-06', ScheduledShiftDay::STATUS_REST);
        $this->assertSame(AttendanceDay::STATUS_REST_DAY, $this->evaluate($rest, null, leave: $this->leave())['computed_status']);
    }

    public function test_attendance_during_leave_is_an_exception(): void
    {
        $result = $this->evaluate($this->working(), $this->record('08:00', '17:00'), leave: $this->leave());

        $this->assertSame(AttendanceDay::STATUS_ON_LEAVE, $result['computed_status']);
        $this->assertSame([AttendanceDay::EXCEPTION_ATTENDED_DURING_LEAVE], $result['exception_reasons']);
    }

    public function test_failed_gps_verification_is_not_absence(): void
    {
        $result = $this->evaluate($this->working(), null, rejectedPunches: true);

        $this->assertSame(AttendanceDay::STATUS_INCOMPLETE, $result['computed_status']);
        $this->assertSame([AttendanceDay::EXCEPTION_GPS_VERIFICATION_FAILED], $result['exception_reasons']);
        $outcome = HrAttendanceDayEvaluator::outcome($result);
        $this->assertSame(0.0, $outcome['absence_days']);
        $this->assertSame(AttendanceDay::REVIEW_PENDING, $outcome['review_status']);
    }

    public function test_missing_check_in_or_out_is_an_exception_for_review(): void
    {
        $missingOut = $this->evaluate($this->working(), $this->record('08:30', null, AttendanceRecord::STATUS_MISSING_CHECK_OUT));
        $this->assertSame(AttendanceDay::STATUS_INCOMPLETE, $missingOut['computed_status']);
        $this->assertSame([AttendanceDay::EXCEPTION_MISSING_CHECK_OUT], $missingOut['exception_reasons']);
        $this->assertSame(15, $missingOut['computed_late_minutes'], 'lateness is still known: 08:30 minus the 15 minute grace');

        $missingIn = $this->evaluate($this->working(), $this->record(null, '17:00', AttendanceRecord::STATUS_MISSING_CHECK_IN));
        $this->assertSame([AttendanceDay::EXCEPTION_MISSING_CHECK_IN], $missingIn['exception_reasons']);

        $openDuringDay = $this->evaluate($this->working(), $this->record('08:00', null, AttendanceRecord::STATUS_CHECKED_IN), now: $this->at(self::DATE.' 15:00'));
        $this->assertSame(AttendanceDay::STATUS_PENDING, $openDuringDay['computed_status']);

        $openAfterWindow = $this->evaluate($this->working(), $this->record('08:00', null, AttendanceRecord::STATUS_CHECKED_IN));
        $this->assertSame(AttendanceDay::STATUS_INCOMPLETE, $openAfterWindow['computed_status']);

        $policyAbsent = $this->evaluate(
            $this->working(rules: ['missingCheckoutAction' => AttendancePolicy::MISSING_CHECKOUT_ABSENT]),
            $this->record('08:00', null, AttendanceRecord::STATUS_MISSING_CHECK_OUT),
        );
        $this->assertSame(AttendanceDay::STATUS_INCOMPLETE, $policyAbsent['computed_status'], 'HR decides: only flagged');
        $this->assertSame([AttendanceDay::FLAG_POLICY_MARKS_ABSENT], $policyAbsent['review_flags']);
    }

    public function test_arrival_after_the_absence_threshold_is_absence(): void
    {
        $result = $this->evaluate($this->working(rules: ['absenceAfterMinutes' => 120]), $this->record('10:30', '17:00'));

        $this->assertSame(AttendanceDay::STATUS_ABSENT, $result['computed_status']);
        $this->assertSame(AttendanceDay::REASON_LATE_BEYOND_ABSENCE_THRESHOLD, $result['status_reason']);
        $this->assertSame(0, HrAttendanceDayEvaluator::outcome($result)['late_minutes'], 'an absent day carries no late minutes');
    }

    public function test_exempt_and_outside_employment(): void
    {
        $exempt = $this->evaluate($this->working(rules: ['attendanceMode' => Employee::ATTENDANCE_EXEMPT]), null);
        $this->assertSame(AttendanceDay::STATUS_EXEMPT, $exempt['computed_status']);

        $notEmployed = new ScheduledShiftDay(self::DATE, ScheduledShiftDay::STATUS_NOT_EMPLOYED);
        $this->assertSame(AttendanceDay::STATUS_NOT_EMPLOYED, $this->evaluate($notEmployed, null, leave: $this->leave())['computed_status']);
    }

    public function test_overnight_shift_belongs_to_its_start_date(): void
    {
        $day = $this->working(start: '22:00', end: '06:00', rules: ['lateCounting' => AttendancePolicy::COUNTING_EXCESS_ONLY]);
        $record = $this->record(null, null);
        $record->check_in_at = $this->at(self::DATE.' 22:25');
        $record->check_out_at = $this->at('2026-03-02 06:00');

        $result = $this->evaluate($day, $record, now: $this->at('2026-03-02 05:00'));
        $this->assertSame(AttendanceDay::STATUS_LATE, $result['computed_status']);
        $this->assertSame(10, $result['computed_late_minutes']);
        $this->assertSame(455, $result['worked_minutes']);
    }

    public function test_outcome_applies_corrections_and_review(): void
    {
        $computed = ['computed_status' => AttendanceDay::STATUS_INCOMPLETE, 'computed_late_minutes' => 30, 'computed_early_departure_minutes' => 0, 'exception_reasons' => [AttendanceDay::EXCEPTION_MISSING_CHECK_OUT]];

        $pending = HrAttendanceDayEvaluator::outcome($computed);
        $this->assertSame(AttendanceDay::STATUS_INCOMPLETE, $pending['status']);
        $this->assertSame(30, $pending['late_minutes']);
        $this->assertSame(AttendanceDay::REVIEW_PENDING, $pending['review_status']);

        $corrected = HrAttendanceDayEvaluator::outcome($computed, ['override_status' => AttendanceDay::STATUS_PRESENT, 'override_late_minutes' => 10, 'overridden_at' => now()]);
        $this->assertSame(AttendanceDay::STATUS_LATE, $corrected['status'], 'present with late minutes is late');
        $this->assertSame(10, $corrected['late_minutes']);
        $this->assertSame(AttendanceDay::REVIEW_RESOLVED, $corrected['review_status']);

        $excused = HrAttendanceDayEvaluator::outcome($computed, ['override_status' => AttendanceDay::STATUS_EXCUSED, 'overridden_at' => now()]);
        $this->assertSame(0, $excused['late_minutes']);
        $this->assertSame(0.0, $excused['absence_days']);
    }

    public function test_summary_aggregates_each_day_once(): void
    {
        $days = collect([
            $this->dayRow('2026-03-01', AttendanceDay::STATUS_LATE, late: 5, worked: 535),
            $this->dayRow('2026-03-02', AttendanceDay::STATUS_LATE, late: 12, worked: 528),
            $this->dayRow('2026-03-03', AttendanceDay::STATUS_ABSENT, absence: 1.0),
            $this->dayRow('2026-03-04', AttendanceDay::STATUS_ON_LEAVE, leaveTypeId: 1, paid: true),
            $this->dayRow('2026-03-05', AttendanceDay::STATUS_INCOMPLETE, worked: 0, exceptions: [AttendanceDay::EXCEPTION_MISSING_CHECK_OUT], review: AttendanceDay::REVIEW_RESOLVED),
            $this->dayRow('2026-03-06', AttendanceDay::STATUS_REST_DAY, dayType: ScheduledShiftDay::STATUS_REST),
            $this->dayRow('2026-03-08', AttendanceDay::STATUS_HOLIDAY),
        ]);
        $types = collect([1 => new LeaveType(['code' => 'annual', 'name_en' => 'Annual leave', 'name_ar' => 'سنوية', 'is_paid' => true])]);

        $summary = HrAttendancePeriods::summarize($days, $types);

        $this->assertSame(6, $summary['scheduled_days']);
        $this->assertSame(2, $summary['present_days']);
        $this->assertSame(1.0, $summary['absence_days']);
        $this->assertSame(2, $summary['late_days']);
        $this->assertSame(17, $summary['late_minutes']);
        $this->assertSame(1, $summary['holiday_days']);
        $this->assertSame(1, $summary['rest_days']);
        $this->assertSame(1, $summary['exceptions_count']);
        $this->assertSame(0, $summary['unresolved_exceptions']);
        $this->assertSame(1, $summary['paid_leave_days']);
        $this->assertSame([['leave_type_id' => 1, 'leave_type' => 'Annual leave', 'code' => 'annual', 'is_paid' => true, 'days' => 1]], $summary['leave_breakdown']);
        $this->assertCount(1, $summary['exception_details']);
        $this->assertSame('2026-03-05', $summary['exception_details'][0]['date']);
    }

    public function test_period_conflicts(): void
    {
        $this->assertTrue(HrAttendancePeriods::conflicts('2026-03-01', '2026-03-31', null, '2026-03-31', '2026-04-30', null), 'shared boundary day');
        $this->assertFalse(HrAttendancePeriods::conflicts('2026-03-01', '2026-03-31', null, '2026-04-01', '2026-04-30', null), 'adjacent periods');
        $this->assertFalse(HrAttendancePeriods::conflicts('2026-03-01', '2026-03-31', 1, '2026-03-01', '2026-03-31', 2), 'different branches');
        $this->assertTrue(HrAttendancePeriods::conflicts('2026-03-01', '2026-03-31', 1, '2026-03-15', '2026-04-15', null), 'a center-wide period covers every branch');
    }

    private function evaluate(
        ScheduledShiftDay $day,
        ?AttendanceRecord $record,
        ?LeaveRequest $leave = null,
        ?Holiday $holiday = null,
        bool $rejectedPunches = false,
        ?CarbonImmutable $now = null,
    ): array {
        return HrAttendanceDayEvaluator::evaluate($day, $record, $leave, $holiday, self::WEEKEND, $rejectedPunches, $now ?? $this->at('2026-03-10 12:00'));
    }

    private function working(string $start = '08:00', string $end = '17:00', array $rules = []): ScheduledShiftDay
    {
        $definition = new ShiftDefinition(1, 'S', 'Shift', Shift::TYPE_CUSTOM, null, $start, $end, null, 0, null, null, null, [0, 1, 2, 3, 4], self::WEEKEND);
        $ruleSet = $this->rules($rules);

        return ScheduledShiftDay::working(self::DATE, $definition, self::TZ, $ruleSet->lateGrace(), $ruleSet->earlyLeaveGrace(), rules: $ruleSet);
    }

    private function rules(array $overrides): AttendanceRuleSet
    {
        return new AttendanceRuleSet(...($overrides + [
            'date' => self::DATE,
            'attendanceMode' => Employee::ATTENDANCE_MANUAL,
            'gpsPolicy' => Employee::GPS_FOLLOW_POLICY,
            'gpsRequirement' => Employee::GPS_NONE,
            'configId' => 1,
            'policyId' => 1,
            'policyVersionId' => 1,
            'source' => AttendanceRuleSet::SOURCE_POLICY,
            'employeeGraceMinutes' => null,
            'lateGraceMinutes' => 15,
            'earlyLeaveGraceMinutes' => 0,
            'checkInBeforeMinutes' => 60,
            'checkInAfterMinutes' => null,
            'checkOutBeforeMinutes' => null,
            'checkOutAfterMinutes' => 240,
            'lateCounting' => AttendancePolicy::COUNTING_EXCESS_ONLY,
            'lateAction' => AttendancePolicy::ACTION_RECORD_ONLY,
            'absenceAfterMinutes' => null,
            'earlyLeaveCounting' => AttendancePolicy::COUNTING_FULL,
            'earlyLeaveAction' => AttendancePolicy::ACTION_RECORD_ONLY,
            'missingCheckoutAction' => AttendancePolicy::MISSING_CHECKOUT_FLAG,
            'duplicatePunchAction' => AttendancePolicy::DUPLICATE_FIRST_IN_LAST_OUT,
            'duplicateWindowMinutes' => 5,
            'allowManualAttendance' => true,
        ]));
    }

    private function record(?string $in, ?string $out, string $status = AttendanceRecord::STATUS_CHECKED_OUT): AttendanceRecord
    {
        $record = new AttendanceRecord();
        $record->id = 11;
        $record->status = $status;
        $record->attendance_method = 'manual';
        $record->check_in_at = $in !== null ? $this->at(self::DATE.' '.$in) : null;
        $record->check_out_at = $out !== null ? $this->at(self::DATE.' '.$out) : null;
        $record->shift_overridden = false;

        return $record;
    }

    private function leave(string $counting = LeaveType::COUNT_WORKING_DAYS): LeaveRequest
    {
        $type = new LeaveType(['day_counting' => $counting, 'is_paid' => true]);
        $type->id = 2;
        $leave = new LeaveRequest();
        $leave->id = 3;
        $leave->setRelation('leaveType', $type);

        return $leave;
    }

    private function holiday(): Holiday
    {
        $holiday = new Holiday();
        $holiday->id = 7;

        return $holiday;
    }

    private function dayRow(
        string $date,
        string $status,
        int $late = 0,
        ?int $worked = null,
        float $absence = 0.0,
        ?int $leaveTypeId = null,
        ?bool $paid = null,
        ?array $exceptions = null,
        ?string $review = null,
        string $dayType = ScheduledShiftDay::STATUS_WORKING,
    ): AttendanceDay {
        $day = new AttendanceDay();
        $day->forceFill([
            'attendance_date' => Carbon::parse($date),
            'status' => $status,
            'day_type' => $dayType,
            'late_minutes' => $late,
            'early_departure_minutes' => 0,
            'worked_minutes' => $worked,
            'expected_minutes' => $dayType === ScheduledShiftDay::STATUS_WORKING ? 540 : 0,
            'absence_days' => $absence,
            'leave_type_id' => $leaveTypeId,
            'leave_is_paid' => $paid,
            'exception_reasons' => $exceptions,
            'review_status' => $review,
        ]);

        return $day;
    }

    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($time, self::TZ);
    }
}
