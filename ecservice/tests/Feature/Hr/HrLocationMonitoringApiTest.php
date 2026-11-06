<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * Periodic location checks from the attendance page: start at check-in, 30-minute schedule (configurable), missed
 * checks while the page is closed or suspended, network retries, permission and GPS failures, outside-radius
 * exceptions, the end at check-out / shift end, and the HR monitoring dashboard.
 */
class HrLocationMonitoringApiTest extends HrTestCase
{
    use InteractsWithElectronicAttendance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpElectronicAttendance();
    }

    public function test_check_in_starts_monitoring_every_thirty_minutes_with_the_check_in_as_first_check(): void
    {
        [$user, $employee] = $this->electronicEmployee();
        $this->at('2026-03-01 07:55');

        $this->punch($user, 'check-in', $this->near(20))->assertOk()
            ->assertJsonPath('data.location_monitoring.enabled', true)
            ->assertJsonPath('data.location_monitoring.monitored', true)
            ->assertJsonPath('data.location_monitoring.active', true)
            ->assertJsonPath('data.location_monitoring.interval_minutes', 30)
            ->assertJsonPath('data.location_monitoring.next_check_time', '08:25')
            ->assertJsonPath('data.location_monitoring.due', false)
            ->assertJsonPath('data.location_monitoring.status', 'within_radius')
            ->assertJsonPath('data.location_monitoring.last_check.kind', 'check_in');

        $check = DB::table('hr_location_checks')->where('employee_id', $employee)->first();
        $record = DB::table('hr_attendance_records')->where('employee_id', $employee)->first();
        $this->assertSame(['check_in', 'web', 'within_radius'], [$check->kind, $check->source, $check->status]);
        $this->assertSame((int) $record->id, (int) $check->attendance_record_id);
        $this->assertSame($this->siteId, (int) $check->work_location_id);
        $this->assertSame('2026-03-01 07:55:00', (string) $check->checked_at);
        $this->assertEqualsWithDelta(20, (float) $check->distance_meters, 1);
        $this->assertEquals(15, (float) $check->accuracy_meters);
        $this->assertSame(30, (int) $record->location_check_interval_minutes);
        $this->assertSame('2026-03-01 08:25:00', (string) $record->next_location_check_at);
    }

    public function test_a_due_check_is_stored_and_schedules_the_next_one_and_early_checks_are_refused(): void
    {
        [$user, $employee] = $this->electronicEmployee();
        $this->at('2026-03-01 07:55');
        $this->punch($user, 'check-in', $this->near(20))->assertOk();

        // Not due yet: refused and nothing stored.
        $this->at('2026-03-01 08:10');
        $this->check($user, $this->near(10))->assertStatus(409)
            ->assertJsonPath('reason', 'location_check_not_due')
            ->assertJsonPath('details.next_check_at', '2026-03-01T08:25:00+03:00');

        $this->at('2026-03-01 08:25');
        $this->check($user, $this->near(35, 12))->assertOk()
            ->assertJsonPath('data.check.status', 'within_radius')
            ->assertJsonPath('data.check.kind', 'periodic')
            ->assertJsonPath('data.check.source', 'web')
            ->assertJsonPath('data.check.due_time', '08:25')
            ->assertJsonPath('data.check.time', '08:25')
            ->assertJsonPath('data.location_monitoring.next_check_time', '08:55')
            ->assertJsonPath('data.location_monitoring.status', 'within_radius');

        $rows = DB::table('hr_location_checks')->where('employee_id', $employee)->orderBy('id')->get();
        $this->assertSame(['check_in', 'periodic'], $rows->pluck('kind')->all());
        $this->assertEqualsWithDelta(35, (float) $rows[1]->distance_meters, 1);
        $this->assertEquals(12, (float) $rows[1]->accuracy_meters);
        $this->assertSame(100, (int) $rows[1]->radius_meters);
    }

    public function test_the_interval_is_configured_in_hr_settings_and_kept_by_open_sessions(): void
    {
        $this->updateSettings(['location_check_interval_minutes' => 15]);
        [$user] = $this->electronicEmployee();
        $this->at('2026-03-01 07:55');
        $this->punch($user, 'check-in', $this->near(20))->assertOk()
            ->assertJsonPath('data.location_monitoring.interval_minutes', 15)
            ->assertJsonPath('data.location_monitoring.next_check_time', '08:10');

        // Changing the setting does not move the schedule of a session in progress.
        $this->updateSettings(['location_check_interval_minutes' => 60]);
        $this->at('2026-03-01 08:10');
        $this->check($user, $this->near(10))->assertOk()->assertJsonPath('data.location_monitoring.next_check_time', '08:25');

        $this->actingAsUser($this->hrManager)
            ->putJson('/api/hr/settings', ['settings' => ['attendance' => ['location_check_interval_minutes' => 2]]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('settings.attendance.location_check_interval_minutes');
    }

    public function test_consecutive_checks_outside_the_radius_form_one_exception_closed_when_back_inside(): void
    {
        [$user, $employee] = $this->electronicEmployee();
        $this->at('2026-03-01 07:55');
        $this->punch($user, 'check-in', $this->near(20))->assertOk();

        $this->at('2026-03-01 08:25');
        $this->check($user, $this->near(900))->assertOk()
            ->assertJsonPath('data.check.status', 'outside_radius')
            ->assertJsonPath('data.location_monitoring.open_exception', true)
            ->assertJsonPath('data.record.needs_review', true)
            ->assertJsonPath('data.record.review_reasons', ['left_work_location']);
        $this->at('2026-03-01 08:55');
        $this->check($user, $this->near(1500))->assertOk();

        $exception = DB::table('hr_location_exceptions')->where('employee_id', $employee)->sole();
        $this->assertSame(['open', 'pending', 2], [$exception->status, $exception->review_status, (int) $exception->checks_count]);
        $this->assertSame('2026-03-01 08:25:00', (string) $exception->started_at);
        $this->assertEqualsWithDelta(1500, (float) $exception->max_distance_meters, 2);

        $this->at('2026-03-01 09:25');
        $this->check($user, $this->near(30))->assertOk()->assertJsonPath('data.location_monitoring.open_exception', false);
        $exception = DB::table('hr_location_exceptions')->where('employee_id', $employee)->sole();
        $this->assertSame(['closed', '2026-03-01 09:25:00'], [$exception->status, (string) $exception->ended_at]);

        // Leaving again is a new exception.
        $this->at('2026-03-01 09:55');
        $this->check($user, $this->near(700))->assertOk();
        $this->assertSame(2, DB::table('hr_location_exceptions')->where('employee_id', $employee)->count());
        // The attendance itself is untouched: still checked in.
        $this->assertSame('checked_in', DB::table('hr_attendance_records')->where('employee_id', $employee)->value('status'));
    }

    public function test_checks_missed_while_the_page_was_closed_or_suspended_stay_pending_and_are_never_verified(): void
    {
        [$user, $employee] = $this->electronicEmployee();
        $this->at('2026-03-01 08:00');
        $this->punch($user, 'check-in', $this->near(20))->assertOk();

        // The tab was suspended for two hours: opening the page records the missed slots as pending and asks for a
        // check now.
        $this->at('2026-03-01 10:05');
        $this->actingAsUser($user)->getJson('/api/hr/my-attendance')->assertOk()
            ->assertJsonPath('data.location_monitoring.status', 'pending')
            ->assertJsonPath('data.location_monitoring.due', true)
            ->assertJsonPath('data.location_monitoring.missed_count', 3)
            ->assertJsonPath('data.record.status', 'checked_in');

        $this->check($user, $this->near(25))->assertOk()
            ->assertJsonPath('data.check.due_time', '10:00')
            ->assertJsonPath('data.check.time', '10:05')
            ->assertJsonPath('data.location_monitoring.status', 'within_radius')
            ->assertJsonPath('data.location_monitoring.next_check_time', '10:35')
            ->assertJsonPath('data.location_monitoring.missed_count', 3);

        $rows = DB::table('hr_location_checks')->where('employee_id', $employee)->where('kind', 'periodic')->orderBy('due_at')->get();
        $this->assertSame(['2026-03-01 08:30:00', '2026-03-01 09:00:00', '2026-03-01 09:30:00', '2026-03-01 10:00:00'], $rows->pluck('due_at')->map(fn ($at) => (string) $at)->all());
        $this->assertSame(['pending', 'pending', 'pending', 'within_radius'], $rows->pluck('status')->all());
        $this->assertSame(['system', 'system', 'system', 'web'], $rows->pluck('source')->all());
        $this->assertNull($rows[0]->checked_at);
        $this->assertNull($rows[0]->latitude);
        // Missing checks do not make the employee absent or flag the record.
        $record = DB::table('hr_attendance_records')->where('employee_id', $employee)->first();
        $this->assertSame(['checked_in', 0], [$record->status, (int) $record->needs_review]);
    }

    public function test_the_scheduler_records_due_checks_without_a_location_as_pending_once(): void
    {
        [$user, $employee] = $this->electronicEmployee();
        $this->at('2026-03-01 07:55');
        $this->punch($user, 'check-in', $this->near(20))->assertOk();

        $this->at('2026-03-01 08:31');
        $this->artisan('hr:record-missed-location-checks')->expectsOutput('Pending checks stored: 1')->assertSuccessful();
        $this->artisan('hr:record-missed-location-checks')->expectsOutput('Pending checks stored: 0')->assertSuccessful();

        $pending = DB::table('hr_location_checks')->where('employee_id', $employee)->where('status', 'pending')->sole();
        $this->assertSame('2026-03-01 08:25:00', (string) $pending->due_at);
        $this->assertSame('pending', DB::table('hr_attendance_records')->where('employee_id', $employee)->value('location_status'));

        // The page then answers that slot; a check is still due because it is unanswered.
        $this->actingAsUser($user)->getJson('/api/hr/my-attendance')->assertOk()->assertJsonPath('data.location_monitoring.due', true);
        $this->check($user, $this->near(10))->assertOk()->assertJsonPath('data.check.due_time', '08:25');
        $this->assertSame(0, DB::table('hr_location_checks')->where('employee_id', $employee)->where('status', 'pending')->count());
    }

    public function test_a_check_retried_after_a_network_failure_is_stored_once(): void
    {
        [$user, $employee] = $this->electronicEmployee();
        $this->at('2026-03-01 07:55');
        $this->punch($user, 'check-in', $this->near(20))->assertOk();

        $this->at('2026-03-01 08:25');
        $first = $this->check($user, $this->near(10), ['client_id' => 'slot-0825-a1b2'])->assertOk()->json('data.check.id');
        // The response was lost; the page retries with the same client id a minute later.
        $this->at('2026-03-01 08:26');
        $this->check($user, $this->near(12), ['client_id' => 'slot-0825-a1b2'])->assertOk()
            ->assertJsonPath('data.duplicate', true)
            ->assertJsonPath('data.check.id', $first);

        $this->assertSame(1, DB::table('hr_location_checks')->where('employee_id', $employee)->where('kind', 'periodic')->count());
        $this->check($user, $this->near(10), ['client_id' => 'bad id!'])->assertStatus(422)->assertJsonValidationErrors('client_id');
    }

    public function test_permission_denied_and_gps_failures_are_recorded_without_verifying_or_marking_absence(): void
    {
        [$user, $employee] = $this->electronicEmployee();
        $this->at('2026-03-01 07:55');
        $this->punch($user, 'check-in', $this->near(20))->assertOk();

        $this->at('2026-03-01 08:25');
        $this->check($user, null, ['location_error' => 'permission_denied'])->assertOk()
            ->assertJsonPath('data.check.status', 'permission_denied')
            ->assertJsonPath('data.location_monitoring.status', 'permission_denied');
        $this->at('2026-03-01 08:55');
        $this->check($user, null, ['location_error' => 'timeout'])->assertOk()->assertJsonPath('data.check.status', 'gps_unavailable');
        $this->at('2026-03-01 09:25');
        $this->check($user, $this->near(20, 800))->assertOk()
            ->assertJsonPath('data.check.status', 'verification_failed')
            ->assertJsonPath('data.check.reason', 'low_accuracy');

        $rows = DB::table('hr_location_checks')->where('employee_id', $employee)->where('kind', 'periodic')->orderBy('id')->get();
        $this->assertSame(['permission_denied', 'timeout', 'low_accuracy'], $rows->pluck('reason')->all());
        $this->assertNull($rows[0]->latitude);
        $this->assertSame(0, DB::table('hr_location_exceptions')->count());
        $record = DB::table('hr_attendance_records')->where('employee_id', $employee)->first();
        $this->assertSame(['checked_in', 0], [$record->status, (int) $record->needs_review]);
    }

    public function test_check_out_stops_monitoring_and_ends_the_open_exception(): void
    {
        [$user, $employee] = $this->electronicEmployee();
        $this->at('2026-03-01 07:55');
        $this->punch($user, 'check-in', $this->near(20))->assertOk();
        $this->at('2026-03-01 08:25');
        $this->check($user, $this->near(900))->assertOk();

        $this->at('2026-03-01 09:00');
        $this->punch($user, 'check-out', $this->near(15))->assertOk()
            ->assertJsonPath('data.location_monitoring.active', false)
            ->assertJsonPath('data.location_monitoring.next_check_at', null)
            ->assertJsonPath('data.location_monitoring.due', false)
            ->assertJsonPath('data.location_monitoring.last_check.kind', 'check_out')
            // The 08:55 slot passed without a location: pending, counted as missed.
            ->assertJsonPath('data.location_monitoring.missed_count', 1);

        $record = DB::table('hr_attendance_records')->where('employee_id', $employee)->first();
        $this->assertNull($record->next_location_check_at);
        $exception = DB::table('hr_location_exceptions')->where('employee_id', $employee)->sole();
        $this->assertSame(['closed', '2026-03-01 09:00:00'], [$exception->status, (string) $exception->ended_at]);

        // Nothing is requested or accepted after check-out, and no slot is created later.
        $this->at('2026-03-01 09:30');
        $this->check($user, $this->near(10))->assertStatus(409)->assertJsonPath('reason', 'location_check_no_session');
        $this->artisan('hr:record-missed-location-checks')->expectsOutput('Pending checks stored: 0');
        $this->assertSame(
            ['check_in', 'periodic', 'periodic', 'check_out'],
            DB::table('hr_location_checks')->where('employee_id', $employee)->orderByRaw('COALESCE(checked_at, due_at)')->pluck('kind')->all(),
        );
    }

    public function test_checks_are_only_scheduled_during_working_hours(): void
    {
        [$user, $employee] = $this->electronicEmployee();
        $this->at('2026-03-01 07:55');
        $this->punch($user, 'check-in', $this->near(20))->assertOk();

        $this->at('2026-03-01 15:40');
        // 15:40 + 30 is after the 16:00 shift end: no further check is scheduled.
        $this->check($user, $this->near(10))->assertOk()->assertJsonPath('data.location_monitoring.next_check_at', null);
        $this->at('2026-03-01 17:00');
        $this->actingAsUser($user)->getJson('/api/hr/my-attendance')->assertOk()
            ->assertJsonPath('data.record.status', 'checked_in')
            ->assertJsonPath('data.location_monitoring.active', false);
        $this->check($user, $this->near(10))->assertStatus(409)->assertJsonPath('reason', 'location_monitoring_ended');
        $this->assertSame(0, DB::table('hr_location_checks')->where('employee_id', $employee)->where('due_at', '>=', '2026-03-01 16:00:00')->count());
    }

    public function test_sessions_without_gps_verification_or_with_monitoring_disabled_are_not_tracked(): void
    {
        $policy = $this->createPolicy(['gps_requirement' => 'none']);
        [$noGps, $noGpsEmployee] = $this->electronicEmployee(['gps_policy' => 'policy', 'attendance_policy_id' => $policy]);
        $this->at('2026-03-01 07:55');
        $this->punch($noGps, 'check-in', null)->assertOk()
            ->assertJsonPath('data.location_monitoring.monitored', false)
            ->assertJsonPath('data.location_monitoring.excluded_reason', 'gps_not_required');
        $this->at('2026-03-01 08:30');
        $this->check($noGps, $this->near(10))->assertStatus(409)->assertJsonPath('reason', 'location_monitoring_off');

        $this->updateSettings(['location_monitoring_enabled' => false]);
        [$disabled] = $this->electronicEmployee([], 'disabled@example.test');
        $this->punch($disabled, 'check-in', $this->near(10))->assertOk()
            ->assertJsonPath('data.location_monitoring.enabled', false)
            ->assertJsonPath('data.location_monitoring.excluded_reason', 'disabled');

        $this->assertSame(0, DB::table('hr_location_checks')->count());
        $this->assertNull(DB::table('hr_attendance_records')->where('employee_id', $noGpsEmployee)->value('location_check_interval_minutes'));
    }

    public function test_the_missing_check_out_action_also_stops_monitoring(): void
    {
        [$user, $employee] = $this->electronicEmployee();
        $this->at('2026-03-01 07:55');
        $this->punch($user, 'check-in', $this->near(20))->assertOk();
        $this->at('2026-03-01 15:35');
        $this->check($user, $this->near(900))->assertOk();

        // The check-out window closes at 20:00 (shift end + 240 minutes).
        $this->at('2026-03-01 20:30');
        $this->artisan('hr:close-missed-checkouts')->assertSuccessful();

        $record = DB::table('hr_attendance_records')->where('employee_id', $employee)->first();
        $this->assertSame('missing_check_out', $record->status);
        $this->assertNull($record->next_location_check_at);
        $exception = DB::table('hr_location_exceptions')->where('employee_id', $employee)->sole();
        $this->assertSame(['closed', '2026-03-01 16:00:00'], [$exception->status, (string) $exception->ended_at]);
        // Slots between 07:55 and 15:35 were missed; none after the shift end.
        $this->assertSame(0, DB::table('hr_location_checks')->where('employee_id', $employee)->where('due_at', '>=', '2026-03-01 16:00:00')->count());
    }

    public function test_dashboard_lists_sessions_with_their_status_and_filters_them(): void
    {
        $otherBranch = $this->createBranch(['code' => 'JED', 'name_ar' => 'جدة', 'name_en' => 'Jeddah']);
        [$inside, $insideEmployee] = $this->electronicEmployee();
        [$outside, $outsideEmployee] = $this->electronicEmployee([], 'out@example.test');
        $otherSite = $this->actingAsUser($this->hrManager)->postJson('/api/hr/work-locations', [
            'branch_id' => $otherBranch, 'name_ar' => 'فرع جدة',
            'latitude' => self::SITE[0], 'longitude' => self::SITE[1], 'radius_meters' => 100,
        ])->assertOk()->json('data.id');
        [$silent, $silentEmployee] = $this->electronicEmployee(['work_location_id' => $otherSite], 'silent@example.test', ['branch_id' => $otherBranch]);
        $this->at('2026-03-01 07:55');
        foreach ([$inside, $outside, $silent] as $user) {
            $this->punch($user, 'check-in', $this->near(20))->assertOk();
        }
        $this->at('2026-03-01 08:25');
        $this->check($inside, $this->near(30))->assertOk();
        $this->check($outside, $this->near(1200))->assertOk();

        $this->at('2026-03-01 08:40');
        $this->actingAsUser($this->hrManager);
        $response = $this->getJson('/api/hr/location-monitoring/sessions?date=2026-03-01')->assertOk()
            ->assertJsonPath('data.summary.open_sessions', 3)
            ->assertJsonPath('data.summary.by_status.within_radius', 1)
            ->assertJsonPath('data.summary.by_status.outside_radius', 1)
            ->assertJsonPath('data.summary.by_status.pending', 1)
            ->assertJsonPath('data.summary.pending_alerts', 1);
        $sessions = collect($response->json('data.sessions'))->keyBy('employee.id');
        $this->assertSame('pending', $sessions[$silentEmployee]['location_status']);
        $this->assertTrue($sessions[$silentEmployee]['overdue']);
        $this->assertSame('outside_radius', $sessions[$outsideEmployee]['location_status']);
        $this->assertSame(1, $sessions[$outsideEmployee]['open_exceptions_count']);
        $this->assertEqualsWithDelta(1200, $sessions[$outsideEmployee]['last_check']['distance_meters'], 2);
        $this->assertSame(self::SITE[0], $sessions[$insideEmployee]['work_location']['latitude']);
        $this->assertSame('08:25', $sessions[$insideEmployee]['last_check']['time']);

        $this->getJson('/api/hr/location-monitoring/sessions?date=2026-03-01&status=outside_radius')->assertOk()
            ->assertJsonCount(1, 'data.sessions')
            ->assertJsonPath('data.sessions.0.employee.id', $outsideEmployee);
        $this->getJson("/api/hr/location-monitoring/sessions?date=2026-03-01&branch_id={$otherBranch}")->assertOk()
            ->assertJsonCount(1, 'data.sessions')
            ->assertJsonPath('data.sessions.0.employee.id', $silentEmployee);
        $this->getJson("/api/hr/location-monitoring/sessions?date=2026-03-01&employee_id={$insideEmployee}")->assertOk()->assertJsonCount(1, 'data.sessions');
        $this->getJson('/api/hr/location-monitoring/sessions?date=2026-02-28')->assertOk()->assertJsonCount(0, 'data.sessions');
        $this->getJson('/api/hr/location-monitoring/employees')->assertOk()->assertJsonCount(3, 'data');

        // History with filters.
        $this->getJson("/api/hr/location-monitoring/checks?employee_id={$outsideEmployee}&status=outside_radius&from=2026-03-01&to=2026-03-01")->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.kind', 'periodic')
            ->assertJsonPath('data.0.employee.id', $outsideEmployee);
        $this->getJson('/api/hr/location-monitoring/checks?status=pending')->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.missed', true)
            ->assertJsonPath('data.0.latitude', null);
        $this->getJson('/api/hr/location-monitoring/checks?date=2026-03-02&from=2026-03-02')->assertOk()->assertJsonPath('total', 0);

        // Alerts and their review.
        $alert = $this->getJson('/api/hr/location-monitoring/exceptions?review_status=pending')->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.employee.id', $outsideEmployee)
            ->assertJsonPath('data.0.status', 'open')
            ->json('data.0.id');
        $this->postJson("/api/hr/location-monitoring/exceptions/{$alert}/review", ['review_status' => 'dismissed'])
            ->assertStatus(422)->assertJsonValidationErrors('notes');
        $this->postJson("/api/hr/location-monitoring/exceptions/{$alert}/review", ['review_status' => 'acknowledged', 'notes' => 'Client visit'])
            ->assertOk()
            ->assertJsonPath('data.review_status', 'acknowledged')
            ->assertJsonPath('data.reviewed_by.id', $this->hrManager->id);
        $this->assertSame(1, DB::table('hr_audit_logs')->where('auditable_type', 'hr_location_exception')->where('event', 'updated')->count());
    }

    public function test_dashboard_requires_the_monitoring_permission_and_review_requires_edit(): void
    {
        [$user] = $this->electronicEmployee();
        $this->actingAsUser($user)->getJson('/api/hr/location-monitoring/sessions')->assertStatus(403);
        $this->actingAsUser($user)->getJson('/api/hr/location-monitoring/checks')->assertStatus(403);

        $viewer = $this->makeUser('viewer@example.test', 'supervisor', self::CENTER, ['access_hr-location-monitoring', 'access_hr-all-branches']);
        $this->actingAsUser($viewer)->getJson('/api/hr/location-monitoring/sessions')->assertOk();
        $this->actingAsUser($viewer)->postJson('/api/hr/location-monitoring/exceptions/1/review', ['review_status' => 'acknowledged'])->assertStatus(403);
        $this->actingAsUser($viewer)->postJson('/api/hr/location-monitoring/devices/1/approve')->assertStatus(403);

        // Without access to every branch, a viewer who manages no branch sees no session.
        $scoped = $this->makeUser('scoped@example.test', 'supervisor', self::CENTER, ['access_hr-location-monitoring']);
        $this->at('2026-03-01 07:55');
        $this->punch($user, 'check-in', $this->near(20))->assertOk();
        $this->actingAsUser($scoped)->getJson('/api/hr/location-monitoring/sessions')->assertOk()->assertJsonCount(0, 'data.sessions');
        $this->actingAsUser($viewer)->getJson('/api/hr/location-monitoring/sessions')->assertOk()->assertJsonCount(1, 'data.sessions');
    }

    private function check(User $user, ?array $fix, array $extra = []): TestResponse
    {
        return $this->actingAsUser($user)->postJson('/api/hr/my-attendance/location-check', $this->fixPayload($fix) + $extra);
    }
}
