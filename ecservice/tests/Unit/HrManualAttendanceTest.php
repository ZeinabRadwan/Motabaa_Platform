<?php

namespace Tests\Unit;

use App\Models\Hr\AttendanceRecord;
use App\Support\Hr\HrAttendanceImport;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HrManualAttendanceTest extends TestCase
{
    public static function dates(): array
    {
        return [
            'iso' => ['2026-03-01', '2026-03-01'],
            'day first' => ['01/03/2026', '2026-03-01'],
            'year first slash' => ['2026/03/01', '2026-03-01'],
            'day first dash' => ['01-03-2026', '2026-03-01'],
            'excel serial' => [46082, '2026-03-01'],
            'excel serial with time' => [46082.5, '2026-03-01'],
            'impossible date' => ['2026-02-30', null],
            'text' => ['tomorrow', null],
            'empty' => ['', null],
        ];
    }

    #[DataProvider('dates')]
    public function test_import_dates(mixed $value, ?string $expected): void
    {
        $this->assertSame($expected, HrAttendanceImport::date($value));
    }

    public static function times(): array
    {
        return [
            '24h' => ['08:00', '08:00'],
            'single digit hour' => ['8:05', '08:05'],
            'seconds' => ['17:30:59', '17:30'],
            'pm' => ['4:00 PM', '16:00'],
            'noon' => ['12:15 pm', '12:15'],
            'midnight am' => ['12:10 AM', '00:10'],
            'arabic evening' => ['5:30 م', '17:30'],
            'arabic morning' => ['7:45 ص', '07:45'],
            'excel fraction' => [0.5, '12:00'],
            'excel date time' => [46082.75, '18:00'],
            'empty' => [null, null],
            'hour out of range' => ['25:00', false],
            '13 pm' => ['13:00 PM', false],
            'text' => ['8am', false],
        ];
    }

    #[DataProvider('times')]
    public function test_import_times(mixed $value, string|false|null $expected): void
    {
        $this->assertSame($expected, HrAttendanceImport::time($value));
    }

    public static function statuses(): array
    {
        return [
            'present' => [['check_in_at' => '08:00', 'check_out_at' => '16:00'], 'present'],
            'late' => [['check_in_at' => '08:20', 'check_out_at' => '16:00', 'late_minutes' => 20], 'late'],
            'early' => [['check_in_at' => '08:00', 'check_out_at' => '15:00', 'early_departure_minutes' => 60], 'early_departure'],
            'both' => [['check_in_at' => '08:20', 'check_out_at' => '15:00', 'late_minutes' => 20, 'early_departure_minutes' => 60], 'late_early_departure'],
            'no check-in' => [['check_out_at' => '16:00', 'status' => AttendanceRecord::STATUS_MISSING_CHECK_IN], 'missing_check_in'],
            'no check-out' => [['check_in_at' => '08:00', 'status' => AttendanceRecord::STATUS_MISSING_CHECK_OUT], 'missing_check_out'],
            'open session' => [['check_in_at' => '08:00', 'status' => AttendanceRecord::STATUS_CHECKED_IN], 'in_progress'],
            'no schedule' => [['check_in_at' => '09:00', 'check_out_at' => '12:00', 'scheduled_start_at' => null], 'off_schedule'],
        ];
    }

    #[DataProvider('statuses')]
    public function test_attendance_status(array $values, string $expected): void
    {
        $time = fn (?string $value) => $value === null ? null : Carbon::parse("2026-03-01 {$value}");
        $record = new AttendanceRecord([
            'status' => $values['status'] ?? AttendanceRecord::STATUS_CHECKED_OUT,
            'check_in_at' => $time($values['check_in_at'] ?? null),
            'check_out_at' => $time($values['check_out_at'] ?? null),
            'scheduled_start_at' => array_key_exists('scheduled_start_at', $values) ? null : $time('08:00'),
            'late_minutes' => $values['late_minutes'] ?? 0,
            'early_departure_minutes' => $values['early_departure_minutes'] ?? 0,
        ]);

        $this->assertSame($expected, AttendanceRecord::attendanceStatusFor($record));
    }
}
