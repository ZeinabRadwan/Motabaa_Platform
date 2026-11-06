<?php

namespace Tests\Unit;

use App\Models\Hr\Shift;
use App\Support\Hr\HrShiftCalculator;
use App\Support\Hr\HrShiftMath;
use App\Support\Hr\ScheduledShiftDay;
use App\Support\Hr\ShiftDefinition;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class HrShiftCalculatorTest extends TestCase
{
    private const TZ = 'Asia/Riyadh';

    public function test_span_expected_hours_and_overnight_detection(): void
    {
        $this->assertSame(540, HrShiftMath::spanMinutes('08:00', '17:00'), 'Morning 08:00 AM - 05:00 PM');
        $this->assertSame(480, HrShiftMath::spanMinutes('14:00', '22:00'), 'Evening 02:00 PM - 10:00 PM');
        $this->assertSame(480, HrShiftMath::spanMinutes('22:00', '06:00'), 'Night 10:00 PM - 06:00 AM crosses midnight');
        $this->assertFalse(HrShiftMath::isOvernight('08:00', '17:00'));
        $this->assertTrue(HrShiftMath::isOvernight('22:00', '06:00'));

        $this->assertSame(480, HrShiftMath::expectedMinutes('08:00', '17:00', 60), 'span minus break');
        $this->assertSame(420, HrShiftMath::expectedMinutes('08:00', '17:00', 60, 420), 'configured value wins');

        $this->assertSame(Shift::TYPE_MORNING, HrShiftMath::suggestType('08:00', '17:00'));
        $this->assertSame(Shift::TYPE_EVENING, HrShiftMath::suggestType('14:00', '22:00'));
        $this->assertSame(Shift::TYPE_NIGHT, HrShiftMath::suggestType('22:00', '06:00'));
    }

    public function test_timing_errors_cover_breaks_and_expected_hours(): void
    {
        $this->assertSame([], HrShiftMath::timingErrors('22:00', '06:00', 30, '02:00', 450), 'break at 02:00 is inside a night shift');
        $this->assertSame(['break_minutes' => 'shift_break_too_long'], HrShiftMath::timingErrors('08:00', '10:00', 120, null, null));
        $this->assertSame(['break_start_time' => 'shift_break_outside'], HrShiftMath::timingErrors('22:00', '06:00', 30, '05:45', null));
        $this->assertSame(['break_start_time' => 'shift_break_outside'], HrShiftMath::timingErrors('08:00', '17:00', 60, '07:00', null));
        $this->assertSame(['expected_minutes' => 'shift_expected_too_long'], HrShiftMath::timingErrors('08:00', '12:00', 0, null, 300));
    }

    public function test_rotation_index_counts_from_the_anchor_with_offset_in_both_directions(): void
    {
        $anchor = CarbonImmutable::parse('2026-03-01');

        $this->assertSame(0, HrShiftMath::rotationIndex($anchor, $anchor, 6));
        $this->assertSame(5, HrShiftMath::rotationIndex($anchor, $anchor->addDays(5), 6));
        $this->assertSame(0, HrShiftMath::rotationIndex($anchor, $anchor->addDays(6), 6), 'cycle restarts');
        $this->assertSame(2, HrShiftMath::rotationIndex($anchor, $anchor, 6, 2), 'offset moves the starting day');
        $this->assertSame(1, HrShiftMath::rotationIndex($anchor, $anchor->addDays(5), 6, 2));
        $this->assertSame(5, HrShiftMath::rotationIndex($anchor, $anchor->subDay(), 6), 'dates before the anchor wrap backwards');
    }

    public function test_regular_shift_late_arrival_early_departure_and_break(): void
    {
        $day = $this->day('2026-03-01', $this->definition('08:00', '17:00', 60), 15, 10);

        $onTime = HrShiftCalculator::evaluate($day, $this->at('2026-03-01 08:10'), $this->at('2026-03-01 17:00'));
        $this->assertSame(0, $onTime['late_minutes'], 'within the 15 minute grace period');
        $this->assertSame(470, $onTime['worked_minutes'], '530 minutes in the shift minus the 60 minute floating break');
        $this->assertSame(60, $onTime['break_minutes']);
        $this->assertSame(10, $onTime['shortfall_minutes'], 'not late, but 10 minutes short of the 8 expected hours');

        $late = HrShiftCalculator::evaluate($day, $this->at('2026-03-01 08:20'), $this->at('2026-03-01 16:45'));
        $this->assertSame(20, $late['late_minutes'], 'whole delay counts once grace is exceeded');
        $this->assertTrue($late['is_late']);
        $this->assertSame(15, $late['early_departure_minutes']);
        $this->assertTrue($late['is_early_departure']);
        $this->assertSame(445, $late['worked_minutes'], '505 minutes in the shift minus the break');
        $this->assertSame(35, $late['shortfall_minutes']);

        $afterGrace = HrShiftCalculator::evaluate($day, $this->at('2026-03-01 08:20'), $this->at('2026-03-01 16:45'), true);
        $this->assertSame(5, $afterGrace['late_minutes'], 'only minutes after the grace period');
        $this->assertSame(5, $afterGrace['early_departure_minutes']);

        $earlyLeaveWithinGrace = HrShiftCalculator::evaluate($day, $this->at('2026-03-01 08:00'), $this->at('2026-03-01 16:52'));
        $this->assertSame(0, $earlyLeaveWithinGrace['early_departure_minutes']);
    }

    public function test_time_outside_the_shift_is_not_worked_time(): void
    {
        $day = $this->day('2026-03-01', $this->definition('08:00', '17:00', 60), 15, 15);

        $result = HrShiftCalculator::evaluate($day, $this->at('2026-03-01 07:30'), $this->at('2026-03-01 18:00'));

        $this->assertSame(630, $result['presence_minutes']);
        $this->assertSame(90, $result['outside_shift_minutes']);
        $this->assertSame(480, $result['worked_minutes']);
        $this->assertSame(0, $result['late_minutes']);
    }

    public function test_overnight_shift_with_fixed_break_window(): void
    {
        $day = $this->day('2026-03-01', $this->definition('22:00', '06:00', 30, '02:00'), 10, 10);

        $this->assertTrue($day->isOvernight());
        $this->assertSame('2026-03-02 06:00', $day->endAt->format('Y-m-d H:i'), 'ends on the next calendar day');
        $this->assertSame('2026-03-02 02:00', $day->breakStartAt->format('Y-m-d H:i'));
        $this->assertSame(450, $day->expectedMinutes);

        $full = HrShiftCalculator::evaluate($day, $this->at('2026-03-01 22:05'), $this->at('2026-03-02 06:00'));
        $this->assertSame(0, $full['late_minutes']);
        $this->assertSame(0, $full['early_departure_minutes']);
        $this->assertSame(30, $full['break_minutes']);
        $this->assertSame(445, $full['worked_minutes']);

        $leftDuringBreak = HrShiftCalculator::evaluate($day, $this->at('2026-03-01 22:00'), $this->at('2026-03-02 02:15'));
        $this->assertSame(15, $leftDuringBreak['break_minutes'], 'only the part of the break window spent present');
        $this->assertSame(240, $leftDuringBreak['worked_minutes']);
        $this->assertSame(225, $leftDuringBreak['early_departure_minutes']);

        $late = HrShiftCalculator::evaluate($day, $this->at('2026-03-01 22:30'), $this->at('2026-03-02 06:00'));
        $this->assertSame(30, $late['late_minutes']);
    }

    public function test_rest_day_presence_and_missing_punches(): void
    {
        $rest = new ScheduledShiftDay('2026-03-06', ScheduledShiftDay::STATUS_REST);
        $result = HrShiftCalculator::evaluate($rest, $this->at('2026-03-06 09:00'), $this->at('2026-03-06 12:00'));
        $this->assertFalse($result['scheduled']);
        $this->assertSame(180, $result['outside_shift_minutes']);
        $this->assertSame(0, $result['worked_minutes']);

        $day = $this->day('2026-03-01', $this->definition('08:00', '17:00', 60), 15, 15);
        $missing = HrShiftCalculator::evaluate($day, $this->at('2026-03-01 08:30'), null);
        $this->assertTrue($missing['missing_check_out']);
        $this->assertSame(30, $missing['late_minutes']);
        $this->assertSame(480, $missing['shortfall_minutes']);
    }

    private function definition(string $start, string $end, int $break, ?string $breakStart = null): ShiftDefinition
    {
        return new ShiftDefinition(1, 'S', 'Shift', Shift::TYPE_CUSTOM, null, $start, $end, $breakStart, $break, null, null, null, [0, 1, 2, 3, 4], [5, 6]);
    }

    private function day(string $date, ShiftDefinition $definition, int $lateGrace, int $earlyGrace): ScheduledShiftDay
    {
        return ScheduledShiftDay::working($date, $definition, self::TZ, $lateGrace, $earlyGrace);
    }

    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($time, self::TZ);
    }
}
