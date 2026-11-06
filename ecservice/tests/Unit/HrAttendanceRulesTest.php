<?php

namespace Tests\Unit;

use App\Models\Hr\AttendancePolicy;
use App\Models\Hr\Employee;
use App\Models\Hr\Shift;
use App\Support\Hr\AttendanceRuleSet;
use App\Support\Hr\HrAttendancePolicyService;
use App\Support\Hr\HrException;
use App\Support\Hr\HrShiftCalculator;
use App\Support\Hr\ScheduledShiftDay;
use App\Support\Hr\ShiftDefinition;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class HrAttendanceRulesTest extends TestCase
{
    private const TZ = 'Asia/Riyadh';

    public function test_manual_and_exempt_employees_never_get_a_gps_requirement(): void
    {
        foreach ([Employee::ATTENDANCE_MANUAL, Employee::ATTENDANCE_EXEMPT] as $mode) {
            foreach ([null, Employee::GPS_FOLLOW_POLICY, Employee::GPS_REQUIRED, Employee::GPS_FLEXIBLE] as $gps) {
                $this->assertSame(Employee::GPS_NONE, AttendanceRuleSet::resolveGps($mode, $gps, AttendancePolicy::GPS_REQUIRED), "{$mode} / {$gps}");
            }
        }
    }

    public function test_electronic_employees_use_their_own_gps_setting_or_the_policy(): void
    {
        $mode = Employee::ATTENDANCE_ELECTRONIC;

        $this->assertSame('required', AttendanceRuleSet::resolveGps($mode, Employee::GPS_FOLLOW_POLICY, 'required'));
        $this->assertSame('flexible', AttendanceRuleSet::resolveGps($mode, Employee::GPS_FOLLOW_POLICY, 'flexible'));
        $this->assertSame('none', AttendanceRuleSet::resolveGps($mode, null, 'none'), 'no setting follows the policy');
        $this->assertSame('flexible', AttendanceRuleSet::resolveGps($mode, Employee::GPS_FLEXIBLE, 'required'), 'employee setting wins');
        $this->assertSame('none', AttendanceRuleSet::resolveGps($mode, Employee::GPS_NONE, 'required'), 'GPS disabled for this employee');
    }

    public function test_grace_precedence_employee_then_shift_then_policy(): void
    {
        $rules = $this->rules(['employeeGraceMinutes' => null, 'lateGraceMinutes' => 15, 'earlyLeaveGraceMinutes' => 10]);
        $this->assertSame(15, $rules->lateGrace());
        $this->assertSame(5, $rules->lateGrace(5));
        $this->assertSame(10, $rules->earlyLeaveGrace());
        $this->assertSame(3, $rules->earlyLeaveGrace(3));

        $override = $this->rules(['employeeGraceMinutes' => 20]);
        $this->assertSame(20, $override->lateGrace(5), 'the employee override beats the shift');
    }

    public function test_check_in_and_check_out_windows_follow_the_shift(): void
    {
        $day = ScheduledShiftDay::working('2026-03-01', $this->definition('08:00', '16:00'), self::TZ, 15, 15);

        $limited = $this->rules(['checkInBeforeMinutes' => 60, 'checkInAfterMinutes' => 120, 'checkOutBeforeMinutes' => 30, 'checkOutAfterMinutes' => 240]);
        [$inFrom, $inTo] = $limited->checkInWindow($day);
        [$outFrom, $outTo] = $limited->checkOutWindow($day);
        $this->assertSame('2026-03-01 07:00', $inFrom->format('Y-m-d H:i'));
        $this->assertSame('2026-03-01 10:00', $inTo->format('Y-m-d H:i'));
        $this->assertSame('2026-03-01 15:30', $outFrom->format('Y-m-d H:i'));
        $this->assertSame('2026-03-01 20:00', $outTo->format('Y-m-d H:i'));

        $open = $this->rules(['checkInAfterMinutes' => null, 'checkOutBeforeMinutes' => null]);
        $this->assertSame('2026-03-01 16:00', $open->checkInWindow($day)[1]->format('Y-m-d H:i'), 'until the shift ends');
        $this->assertSame('2026-03-01 08:00', $open->checkOutWindow($day)[0]->format('Y-m-d H:i'), 'any time after the start');

        $rest = new ScheduledShiftDay('2026-03-06', ScheduledShiftDay::STATUS_REST);
        $this->assertNull($open->checkInWindow($rest));
    }

    public function test_overnight_check_out_window_ends_on_the_next_day(): void
    {
        $day = ScheduledShiftDay::working('2026-03-01', $this->definition('22:00', '06:00'), self::TZ, 15, 15);
        $rules = $this->rules(['checkOutAfterMinutes' => 120]);

        $this->assertSame('2026-03-02 08:00', $rules->checkOutWindow($day)[1]->format('Y-m-d H:i'));
    }

    public function test_punch_attribution_uses_the_policy_windows_when_wider_than_the_setting(): void
    {
        $rules = $this->rules(['checkInBeforeMinutes' => 60, 'checkOutAfterMinutes' => 600]);
        $day = ScheduledShiftDay::working('2026-03-01', $this->definition('08:00', '16:00'), self::TZ, 15, 15, ScheduledShiftDay::SOURCE_ASSIGNMENT, [], $rules);

        $lateCheckout = CarbonImmutable::parse('2026-03-02 01:00', self::TZ);
        $this->assertFalse($day->windowContains($lateCheckout, 240));
        $this->assertTrue($day->punchWindowContains($lateCheckout, 240));
        $this->assertFalse($day->punchWindowContains(CarbonImmutable::parse('2026-03-02 03:00', self::TZ), 240));
    }

    public function test_late_and_early_departure_counting_follow_the_day_rules(): void
    {
        $rules = $this->rules([
            'lateCounting' => AttendancePolicy::COUNTING_EXCESS_ONLY,
            'earlyLeaveCounting' => AttendancePolicy::COUNTING_FULL,
        ]);
        $day = ScheduledShiftDay::working('2026-03-01', $this->definition('08:00', '16:00'), self::TZ, 10, 10, ScheduledShiftDay::SOURCE_ASSIGNMENT, [], $rules);

        $result = HrShiftCalculator::evaluate($day, $this->at('2026-03-01 08:25'), $this->at('2026-03-01 15:40'));
        $this->assertSame(15, $result['late_minutes'], '25 minutes late, 10 of grace deducted');
        $this->assertSame(20, $result['early_departure_minutes'], 'whole early departure counts');

        $explicit = HrShiftCalculator::evaluate($day, $this->at('2026-03-01 08:25'), $this->at('2026-03-01 15:40'), false);
        $this->assertSame(25, $explicit['late_minutes'], 'an explicit counting rule still wins');
        $this->assertSame(20, $explicit['early_departure_minutes']);
    }

    public function test_policy_rules_are_validated_together(): void
    {
        $base = [
            'check_in_before_minutes' => 60, 'check_in_after_minutes' => null, 'check_out_before_minutes' => null,
            'check_out_after_minutes' => 240, 'late_grace_minutes' => 15, 'early_leave_grace_minutes' => 15,
            'late_counting' => 'full', 'late_action' => 'record_only', 'absence_after_minutes' => null,
            'early_leave_counting' => 'full', 'early_leave_action' => 'record_only', 'missing_checkout_action' => 'flag_for_review',
            'duplicate_punch_action' => 'first_in_last_out', 'duplicate_window_minutes' => 5, 'gps_requirement' => 'required',
            'allow_manual_attendance' => 1,
        ];

        $merged = HrAttendancePolicyService::rules(['late_grace_minutes' => 20, 'gps_requirement' => 'none'], $base);
        $this->assertSame(20, $merged['late_grace_minutes']);
        $this->assertSame('none', $merged['gps_requirement']);
        $this->assertTrue($merged['allow_manual_attendance']);
        $this->assertSame(AttendancePolicy::RULE_FIELDS, array_keys($merged));

        $this->assertRuleError('check_in_window_shorter_than_grace', ['check_in_after_minutes' => 10], $base);
        $this->assertRuleError('absence_within_grace', ['absence_after_minutes' => 15], $base);
        $this->assertRuleError('policy_rule_required', ['late_grace_minutes' => null], $base);
    }

    private function assertRuleError(string $reason, array $data, array $base): void
    {
        try {
            HrAttendancePolicyService::rules($data, $base);
            $this->fail("Expected {$reason}");
        } catch (HrException $e) {
            $this->assertSame($reason, $e->reason);
        }
    }

    private function rules(array $overrides = []): AttendanceRuleSet
    {
        $values = $overrides + [
            'date' => '2026-03-01',
            'attendanceMode' => Employee::ATTENDANCE_ELECTRONIC,
            'gpsPolicy' => Employee::GPS_FOLLOW_POLICY,
            'gpsRequirement' => AttendancePolicy::GPS_REQUIRED,
            'configId' => 1,
            'policyId' => 1,
            'policyVersionId' => 1,
            'source' => AttendanceRuleSet::SOURCE_POLICY,
            'employeeGraceMinutes' => null,
            'lateGraceMinutes' => 15,
            'earlyLeaveGraceMinutes' => 15,
            'checkInBeforeMinutes' => 60,
            'checkInAfterMinutes' => null,
            'checkOutBeforeMinutes' => null,
            'checkOutAfterMinutes' => 240,
            'lateCounting' => AttendancePolicy::COUNTING_FULL,
            'lateAction' => AttendancePolicy::ACTION_RECORD_ONLY,
            'absenceAfterMinutes' => null,
            'earlyLeaveCounting' => AttendancePolicy::COUNTING_FULL,
            'earlyLeaveAction' => AttendancePolicy::ACTION_RECORD_ONLY,
            'missingCheckoutAction' => AttendancePolicy::MISSING_CHECKOUT_FLAG,
            'duplicatePunchAction' => AttendancePolicy::DUPLICATE_FIRST_IN_LAST_OUT,
            'duplicateWindowMinutes' => 5,
            'allowManualAttendance' => true,
        ];

        return new AttendanceRuleSet(...$values);
    }

    private function definition(string $start, string $end): ShiftDefinition
    {
        return new ShiftDefinition(1, 'S', 'Shift', Shift::TYPE_CUSTOM, null, $start, $end, null, 0, null, null, null, [0, 1, 2, 3, 4], [5, 6]);
    }

    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($time, self::TZ);
    }
}
