<?php

namespace Tests\Unit;

use App\Models\QuestionnaireTask;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class QuestionnaireTaskAvailabilityTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function taskWithDates(string $startsAt, string $endsAt): QuestionnaireTask
    {
        $task = new QuestionnaireTask();
        $task->setRawAttributes([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        return $task;
    }

    public function test_open_when_today_is_on_end_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-30 23:59:00', QuestionnaireTask::AVAILABILITY_TIMEZONE));

        $task = $this->taskWithDates('2026-08-30', '2026-09-30');

        $this->assertTrue($task->isOpen());
    }

    public function test_closed_when_today_is_after_end_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', QuestionnaireTask::AVAILABILITY_TIMEZONE));

        $task = $this->taskWithDates('2026-08-30', '2026-09-30');

        $this->assertFalse($task->isOpen());
        $this->assertSame('2026-10-10', $task->availabilityWindow()['today']);
    }

    public function test_open_when_today_is_within_inclusive_range(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 08:00:00', QuestionnaireTask::AVAILABILITY_TIMEZONE));

        $task = $this->taskWithDates('2026-10-01', '2026-10-31');

        $this->assertTrue($task->isOpen());
    }
}
