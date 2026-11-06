<?php

namespace Tests\Unit;

use App\Models\Hr\LocationCheck;
use App\Support\Hr\HrGeofence;
use App\Support\Hr\HrLocationMonitor;
use App\Support\Hr\HrLocationSchedule;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class HrLocationScheduleTest extends TestCase
{
    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse("2026-03-01 {$time}", 'Asia/Riyadh');
    }

    private function times(array $slots): array
    {
        return array_map(fn (CarbonImmutable $slot) => $slot->format('H:i'), $slots);
    }

    public function test_the_next_check_is_due_thirty_minutes_after_the_last_one_until_the_shift_ends(): void
    {
        $end = $this->at('16:00');

        $this->assertSame('08:25', HrLocationSchedule::nextAfter($this->at('07:55'), 30, $end)->format('H:i'));
        $this->assertSame('15:59', HrLocationSchedule::nextAfter($this->at('15:29'), 30, $end)->format('H:i'));
        // A check falling at or after the shift end is not scheduled.
        $this->assertNull(HrLocationSchedule::nextAfter($this->at('15:30'), 30, $end));
        $this->assertNull(HrLocationSchedule::nextAfter($this->at('15:45'), 30, $end));
        // Without a known end the schedule continues.
        $this->assertSame('16:15', HrLocationSchedule::nextAfter($this->at('15:45'), 30, null)->format('H:i'));
    }

    public function test_slots_missed_during_a_suspension_are_listed_one_interval_apart(): void
    {
        [$slots, $next] = HrLocationSchedule::dueSlots($this->at('08:30'), $this->at('10:05'), 30, $this->at('16:00'));

        $this->assertSame(['08:30', '09:00', '09:30', '10:00'], $this->times($slots));
        $this->assertSame('10:30', $next->format('H:i'));

        // Nothing is due yet.
        [$slots, $next] = HrLocationSchedule::dueSlots($this->at('08:30'), $this->at('08:29'), 30, $this->at('16:00'));
        $this->assertSame([], $slots);
        $this->assertSame('08:30', $next->format('H:i'));

        // A slot exactly at the current time is due.
        [$slots] = HrLocationSchedule::dueSlots($this->at('08:30'), $this->at('08:30'), 30, $this->at('16:00'));
        $this->assertSame(['08:30'], $this->times($slots));
    }

    public function test_no_slot_is_listed_after_the_shift_end(): void
    {
        [$slots, $next] = HrLocationSchedule::dueSlots($this->at('14:30'), $this->at('18:00'), 30, $this->at('16:00'));

        $this->assertSame(['14:30', '15:00', '15:30'], $this->times($slots));
        $this->assertNull($next);
    }

    public function test_a_check_is_due_from_one_minute_before_its_time_and_overdue_five_minutes_after(): void
    {
        $due = $this->at('08:30');

        $this->assertFalse(HrLocationSchedule::isDue($due, $this->at('08:28:59')));
        $this->assertTrue(HrLocationSchedule::isDue($due, $this->at('08:29:00')));
        $this->assertTrue(HrLocationSchedule::isDue($due, $this->at('09:40')));
        $this->assertFalse(HrLocationSchedule::isDue(null, $this->at('09:40')));

        $this->assertFalse(HrLocationSchedule::isOverdue($due, $this->at('08:35')));
        $this->assertTrue(HrLocationSchedule::isOverdue($due, $this->at('08:35:01')));
    }

    public function test_a_location_answers_the_latest_slot_within_one_interval_before_it(): void
    {
        $pending = [$this->at('08:30'), $this->at('09:00'), $this->at('09:30'), $this->at('10:00')];

        $this->assertSame('10:00', HrLocationSchedule::slotFor($pending, $this->at('10:05'), 30)->format('H:i'));
        // Sent a few seconds early (client timer) it still answers the coming slot.
        $this->assertSame('10:00', HrLocationSchedule::slotFor($pending, $this->at('09:59:30'), 30)->format('H:i'));
        // An older queued mobile sample answers the slot it belongs to.
        $this->assertSame('09:00', HrLocationSchedule::slotFor($pending, $this->at('09:10'), 30)->format('H:i'));
        // A location more than one interval after every slot answers none of them.
        $this->assertNull(HrLocationSchedule::slotFor($pending, $this->at('10:40'), 30));
        $this->assertNull(HrLocationSchedule::slotFor([], $this->at('10:40'), 30));
    }

    public function test_geofence_results_map_to_location_check_statuses(): void
    {
        $site = ['latitude' => 24.7136, 'longitude' => 46.6753, 'radius' => 100];
        $fix = fn (float $meters, float $accuracy = 10) => ['latitude' => 24.7136 + $meters / 111_195, 'longitude' => 46.6753, 'accuracy' => $accuracy];
        $status = fn (?array $site, ?array $fix, ?string $error = null, bool $mocked = false) => HrLocationMonitor::statusFor(HrGeofence::check('required', $site, $fix, 100), $error, $mocked);

        $this->assertSame([LocationCheck::STATUS_WITHIN_RADIUS, null], $status($site, $fix(40)));
        $this->assertSame([LocationCheck::STATUS_OUTSIDE_RADIUS, null], $status($site, $fix(400)));
        $this->assertSame([LocationCheck::STATUS_VERIFICATION_FAILED, 'low_accuracy'], $status($site, $fix(40, 500)));
        $this->assertSame([LocationCheck::STATUS_VERIFICATION_FAILED, 'location_not_configured'], $status(null, $fix(40)));
        $this->assertSame([LocationCheck::STATUS_PERMISSION_DENIED, 'permission_denied'], $status($site, null, 'permission_denied'));
        $this->assertSame([LocationCheck::STATUS_GPS_UNAVAILABLE, 'timeout'], $status($site, null, 'timeout'));
        $this->assertSame([LocationCheck::STATUS_GPS_UNAVAILABLE, 'position_unavailable'], $status($site, null));
        // A mocked (spoofed) position never verifies, even inside the radius.
        $this->assertSame([LocationCheck::STATUS_VERIFICATION_FAILED, 'mocked_location'], $status($site, $fix(10), null, true));
    }
}
