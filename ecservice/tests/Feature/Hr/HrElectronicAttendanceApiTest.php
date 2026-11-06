<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * Website GPS check-in / check-out: geofence verification per GPS requirement, check-in windows, duplicate and
 * missing check-out handling, overnight shifts, one open session, eligibility and authorization.
 */
class HrElectronicAttendanceApiTest extends HrTestCase
{
    private const SITE = [24.7136, 46.6753];

    private int $siteId;

    private int $shiftId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->at('2026-03-01 07:00'); // Sunday
        $this->actingAsUser($this->hrManager);
        $this->siteId = $this->postJson('/api/hr/work-locations', [
            'branch_id' => $this->defaultBranchId(), 'name_ar' => 'المقر', 'name_en' => 'Head office',
            'latitude' => self::SITE[0], 'longitude' => self::SITE[1], 'radius_meters' => 100,
        ])->assertOk()->json('data.id');
        $this->shiftId = $this->createShift('AM', '08:00', '16:00');
    }

    public function test_check_in_inside_the_radius_is_verified_and_stores_the_evidence(): void
    {
        [$user, $employee] = $this->electronicEmployee();
        $this->at('2026-03-01 07:55');

        $response = $this->punch($user, 'check-in', $this->near(30), ['timestamp' => '2020-01-01T00:00:00Z'])->assertOk();

        $response->assertJsonPath('data.result.verification', 'verified')
            ->assertJsonPath('data.result.duplicate', false)
            ->assertJsonPath('data.record.status', 'checked_in')
            ->assertJsonPath('data.record.check_in.time', '07:55')
            ->assertJsonPath('data.record.check_in.verification', 'verified')
            ->assertJsonPath('data.can_check_in', false)
            // Check-out opens at the shift start (no check-out-before limit).
            ->assertJsonPath('data.can_check_out', false)
            ->assertJsonPath('data.hint.key', 'check_out_opens')
            ->assertJsonPath('data.hint.time', '08:00');
        $this->assertStringContainsString('+03:00', $response->json('data.record.check_in.at'));

        $row = DB::table('hr_attendance_records')->where('employee_id', $employee)->first();
        // The server clock is used; any client timestamp is ignored.
        $this->assertSame('2026-03-01 07:55:00', (string) $row->check_in_at);
        $this->assertSame('2026-03-01', substr((string) $row->attendance_date, 0, 10));
        $this->assertEqualsWithDelta(self::SITE[0] + 30 / 111_195, (float) $row->check_in_latitude, 0.000001);
        $this->assertEqualsWithDelta(self::SITE[1], (float) $row->check_in_longitude, 0.000001);
        $this->assertEquals(15, (float) $row->check_in_accuracy_meters);
        $this->assertEqualsWithDelta(30, (float) $row->check_in_distance_meters, 1);
        $this->assertSame($this->siteId, (int) $row->work_location_id);
        $this->assertSame(100, (int) $row->location_radius_meters);
        $this->assertSame('127.0.0.1', $row->check_in_ip);
        $this->assertSame('electronic', $row->attendance_method);
        $this->assertSame('required', $row->gps_requirement);
        $this->assertSame($this->shiftId, (int) $row->shift_id);
        $this->assertSame($employee, (int) $row->open_session_key);
        $this->assertSame(0, (int) $row->needs_review);

        $punch = DB::table('hr_attendance_punches')->where('employee_id', $employee)->first();
        $this->assertSame(['check_in', 'accepted', 'verified'], [$punch->type, $punch->result, $punch->verification]);
        $this->assertSame((int) $row->id, (int) $punch->attendance_record_id);
    }

    public function test_check_out_records_location_and_working_duration(): void
    {
        [$user, $employee] = $this->electronicEmployee();
        $this->at('2026-03-01 07:55');
        $this->punch($user, 'check-in', $this->near(20))->assertOk();

        $this->at('2026-03-01 12:00');
        $this->actingAsUser($user)->getJson('/api/hr/my-attendance')->assertOk()
            ->assertJsonPath('data.can_check_out', true)
            ->assertJsonPath('data.record.duration_minutes', 245);

        $this->at('2026-03-01 16:10');
        $this->punch($user, 'check-out', $this->near(40, 8))->assertOk()
            ->assertJsonPath('data.record.status', 'checked_out')
            ->assertJsonPath('data.record.check_out.time', '16:10')
            ->assertJsonPath('data.record.check_out.verification', 'verified')
            ->assertJsonPath('data.record.duration_minutes', 495)
            ->assertJsonPath('data.record.worked_minutes', 480)
            ->assertJsonPath('data.hint.key', 'checked_out');

        $row = DB::table('hr_attendance_records')->where('employee_id', $employee)->first();
        $this->assertSame('2026-03-01 16:10:00', (string) $row->check_out_at);
        $this->assertEquals(8, (float) $row->check_out_accuracy_meters);
        $this->assertEqualsWithDelta(40, (float) $row->check_out_distance_meters, 1);
        $this->assertSame('checked_out', $row->status);
        $this->assertNull($row->open_session_key);
    }

    public function test_required_gps_rejects_attempts_it_cannot_verify_and_logs_them(): void
    {
        [$user, $employee] = $this->electronicEmployee();
        $this->at('2026-03-01 07:55');

        $this->punch($user, 'check-in', $this->near(1000))->assertStatus(422)
            ->assertJsonPath('reason', 'attendance_outside_radius')
            ->assertJsonPath('details.verification', 'outside_radius')
            ->assertJsonPath('details.radius_meters', 100);
        $this->punch($user, 'check-in', $this->near(10, 500))->assertStatus(422)->assertJsonPath('reason', 'attendance_low_accuracy');
        $this->punch($user, 'check-in', null, ['location_error' => 'permission_denied'])->assertStatus(422)
            ->assertJsonPath('reason', 'attendance_location_denied');
        $this->punch($user, 'check-in', null, ['location_error' => 'timeout'])->assertStatus(422)
            ->assertJsonPath('reason', 'attendance_location_unavailable');

        // A GPS failure is not an absence: nothing is recorded, the attempts are only logged.
        $this->assertSame(0, DB::table('hr_attendance_records')->count());
        $punches = DB::table('hr_attendance_punches')->where('employee_id', $employee)->orderBy('id')->get();
        $this->assertSame(['rejected'], $punches->pluck('result')->unique()->values()->all());
        $this->assertSame(
            ['attendance_outside_radius', 'attendance_low_accuracy', 'attendance_location_denied', 'attendance_location_unavailable'],
            $punches->pluck('reason')->all(),
        );
        $this->assertEqualsWithDelta(1000, (float) $punches[0]->distance_meters, 2);
        $this->assertSame('permission_denied', $punches[2]->location_error);

        // Inside the radius the employee can still check in afterwards.
        $this->punch($user, 'check-in', $this->near(10))->assertOk();
    }

    public function test_flexible_gps_accepts_unverified_attempts_and_flags_them_for_review(): void
    {
        [$user, $employee] = $this->electronicEmployee(['gps_policy' => 'flexible']);
        $this->at('2026-03-01 07:55');

        $this->punch($user, 'check-in', null, ['location_error' => 'permission_denied'])->assertOk()
            ->assertJsonPath('data.result.verification', 'location_unavailable')
            ->assertJsonPath('data.record.status', 'checked_in')
            ->assertJsonPath('data.record.needs_review', true)
            ->assertJsonPath('data.record.review_reasons', ['location_unavailable']);

        $this->at('2026-03-01 16:05');
        $this->punch($user, 'check-out', $this->near(800))->assertOk()
            ->assertJsonPath('data.record.check_out.verification', 'outside_radius')
            ->assertJsonPath('data.record.review_reasons', ['location_unavailable', 'outside_radius']);

        $this->assertSame(1, (int) DB::table('hr_attendance_records')->where('employee_id', $employee)->value('needs_review'));
    }

    public function test_policy_without_gps_records_attendance_without_verifying(): void
    {
        $policy = $this->createPolicy(['gps_requirement' => 'none']);
        [$user] = $this->electronicEmployee(['gps_policy' => 'policy', 'attendance_policy_id' => $policy]);
        $this->at('2026-03-01 08:05');

        $this->actingAsUser($user)->getJson('/api/hr/my-attendance')->assertOk()->assertJsonPath('data.gps_requirement', 'none');
        $this->punch($user, 'check-in', null)->assertOk()
            ->assertJsonPath('data.record.check_in.verification', 'not_required')
            ->assertJsonPath('data.record.needs_review', false);
    }

    public function test_repeated_check_in_is_idempotent_inside_the_duplicate_window_and_refused_after(): void
    {
        [$user, $employee] = $this->electronicEmployee();
        $this->at('2026-03-01 07:55');
        $this->punch($user, 'check-in', $this->near(10))->assertOk();

        // A retry after a lost response returns the existing record.
        $this->at('2026-03-01 07:57');
        $this->punch($user, 'check-in', $this->near(10))->assertOk()
            ->assertJsonPath('data.result.duplicate', true)
            ->assertJsonPath('data.record.check_in.time', '07:55');

        $this->at('2026-03-01 08:30');
        $this->punch($user, 'check-in', $this->near(10))->assertStatus(422)->assertJsonPath('reason', 'attendance_already_checked_in');

        $this->assertSame(1, DB::table('hr_attendance_records')->count());
        $this->assertSame(['accepted', 'duplicate', 'rejected'], DB::table('hr_attendance_punches')->where('employee_id', $employee)->orderBy('id')->pluck('result')->all());

        // After checking out, the same shift cannot be checked into again.
        $this->at('2026-03-01 09:00');
        $this->punch($user, 'check-out', $this->near(10))->assertOk();
        $this->at('2026-03-01 09:30');
        $this->punch($user, 'check-in', $this->near(10))->assertStatus(422)->assertJsonPath('reason', 'attendance_already_recorded');
    }

    public function test_check_out_requires_a_check_in(): void
    {
        [$user] = $this->electronicEmployee();
        $this->at('2026-03-01 16:05');

        $this->punch($user, 'check-out', $this->near(10))->assertStatus(422)->assertJsonPath('reason', 'attendance_not_checked_in');
        $this->assertSame(0, DB::table('hr_attendance_records')->count());
    }

    public function test_check_in_and_check_out_respect_the_policy_windows(): void
    {
        $policy = $this->createPolicy([
            'check_in_before_minutes' => 30, 'check_in_after_minutes' => 60, 'check_out_before_minutes' => 15, 'early_leave_grace_minutes' => 0,
        ]);
        [$user] = $this->electronicEmployee(['gps_policy' => 'required', 'attendance_policy_id' => $policy]);

        $this->at('2026-03-01 07:00');
        $this->actingAsUser($user)->getJson('/api/hr/my-attendance')->assertOk()
            ->assertJsonPath('data.can_check_in', false)
            ->assertJsonPath('data.hint.key', 'check_in_opens')
            ->assertJsonPath('data.check_in_window.from_time', '07:30')
            ->assertJsonPath('data.check_in_window.to_time', '09:00');
        $this->punch($user, 'check-in', $this->near(10))->assertStatus(422)->assertJsonPath('reason', 'attendance_check_in_not_open');

        $this->at('2026-03-01 09:01');
        $this->punch($user, 'check-in', $this->near(10))->assertStatus(422)->assertJsonPath('reason', 'attendance_check_in_closed');

        $this->at('2026-03-01 08:20');
        $this->punch($user, 'check-in', $this->near(10))->assertOk()->assertJsonPath('data.record.late_minutes', 20);

        $this->at('2026-03-01 15:00');
        $this->punch($user, 'check-out', $this->near(10))->assertStatus(422)->assertJsonPath('reason', 'attendance_check_out_not_open');
        $this->at('2026-03-01 15:50');
        $this->punch($user, 'check-out', $this->near(10))->assertOk()->assertJsonPath('data.record.early_departure_minutes', 10);

        // Friday is a rest day of the shift.
        $this->at('2026-03-06 09:00');
        $this->actingAsUser($user)->getJson('/api/hr/my-attendance')->assertOk()->assertJsonPath('data.hint.key', 'no_shift');
        $this->punch($user, 'check-in', $this->near(10))->assertStatus(422)->assertJsonPath('reason', 'attendance_no_shift');
    }

    public function test_second_check_out_follows_the_duplicate_punch_action(): void
    {
        [$user, $employee] = $this->electronicEmployee();
        $this->at('2026-03-01 07:55');
        $this->punch($user, 'check-in', $this->near(10))->assertOk();
        $this->at('2026-03-01 16:00');
        $this->punch($user, 'check-out', $this->near(10))->assertOk();

        // first_in_last_out (default): the later check-out replaces the earlier one.
        $this->at('2026-03-01 16:30');
        $this->actingAsUser($user)->getJson('/api/hr/my-attendance')->assertOk()->assertJsonPath('data.can_check_out', true);
        $this->punch($user, 'check-out', $this->near(10))->assertOk()
            ->assertJsonPath('data.record.check_out.time', '16:30')
            ->assertJsonPath('data.record.needs_review', false);

        $rejecting = $this->createPolicy(['code' => 'REJ', 'duplicate_punch_action' => 'reject']);
        [$other] = $this->electronicEmployee(['attendance_policy_id' => $rejecting], 'other@example.test');
        $this->at('2026-03-02 07:55');
        $this->punch($other, 'check-in', $this->near(10))->assertOk();
        $this->at('2026-03-02 16:00');
        $this->punch($other, 'check-out', $this->near(10))->assertOk();
        $this->at('2026-03-02 16:30');
        $this->actingAsUser($other)->getJson('/api/hr/my-attendance')->assertOk()->assertJsonPath('data.can_check_out', false);
        $this->punch($other, 'check-out', $this->near(10))->assertStatus(422)->assertJsonPath('reason', 'attendance_already_checked_out');
    }

    public function test_overnight_shift_check_out_belongs_to_the_day_it_started_and_only_one_session_is_open(): void
    {
        $night = $this->createShift('NT', '22:00', '06:00', [0, 1, 2, 3, 4, 5]);
        [$user, $employee] = $this->electronicEmployee(['shift_id' => $night]);

        $this->at('2026-03-01 21:50');
        $this->punch($user, 'check-in', $this->near(10))->assertOk()->assertJsonPath('data.record.attendance_date', '2026-03-01');

        // Next morning the open session from yesterday is shown and blocks another check-in.
        $this->at('2026-03-02 06:05');
        $this->actingAsUser($user)->getJson('/api/hr/my-attendance')->assertOk()
            ->assertJsonPath('data.record.attendance_date', '2026-03-01')
            ->assertJsonPath('data.can_check_in', false)
            ->assertJsonPath('data.can_check_out', true);
        $this->punch($user, 'check-in', $this->near(10))->assertStatus(422)->assertJsonPath('reason', 'attendance_already_checked_in');

        $this->punch($user, 'check-out', $this->near(10))->assertOk()
            ->assertJsonPath('data.record.attendance_date', '2026-03-01')
            ->assertJsonPath('data.record.check_out.date', '2026-03-02')
            ->assertJsonPath('data.record.worked_minutes', 480);

        $this->assertSame(1, DB::table('hr_attendance_records')->where('employee_id', $employee)->count());
        $this->assertSame(0, DB::table('hr_attendance_records')->whereNotNull('open_session_key')->count());

        // That evening's shift is a new session.
        $this->at('2026-03-02 21:55');
        $this->punch($user, 'check-in', $this->near(10))->assertOk()->assertJsonPath('data.record.attendance_date', '2026-03-02');
    }

    public function test_database_refuses_a_second_open_session_even_without_the_row_lock(): void
    {
        [$user, $employee] = $this->electronicEmployee();
        $this->at('2026-03-01 07:55');
        $this->punch($user, 'check-in', $this->near(10))->assertOk();

        $row = (array) DB::table('hr_attendance_records')->where('employee_id', $employee)->first();
        unset($row['id']);
        $row['attendance_date'] = '2026-03-02';

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('hr_attendance_records')->insert($row);
    }

    public function test_open_session_past_its_check_out_window_follows_the_missing_check_out_action(): void
    {
        [$user, $employee] = $this->electronicEmployee();
        $this->at('2026-03-01 07:55');
        $this->punch($user, 'check-in', $this->near(10))->assertOk();

        // Default flag_for_review: the session closes without a check-out (deadline 16:00 + 240 min).
        $this->at('2026-03-01 20:01');
        $this->artisan('hr:close-missed-checkouts')->assertSuccessful();
        $row = DB::table('hr_attendance_records')->where('employee_id', $employee)->first();
        $this->assertSame('missing_check_out', $row->status);
        $this->assertNull($row->check_out_at);
        $this->assertNull($row->open_session_key);
        $this->assertSame(['missing_check_out'], json_decode($row->review_reasons, true));
        $this->punch($user, 'check-out', $this->near(10))->assertStatus(422)->assertJsonPath('reason', 'attendance_check_out_closed');

        // auto_checkout_at_shift_end: closed at the scheduled end, applied when the employee opens the page.
        $auto = $this->createPolicy(['code' => 'AUTO', 'missing_checkout_action' => 'auto_checkout_at_shift_end']);
        [$other, $otherEmployee] = $this->electronicEmployee(['attendance_policy_id' => $auto], 'other@example.test');
        $this->at('2026-03-02 07:55');
        $this->punch($other, 'check-in', $this->near(10))->assertOk();
        $this->at('2026-03-03 07:30');
        $this->actingAsUser($other)->getJson('/api/hr/my-attendance')->assertOk()
            ->assertJsonPath('data.can_check_in', true)
            ->assertJsonPath('data.record', null);
        $row = DB::table('hr_attendance_records')->where('employee_id', $otherEmployee)->first();
        $this->assertSame('checked_out', $row->status);
        $this->assertSame('2026-03-02 16:00:00', (string) $row->check_out_at);
        $this->assertSame('auto_closed', $row->check_out_verification);
        $this->assertSame(480, (int) $row->worked_minutes);
    }

    public function test_manual_exempt_and_non_employee_users_cannot_punch(): void
    {
        $manualUser = $this->makeUser('manual@example.test', 'teacher', self::CENTER, ['apply_hr-self-attendance']);
        $this->createEmployee($manualUser);
        $this->at('2026-03-01 07:55');
        $this->actingAsUser($manualUser)->getJson('/api/hr/my-attendance')->assertOk()
            ->assertJsonPath('data.eligible', false)
            ->assertJsonPath('data.ineligible_reason', 'manual')
            ->assertJsonPath('data.can_check_in', false);
        $this->punch($manualUser, 'check-in', $this->near(10))->assertStatus(403)->assertJsonPath('reason', 'attendance_ineligible_manual');

        [$exemptUser] = $this->electronicEmployee(['attendance_mode' => 'exempt'], 'exempt@example.test');
        $this->punch($exemptUser, 'check-in', $this->near(10))->assertStatus(403)->assertJsonPath('reason', 'attendance_ineligible_exempt');

        $stranger = $this->makeUser('stranger@example.test', 'teacher', self::CENTER, ['apply_hr-self-attendance']);
        $this->actingAsUser($stranger)->getJson('/api/hr/my-attendance')->assertOk()
            ->assertJsonPath('data.eligible', false)
            ->assertJsonPath('data.ineligible_reason', 'not_employee');
        $this->punch($stranger, 'check-in', $this->near(10))->assertStatus(403)->assertJsonPath('reason', 'attendance_ineligible_not_employee');
        $this->actingAsUser($stranger)->getJson('/api/hr/my-attendance/history')->assertStatus(403);

        // An electronic employee without the self-service permission is refused by the route.
        $noPermission = $this->makeUser('noperm@example.test', 'teacher');
        $this->createEmployee($noPermission);
        $this->actingAsUser($noPermission)->getJson('/api/hr/my-attendance')->assertStatus(403);
        $this->punch($noPermission, 'check-in', $this->near(10))->assertStatus(403);

        $this->assertSame(0, DB::table('hr_attendance_records')->count());
    }

    public function test_deactivated_employees_cannot_punch(): void
    {
        [$user, $employee] = $this->electronicEmployee();
        $this->actingAsUser($this->hrManager)->patchJson("/api/hr/employees/{$employee}/deactivate", [
            'end_date' => '2026-03-02', 'termination_reason' => 'resignation', 'notes' => 'Resigned',
        ])->assertOk();
        $this->at('2026-03-01 07:55');

        $this->punch($user, 'check-in', $this->near(10))->assertStatus(403)->assertJsonPath('reason', 'attendance_ineligible_not_employed');
    }

    public function test_submitted_coordinates_are_validated(): void
    {
        [$user] = $this->electronicEmployee();
        $this->at('2026-03-01 07:55');

        $this->punch($user, 'check-in', [95, 46.6, 10])->assertStatus(422)->assertJsonValidationErrors('latitude');
        $this->punch($user, 'check-in', [24.7, 190, 10])->assertStatus(422)->assertJsonValidationErrors('longitude');
        $this->punch($user, 'check-in', [24.7, 46.6, -5])->assertStatus(422)->assertJsonValidationErrors('accuracy');
        $this->punch($user, 'check-in', [0, 0, 10])->assertStatus(422)->assertJsonValidationErrors('latitude');
        $this->actingAsUser($user)->postJson('/api/hr/my-attendance/check-in', ['latitude' => 24.7])->assertStatus(422)
            ->assertJsonValidationErrors(['longitude', 'accuracy']);
        $this->punch($user, 'check-in', null, ['location_error' => 'made_up'])->assertStatus(422)->assertJsonValidationErrors('location_error');

        $this->assertSame(0, DB::table('hr_attendance_records')->count());
    }

    public function test_history_is_limited_to_the_users_own_records_and_hr_sees_the_attempt_log(): void
    {
        [$user, $employee] = $this->electronicEmployee();
        [$other] = $this->electronicEmployee([], 'other@example.test');
        $this->at('2026-03-01 07:55');
        $this->punch($user, 'check-in', $this->near(1000))->assertStatus(422);
        $this->punch($user, 'check-in', $this->near(10))->assertOk();
        $this->punch($other, 'check-in', $this->near(10))->assertOk();

        $this->actingAsUser($user)->getJson('/api/hr/my-attendance/history')->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.employee_id', $employee);

        // Employees cannot read other employees' logs through the HR endpoints.
        $this->actingAsUser($user)->getJson("/api/hr/employees/{$employee}/attendance-records")->assertStatus(403);

        $this->actingAsUser($this->hrManager);
        $this->getJson("/api/hr/employees/{$employee}/attendance-records")->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.check_in.verification', 'verified');
        $this->getJson("/api/hr/employees/{$employee}/attendance-punches?result=rejected")->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.reason', 'attendance_outside_radius');
    }

    /** @return array{0: User, 1: int} */
    private function electronicEmployee(array $attendance = [], string $email = 'emp@example.test'): array
    {
        $user = $this->makeUser($email, 'teacher', self::CENTER, ['apply_hr-self-attendance']);
        $employee = $this->createEmployee($user);
        $this->actingAsUser($this->hrManager)->putJson("/api/hr/employees/{$employee}/attendance", $attendance + [
            'attendance_mode' => 'electronic',
            'gps_policy' => 'required',
            'shift_id' => $this->shiftId,
            'work_location_id' => $this->siteId,
        ])->assertOk();

        return [$user, $employee];
    }

    private function createShift(string $code, string $start, string $end, array $days = [0, 1, 2, 3, 4]): int
    {
        return (int) $this->actingAsUser($this->hrManager)->postJson('/api/hr/shifts', [
            'code' => $code, 'name_ar' => $code, 'start_time' => $start, 'end_time' => $end, 'working_days' => $days,
        ])->assertOk()->json('data.id');
    }

    private function createPolicy(array $overrides = []): int
    {
        return (int) $this->actingAsUser($this->hrManager)
            ->postJson('/api/hr/attendance-policies', $overrides + ['code' => 'STD', 'name_ar' => 'قياسية'])
            ->assertOk()
            ->json('data.id');
    }

    /** A fix $meters north of the work location. */
    private function near(float $meters, float $accuracy = 15): array
    {
        return [self::SITE[0] + $meters / 111_195, self::SITE[1], $accuracy];
    }

    private function punch(User $user, string $action, ?array $fix, array $extra = []): TestResponse
    {
        $payload = $fix === null ? [] : ['latitude' => $fix[0], 'longitude' => $fix[1], 'accuracy' => $fix[2]];

        return $this->actingAsUser($user)->postJson("/api/hr/my-attendance/{$action}", $payload + $extra);
    }

    private function at(string $time): void
    {
        $this->travelTo(Carbon::parse($time, 'Asia/Riyadh'));
    }
}
