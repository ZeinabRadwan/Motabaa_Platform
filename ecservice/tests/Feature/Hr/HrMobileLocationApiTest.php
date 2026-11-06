<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * Integration foundation for the future mobile application: authenticated endpoints, device registration and
 * approval, device credentials bound to the employee, payload and timestamp validation, duplicate protection, and
 * samples feeding the same sessions, work locations and location records as the website.
 */
class HrMobileLocationApiTest extends HrTestCase
{
    use InteractsWithElectronicAttendance;

    private const UUID = 'f3a1c2d4-5b6e-4f70-8a9b-0c1d2e3f4a5b';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpElectronicAttendance();
    }

    public function test_mobile_endpoints_require_authentication_and_the_self_attendance_permission(): void
    {
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/hr/mobile/devices', $this->deviceData())->assertStatus(401);
        $this->postJson('/api/hr/mobile/locations', [])->assertStatus(401);
        $this->getJson('/api/hr/mobile/device')->assertStatus(401);

        $noPermission = $this->makeUser('plain@example.test', 'teacher');
        $this->actingAsUser($noPermission)->postJson('/api/hr/mobile/devices', $this->deviceData())->assertStatus(403);
        $this->actingAsUser($noPermission)->postJson('/api/hr/mobile/locations', [])->assertStatus(403);

        // Permission but no employee profile.
        $notEmployee = $this->makeUser('nobody@example.test', 'teacher', self::CENTER, ['apply_hr-self-attendance']);
        $this->actingAsUser($notEmployee)->postJson('/api/hr/mobile/devices', $this->deviceData())->assertStatus(403);
        $this->assertSame(0, DB::table('hr_attendance_devices')->count());
    }

    public function test_registration_returns_the_secret_once_and_stores_only_its_hash(): void
    {
        [$user, $employee] = $this->electronicEmployee();
        $response = $this->actingAsUser($user)->postJson('/api/hr/mobile/devices', $this->deviceData())->assertOk()
            ->assertJsonPath('data.device.status', 'pending')
            ->assertJsonPath('data.device.platform', 'android')
            ->assertJsonPath('data.headers', ['X-Device-Id', 'X-Device-Token']);
        $token = $response->json('data.device_token');
        $this->assertSame(64, strlen($token));

        $row = DB::table('hr_attendance_devices')->sole();
        $this->assertSame([$employee, $user->id, self::UUID], [(int) $row->employee_id, (int) $row->user_id, $row->device_uuid]);
        $this->assertSame(hash('sha256', $token), $row->token_hash);
        $this->assertStringNotContainsString($token, json_encode(DB::table('hr_attendance_devices')->get()));

        // The status endpoint never returns the secret.
        $status = $this->device($user, $token)->assertOk()->assertJsonPath('data.device.status', 'pending');
        $this->assertStringNotContainsString($token, $status->getContent());
        $this->assertArrayNotHasKey('token_hash', $status->json('data.device'));

        // Registering the same installation again rotates the secret: the old one stops working.
        $rotated = $this->actingAsUser($user)->postJson('/api/hr/mobile/devices', $this->deviceData(['app_version' => '1.1.0']))->assertOk()->json('data.device_token');
        $this->assertNotSame($token, $rotated);
        $this->assertSame(1, DB::table('hr_attendance_devices')->count());
        $this->device($user, $token)->assertStatus(401)->assertJsonPath('reason', 'device_unauthenticated');
        $this->device($user, $rotated)->assertOk()->assertJsonPath('data.device.app_version', '1.1.0');

        $this->actingAsUser($user)->postJson('/api/hr/mobile/devices', ['device_uuid' => 'bad id', 'platform' => 'web'])
            ->assertStatus(422)->assertJsonValidationErrors(['device_uuid', 'platform']);
    }

    public function test_a_pending_device_cannot_send_locations_until_hr_approves_it(): void
    {
        [$user, $employee] = $this->electronicEmployee();
        $token = $this->register($user);
        $this->at('2026-03-01 07:55');
        $this->punch($user, 'check-in', $this->near(20))->assertOk();

        $this->at('2026-03-01 08:26');
        $this->locations($user, $token, [$this->sample('s1', '2026-03-01T08:25:30+03:00')])->assertStatus(403)->assertJsonPath('reason', 'device_pending');

        $deviceId = $this->actingAsUser($this->hrManager)->getJson('/api/hr/location-monitoring/devices?status=pending')->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.employee.id', $employee)
            ->json('data.0.id');
        $this->postJson("/api/hr/location-monitoring/devices/{$deviceId}/approve")->assertOk()->assertJsonPath('data.status', 'active');
        $this->postJson("/api/hr/location-monitoring/devices/{$deviceId}/approve")->assertStatus(422)->assertJsonPath('reason', 'device_not_pending');

        $this->locations($user, $token, [$this->sample('s1', '2026-03-01T08:25:30+03:00')])->assertOk()
            ->assertJsonPath('data.accepted', 1)
            ->assertJsonPath('data.results.0.status', 'within_radius')
            ->assertJsonPath('data.location_monitoring.next_check_time', '08:55');
        $this->assertSame(1, DB::table('hr_audit_logs')->where('auditable_type', 'hr_attendance_device')->where('event', 'updated')->count());
    }

    public function test_device_credentials_are_required_and_bound_to_the_employee(): void
    {
        [$user] = $this->electronicEmployee();
        [$other] = $this->electronicEmployee([], 'other@example.test');
        $token = $this->activeDevice($user);
        $batch = ['sent_at' => now()->toIso8601String(), 'locations' => [$this->sample('a1', now()->toIso8601String())]];

        $this->actingAsUser($user)->postJson('/api/hr/mobile/locations', $batch)->assertStatus(401)->assertJsonPath('reason', 'device_unauthenticated');
        $this->actingAsUser($user)->postJson('/api/hr/mobile/locations', $batch, $this->headers(str_repeat('x', 64)))->assertStatus(401);
        $this->actingAsUser($user)->postJson('/api/hr/mobile/locations', $batch, $this->headers($token, 'another-device-123'))->assertStatus(401);
        // Another employee's session cannot use this device's credentials.
        $this->actingAsUser($other)->postJson('/api/hr/mobile/locations', $batch, $this->headers($token))->assertStatus(401);
        // Nor register the same installation id for themselves.
        $this->actingAsUser($other)->postJson('/api/hr/mobile/devices', $this->deviceData())->assertStatus(409)->assertJsonPath('reason', 'device_registered_to_another');

        // Once HR revokes the device it is refused, and the installation cannot register again.
        $deviceId = DB::table('hr_attendance_devices')->value('id');
        $this->actingAsUser($this->hrManager)->postJson("/api/hr/location-monitoring/devices/{$deviceId}/revoke")->assertOk()
            ->assertJsonPath('data.status', 'revoked')
            ->assertJsonPath('data.revoke_reason', 'hr_revoked');
        $this->locations($user, $token, $batch['locations'])->assertStatus(403)->assertJsonPath('reason', 'device_revoked');
        $this->device($user, $token)->assertOk()->assertJsonPath('data.device.status', 'revoked');
        $this->actingAsUser($user)->postJson('/api/hr/mobile/devices', $this->deviceData())->assertStatus(403)->assertJsonPath('reason', 'device_revoked');
    }

    public function test_samples_feed_the_session_schedule_and_are_validated_and_deduplicated(): void
    {
        [$user, $employee] = $this->electronicEmployee();
        $token = $this->activeDevice($user);
        $this->at('2026-03-01 07:55');
        $this->punch($user, 'check-in', $this->near(20))->assertOk();

        // Queued while the application was in the background; sent together after a network outage.
        $this->at('2026-03-01 09:30');
        $response = $this->locations($user, $token, [
            $this->sample('bg-0925', '2026-03-01T06:25:30Z', $this->near(900)),
            $this->sample('bg-0825', '2026-03-01T08:25:40+03:00'),
            $this->sample('bg-0855', '2026-03-01T08:55:05+03:00', null, ['location_error' => 'background_restricted']),
            $this->sample('early', '2026-03-01T07:40:00+03:00'),
            $this->sample('future', '2026-03-01T09:40:00+03:00'),
            $this->sample('old', '2026-02-27T09:00:00+03:00'),
            $this->sample('mocked', '2026-03-01T09:29:00+03:00', $this->near(5), ['is_mocked' => true, 'source' => 'foreground']),
        ])->assertOk()
            ->assertJsonPath('data.accepted', 4)
            ->assertJsonPath('data.rejected', 3);
        $results = collect($response->json('data.results'))->keyBy('client_id');
        $this->assertSame('outside_session', $results['early']['reason']);
        $this->assertSame('timestamp_in_future', $results['future']['reason']);
        $this->assertSame('timestamp_too_old', $results['old']['reason']);
        $this->assertSame('outside_radius', $results['bg-0925']['status']);
        $this->assertSame('gps_unavailable', $results['bg-0855']['status']);
        $this->assertSame('verification_failed', $results['mocked']['status']);

        // Each sample answers the check due when it was taken (the next one is due 30 minutes after it); the mocked
        // sample taken between two due checks is stored as an extra check.
        $rows = DB::table('hr_location_checks')->where('employee_id', $employee)->where('kind', 'periodic')->orderBy('checked_at')->get()->keyBy('client_id');
        $this->assertSame(['bg-0825', 'bg-0855', 'bg-0925', 'mocked'], $rows->keys()->all());
        $this->assertSame('2026-03-01 08:25:00', (string) $rows['bg-0825']->due_at);
        $this->assertSame('2026-03-01 08:55:40', (string) $rows['bg-0855']->due_at);
        $this->assertSame('2026-03-01 09:25:05', (string) $rows['bg-0925']->due_at);
        $this->assertSame('2026-03-01 09:25:30', (string) $rows['bg-0925']->checked_at);
        $this->assertNull($rows['mocked']->due_at);
        $this->assertSame(0, DB::table('hr_location_checks')->where('employee_id', $employee)->where('status', 'pending')->count());
        $this->assertSame(['mobile_background', 'mobile_foreground'], [$rows['bg-0825']->source, $rows['mocked']->source]);
        $this->assertSame(['mocked_location', 1], [$rows['mocked']->reason, (int) $rows['mocked']->is_mocked]);
        $this->assertSame((int) DB::table('hr_attendance_devices')->value('id'), (int) $rows['bg-0825']->device_id);
        $this->assertSame(1, DB::table('hr_location_exceptions')->where('employee_id', $employee)->where('status', 'open')->count());
        $this->assertNotNull(DB::table('hr_attendance_devices')->value('last_location_at'));

        // The application retries the whole batch: nothing is stored twice.
        $this->locations($user, $token, [$this->sample('bg-0825', '2026-03-01T08:25:40+03:00')])->assertOk()
            ->assertJsonPath('data.duplicates', 1)
            ->assertJsonPath('data.results.0.check_id', (int) $rows['bg-0825']->id);
        $this->assertSame(4, DB::table('hr_location_checks')->where('employee_id', $employee)->where('kind', 'periodic')->count());

        // After the shift end, and after check-out, samples are not tracked.
        $this->at('2026-03-01 16:20');
        $this->locations($user, $token, [$this->sample('late', '2026-03-01T16:10:00+03:00')])->assertOk()->assertJsonPath('data.results.0.reason', 'outside_working_hours');
        $this->punch($user, 'check-out', $this->near(15))->assertOk();
        $this->at('2026-03-01 16:30');
        $this->locations($user, $token, [$this->sample('home', '2026-03-01T16:29:00+03:00')])->assertOk()->assertJsonPath('data.results.0.reason', 'outside_session');
    }

    public function test_the_payload_and_device_clock_are_validated(): void
    {
        [$user] = $this->electronicEmployee();
        $token = $this->activeDevice($user);
        $this->at('2026-03-01 08:30');
        $now = '2026-03-01T08:30:00+03:00';

        $this->locations($user, $token, [$this->sample('a', $now)], '2026-03-01T08:45:00+03:00')
            ->assertStatus(422)->assertJsonValidationErrors('sent_at');
        $this->locations($user, $token, [$this->sample('a', '2026-03-01 08:30:00')])
            ->assertStatus(422)->assertJsonValidationErrors('locations.0.recorded_at');
        $this->locations($user, $token, [$this->sample('a', $now), $this->sample('a', $now)])
            ->assertStatus(422)->assertJsonValidationErrors('locations.0.client_id');
        $this->locations($user, $token, [$this->sample('a', $now, null)])
            ->assertStatus(422)->assertJsonValidationErrors('locations.0.location_error');
        $this->locations($user, $token, [$this->sample('a', $now, [0, 0, 10])])
            ->assertStatus(422)->assertJsonValidationErrors('locations.0.latitude');
        $this->locations($user, $token, [$this->sample('a', $now, null, ['source' => 'push', 'location_error' => 'nope'])])
            ->assertStatus(422)->assertJsonValidationErrors(['locations.0.source', 'locations.0.location_error']);
        $this->locations($user, $token, array_map(fn ($i) => $this->sample("s{$i}", $now), range(1, 51)))
            ->assertStatus(422)->assertJsonValidationErrors('locations');
        $this->assertSame(0, DB::table('hr_location_checks')->count());
    }

    public function test_without_approval_a_new_device_is_active_at_once_and_replaces_the_previous_one(): void
    {
        $this->updateSettings(['mobile_device_approval_required' => false]);
        [$user] = $this->electronicEmployee();
        $first = $this->register($user);
        $this->device($user, $first)->assertJsonPath('data.device.status', 'active');

        $second = $this->register($user, 'a9b8c7d6-new-phone');
        $this->device($user, $second, 'a9b8c7d6-new-phone')->assertJsonPath('data.device.status', 'active');
        $this->device($user, $first)->assertJsonPath('data.device.status', 'revoked')->assertJsonPath('data.device.revoke_reason', 'replaced');

        // The application can sign the device out itself.
        $this->actingAsUser($user)->postJson('/api/hr/mobile/device/unregister', [], $this->headers($second, 'a9b8c7d6-new-phone'))->assertOk()
            ->assertJsonPath('data.device.revoke_reason', 'unregistered');
        $this->locations($user, $second, [$this->sample('x', now()->toIso8601String())], null, 'a9b8c7d6-new-phone')->assertStatus(403);
    }

    public function test_the_application_works_with_a_real_bearer_token(): void
    {
        $this->updateSettings(['mobile_device_approval_required' => false]);
        [$user] = $this->electronicEmployee();
        $bearer = $user->createToken('auth-token')->plainTextToken;
        $this->at('2026-03-01 07:55');
        $this->punch($user, 'check-in', $this->near(20))->assertOk();
        $this->app['auth']->forgetGuards();

        $auth = ['Authorization' => "Bearer {$bearer}"];
        $device = $this->postJson('/api/hr/mobile/devices', $this->deviceData(), $auth)->assertOk()->json('data.device_token');
        $this->at('2026-03-01 08:26');
        $this->postJson('/api/hr/mobile/locations', [
            'sent_at' => now()->toIso8601String(),
            'locations' => [$this->sample('b1', '2026-03-01T05:25:50Z')],
        ], $auth + $this->headers($device))->assertOk()->assertJsonPath('data.accepted', 1);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/hr/mobile/device', ['Authorization' => 'Bearer 999|invalid'] + $this->headers($device))->assertStatus(401);
    }

    private function deviceData(array $overrides = []): array
    {
        return $overrides + [
            'device_uuid' => self::UUID, 'platform' => 'android', 'device_name' => 'Work phone',
            'device_model' => 'Pixel 8', 'os_version' => '15', 'app_version' => '1.0.0',
        ];
    }

    private function register(User $user, string $uuid = self::UUID): string
    {
        return $this->actingAsUser($user)->postJson('/api/hr/mobile/devices', $this->deviceData(['device_uuid' => $uuid]))->assertOk()->json('data.device_token');
    }

    private function activeDevice(User $user): string
    {
        $token = $this->register($user);
        $this->actingAsUser($this->hrManager)
            ->postJson('/api/hr/location-monitoring/devices/'.DB::table('hr_attendance_devices')->where('device_uuid', self::UUID)->value('id').'/approve')
            ->assertOk();

        return $token;
    }

    private function headers(string $token, string $uuid = self::UUID): array
    {
        return ['X-Device-Id' => $uuid, 'X-Device-Token' => $token];
    }

    private function device(User $user, string $token, string $uuid = self::UUID): TestResponse
    {
        return $this->actingAsUser($user)->getJson('/api/hr/mobile/device', $this->headers($token, $uuid));
    }

    private function locations(User $user, string $token, array $samples, ?string $sentAt = null, string $uuid = self::UUID): TestResponse
    {
        return $this->actingAsUser($user)->postJson('/api/hr/mobile/locations', [
            'sent_at' => $sentAt ?? now()->toIso8601String(),
            'locations' => $samples,
        ], $this->headers($token, $uuid));
    }

    private function sample(string $clientId, string $recordedAt, ?array $fix = [], array $extra = []): array
    {
        $fix = $fix === [] ? $this->near(20) : $fix;

        return $extra + ['client_id' => $clientId, 'recorded_at' => $recordedAt, 'source' => 'background'] + $this->fixPayload($fix);
    }
}
