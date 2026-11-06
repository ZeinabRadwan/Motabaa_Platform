<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

/**
 * Work location, shift and electronic employee setup for attendance tests (HrTestCase subclasses).
 */
trait InteractsWithElectronicAttendance
{
    protected const SITE = [24.7136, 46.6753];

    protected int $siteId;

    protected int $shiftId;

    protected function setUpElectronicAttendance(): void
    {
        $this->at('2026-03-01 07:00'); // Sunday
        $this->actingAsUser($this->hrManager);
        $this->siteId = $this->postJson('/api/hr/work-locations', [
            'branch_id' => $this->defaultBranchId(), 'name_ar' => 'المقر', 'name_en' => 'Head office',
            'latitude' => self::SITE[0], 'longitude' => self::SITE[1], 'radius_meters' => 100,
        ])->assertOk()->json('data.id');
        $this->shiftId = $this->createShift('AM', '08:00', '16:00');
    }

    /** @return array{0: User, 1: int} */
    protected function electronicEmployee(array $attendance = [], string $email = 'emp@example.test', array $employee = []): array
    {
        $user = $this->makeUser($email, 'teacher', self::CENTER, ['apply_hr-self-attendance']);
        $employeeId = $this->createEmployee($user, $employee);
        $this->actingAsUser($this->hrManager)->putJson("/api/hr/employees/{$employeeId}/attendance", $attendance + [
            'attendance_mode' => 'electronic',
            'gps_policy' => 'required',
            'shift_id' => $this->shiftId,
            'work_location_id' => $this->siteId,
        ])->assertOk();

        return [$user, $employeeId];
    }

    protected function createShift(string $code, string $start, string $end, array $days = [0, 1, 2, 3, 4]): int
    {
        return (int) $this->actingAsUser($this->hrManager)->postJson('/api/hr/shifts', [
            'code' => $code, 'name_ar' => $code, 'start_time' => $start, 'end_time' => $end, 'working_days' => $days,
        ])->assertOk()->json('data.id');
    }

    protected function createPolicy(array $overrides = []): int
    {
        return (int) $this->actingAsUser($this->hrManager)
            ->postJson('/api/hr/attendance-policies', $overrides + ['code' => 'STD', 'name_ar' => 'قياسية'])
            ->assertOk()
            ->json('data.id');
    }

    protected function updateSettings(array $attendance): void
    {
        $this->actingAsUser($this->hrManager)->putJson('/api/hr/settings', ['settings' => ['attendance' => $attendance]])->assertOk();
    }

    /** A fix $meters north of the work location: [latitude, longitude, accuracy]. */
    protected function near(float $meters, float $accuracy = 15): array
    {
        return [self::SITE[0] + $meters / 111_195, self::SITE[1], $accuracy];
    }

    protected function punch(User $user, string $action, ?array $fix, array $extra = []): TestResponse
    {
        return $this->actingAsUser($user)->postJson("/api/hr/my-attendance/{$action}", $this->fixPayload($fix) + $extra);
    }

    protected function fixPayload(?array $fix): array
    {
        return $fix === null ? [] : ['latitude' => $fix[0], 'longitude' => $fix[1], 'accuracy' => $fix[2]];
    }

    protected function at(string $time): void
    {
        $this->travelTo(Carbon::parse($time, 'Asia/Riyadh'));
    }
}
