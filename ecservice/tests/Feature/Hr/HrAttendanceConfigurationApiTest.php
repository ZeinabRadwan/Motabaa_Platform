<?php

namespace Tests\Feature\Hr;

use App\Support\Hr\HrSettings;
use App\Support\PermissionCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Attendance method configuration and attendance policies: effective-dated policy rules, effective-dated
 * employee configuration, GPS rules per method, logging and permissions.
 */
class HrAttendanceConfigurationApiTest extends HrTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-03-01 10:00', 'Asia/Riyadh'));
        $this->actingAsUser($this->hrManager);
    }

    public function test_new_policy_takes_center_defaults_for_rules_not_given(): void
    {
        HrSettings::update(self::CENTER, ['attendance' => ['late_grace_minutes' => 12, 'count_after_grace' => true]]);

        $policy = $this->postJson('/api/hr/attendance-policies', [
            'code' => 'STD', 'name_ar' => 'قياسية', 'check_in_before_minutes' => 30, 'missing_checkout_action' => 'auto_checkout_at_shift_end',
        ])->assertOk()
            ->assertJsonPath('data.check_in_before_minutes', 30)
            ->assertJsonPath('data.late_grace_minutes', 12)
            ->assertJsonPath('data.late_counting', 'excess_only')
            ->assertJsonPath('data.missing_checkout_action', 'auto_checkout_at_shift_end')
            ->assertJsonPath('data.gps_requirement', 'required')
            ->assertJsonPath('data.employees_count', 0)
            ->assertJsonPath('data.has_history', false)
            ->json('data');

        $this->getJson("/api/hr/attendance-policies/{$policy['id']}/versions")
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.effective_from', '2026-03-01')
            ->assertJsonPath('data.0.is_current', true);

        $this->postJson('/api/hr/attendance-policies', ['code' => 'BAD', 'name_ar' => 'x', 'late_grace_minutes' => 30, 'check_in_after_minutes' => 10])
            ->assertStatus(422)->assertJsonPath('reason', 'check_in_window_shorter_than_grace');
        $this->postJson('/api/hr/attendance-policies', ['code' => 'BAD', 'name_ar' => 'x', 'duplicate_punch_action' => 'ignore'])
            ->assertStatus(422)->assertJsonValidationErrors('duplicate_punch_action');
    }

    public function test_unused_policy_rules_are_corrected_in_place(): void
    {
        $policy = $this->createPolicy(['late_grace_minutes' => 10]);

        $this->putJson("/api/hr/attendance-policies/{$policy}", ['late_grace_minutes' => 20])
            ->assertOk()->assertJsonPath('data.late_grace_minutes', 20);
        $this->getJson("/api/hr/attendance-policies/{$policy}/versions")
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.late_grace_minutes', 20);
    }

    public function test_policy_changes_are_effective_dated_and_history_keeps_the_rules_of_the_time(): void
    {
        $policy = $this->createPolicy(['late_grace_minutes' => 10, 'late_action' => 'record_only']);
        $employee = $this->createEmployee($this->makeUser('emp@example.test', 'teacher'));
        $shift = $this->postJson('/api/hr/shifts', [
            'code' => 'AM', 'name_ar' => 'صباحي', 'start_time' => '08:00', 'end_time' => '16:00', 'working_days' => [0, 1, 2, 3, 4],
        ])->assertOk()->json('data.id');
        $this->putJson("/api/hr/employees/{$employee}/attendance", [
            'attendance_mode' => 'manual', 'attendance_policy_id' => $policy, 'shift_id' => $shift, 'effective_from' => '2026-02-01',
        ])->assertOk()->assertJsonPath('data.attendance.attendance_policy_id', $policy);

        $url = "/api/hr/attendance-policies/{$policy}";
        $change = ['late_grace_minutes' => 25, 'late_action' => 'require_justification'];
        $this->putJson($url, $change)->assertStatus(422)->assertJsonPath('reason', 'policy_change_effective_date_required');
        $this->putJson($url, $change + ['effective_from' => '2026-02-15'])->assertStatus(422)->assertJsonPath('reason', 'policy_change_in_past');

        $this->putJson($url, $change + ['effective_from' => '2026-03-10', 'reason' => 'New handbook', 'name_ar' => 'قياسية معدلة'])
            ->assertOk()
            ->assertJsonPath('message', __('validation.hr.policy_change_scheduled', ['date' => '2026-03-10']))
            ->assertJsonPath('data.name_ar', 'قياسية معدلة')
            ->assertJsonPath('data.late_grace_minutes', 10)
            ->assertJsonPath('data.scheduled_version.late_grace_minutes', 25)
            ->assertJsonPath('data.employees_count', 1)
            ->assertJsonPath('data.has_history', true);

        $versions = $this->getJson("{$url}/versions")->assertJsonCount(2, 'data')->json('data');
        $this->assertSame('2026-03-10', $versions[0]['effective_from']);
        $this->assertTrue($versions[0]['is_scheduled']);
        $this->assertSame('2026-03-09', $versions[1]['effective_to']);

        $before = $this->getJson("/api/hr/employees/{$employee}/attendance-rules?date=2026-03-09")->assertOk()->json('data');
        $after = $this->getJson("/api/hr/employees/{$employee}/attendance-rules?date=2026-03-10")->assertOk()->json('data');
        $this->assertSame(10, $before['late_grace_minutes']);
        $this->assertSame('record_only', $before['late_action']);
        $this->assertSame($versions[1]['id'], $before['attendance_policy_version_id']);
        $this->assertSame(25, $after['late_grace_minutes']);
        $this->assertSame('require_justification', $after['late_action']);
        $this->assertSame($versions[0]['id'], $after['attendance_policy_version_id']);

        $days = array_column($this->getJson("/api/hr/employees/{$employee}/schedule?from=2026-03-09&to=2026-03-10")->json('data.days'), null, 'date');
        $this->assertSame(10, $days['2026-03-09']['late_grace_minutes']);
        $this->assertSame(25, $days['2026-03-10']['late_grace_minutes']);
        $this->assertSame($versions[1]['id'], $days['2026-03-09']['attendance_policy_version_id']);

        $this->travelTo(Carbon::parse('2026-03-10 00:10', 'Asia/Riyadh'));
        $this->artisan('hr:apply-attendance-changes')->assertSuccessful();
        $this->getJson($url)->assertJsonPath('data.late_grace_minutes', 25)->assertJsonPath('data.current_version_id', $versions[0]['id']);
        $this->getJson("/api/hr/employees/{$employee}/attendance-rules?date=2026-03-01")->assertJsonPath('data.late_grace_minutes', 10);
    }

    public function test_scheduled_policy_change_can_be_cancelled(): void
    {
        $policy = $this->createPolicy();
        $employee = $this->createEmployee($this->makeUser('emp@example.test', 'teacher'));
        $this->putJson("/api/hr/employees/{$employee}/attendance", ['attendance_mode' => 'manual', 'attendance_policy_id' => $policy])->assertOk();

        $scheduled = $this->putJson("/api/hr/attendance-policies/{$policy}", ['duplicate_punch_action' => 'reject', 'effective_from' => '2026-04-01'])
            ->assertOk()->json('data.scheduled_version.id');
        $this->postJson("/api/hr/attendance-policies/{$policy}/versions/{$scheduled}/cancel")->assertOk();
        $this->getJson("/api/hr/attendance-policies/{$policy}/versions")
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.effective_to', null);
    }

    public function test_manual_employees_never_need_gps_and_electronic_employees_follow_gps_rules(): void
    {
        $branch = $this->defaultBranchId();
        $bare = $this->postJson('/api/hr/work-locations', ['branch_id' => $branch, 'name_ar' => 'بدون إحداثيات'])->assertOk()->json('data.id');
        $site = $this->postJson('/api/hr/work-locations', ['branch_id' => $branch, 'name_ar' => 'المقر', 'latitude' => 24.7, 'longitude' => 46.7])->assertOk()->json('data.id');
        $gpsPolicy = $this->createPolicy(['code' => 'GPS', 'gps_requirement' => 'required']);
        $employee = $this->createEmployee($this->makeUser('emp@example.test', 'teacher'), ['work_location_id' => $bare]);
        $url = "/api/hr/employees/{$employee}/attendance";

        $this->putJson($url, ['attendance_mode' => 'manual', 'gps_policy' => 'required', 'attendance_policy_id' => $gpsPolicy])
            ->assertOk()
            ->assertJsonPath('data.attendance.gps_policy', 'none')
            ->assertJsonPath('data.attendance.gps_requirement', 'none')
            ->assertJsonPath('data.attendance.warnings', []);

        $this->putJson($url, ['attendance_mode' => 'electronic', 'attendance_policy_id' => $gpsPolicy])
            ->assertStatus(422)->assertJsonPath('reason', 'gps_requires_location');
        $this->putJson($url, ['attendance_mode' => 'electronic', 'gps_policy' => 'flexible', 'attendance_policy_id' => $gpsPolicy])
            ->assertOk()
            ->assertJsonPath('data.attendance.gps_requirement', 'flexible')
            ->assertJsonPath('data.attendance.warnings.0.key', 'no_shift')
            ->assertJsonPath('data.attendance.warnings.1.key', 'no_location_coordinates');
        $this->putJson($url, ['attendance_mode' => 'electronic', 'gps_policy' => 'policy', 'attendance_policy_id' => $gpsPolicy, 'work_location_id' => $site])
            ->assertOk()
            ->assertJsonPath('data.attendance.gps_policy', 'policy')
            ->assertJsonPath('data.attendance.gps_requirement', 'required')
            ->assertJsonPath('data.attendance.work_location.id', $site);

        // Moving an electronic employee who needs GPS to a location without coordinates is refused.
        $this->postJson("/api/hr/employees/{$employee}/work-assignments", [
            'branch_id' => $branch, 'work_location_id' => $bare, 'effective_from' => '2026-03-05', 'reason' => 'Move',
        ])->assertStatus(422)->assertJsonPath('reason', 'gps_requires_location');

        $noManual = $this->createPolicy(['code' => 'ELEC', 'allow_manual_attendance' => false]);
        $this->putJson($url, ['attendance_mode' => 'manual', 'attendance_policy_id' => $noManual])
            ->assertStatus(422)->assertJsonPath('reason', 'manual_not_allowed_by_policy');

        $this->putJson($url, ['attendance_mode' => 'manual', 'attendance_policy_id' => $gpsPolicy])->assertOk();
        $this->putJson("/api/hr/attendance-policies/{$gpsPolicy}", ['allow_manual_attendance' => false, 'effective_from' => '2026-04-01'])
            ->assertStatus(422)->assertJsonPath('reason', 'policy_used_by_manual_employees');
    }

    public function test_method_changes_are_effective_dated_logged_and_keep_history(): void
    {
        $employee = $this->createEmployee($this->makeUser('emp@example.test', 'teacher'));
        $url = "/api/hr/employees/{$employee}/attendance";

        $this->putJson($url, ['attendance_mode' => 'electronic', 'gps_policy' => 'none', 'effective_from' => '2026-03-15', 'reason' => 'Fingerprint rollout'])
            ->assertOk()
            ->assertJsonPath('message', __('validation.hr.attendance_config_scheduled', ['date' => '2026-03-15']))
            ->assertJsonPath('data.attendance.mode', 'manual')
            ->assertJsonPath('data.attendance.next_config.attendance_mode', 'electronic')
            ->assertJsonPath('data.attendance.next_config.effective_from', '2026-03-15');

        $rows = $this->getJson("/api/hr/employees/{$employee}/attendance-configs")->assertOk()->assertJsonCount(2, 'data')->json('data');
        $this->assertSame('2026-03-14', $rows[1]['effective_to'], 'the previous configuration is kept and closed');
        $this->assertSame('manual', $rows[1]['attendance_mode']);
        $this->assertSame('2025-01-01', $rows[1]['effective_from']);

        $this->getJson("/api/hr/employees/{$employee}/attendance-rules?date=2026-03-14")->assertJsonPath('data.attendance_mode', 'manual');
        $this->getJson("/api/hr/employees/{$employee}/attendance-rules?date=2026-03-15")->assertJsonPath('data.attendance_mode', 'electronic');

        $history = collect($this->getJson("/api/hr/employees/{$employee}/history")->json('data'));
        $method = $history->firstWhere('change_type', 'attendance_method');
        $this->assertNotNull($method);
        $this->assertSame('2026-03-15', substr((string) $method['effective_date'], 0, 10));
        $this->assertSame('Fingerprint rollout', $method['reason']);
        $this->assertTrue(DB::table('hr_audit_logs')->where('auditable_type', 'hr_employee_attendance_config')->exists());

        $this->putJson($url, ['attendance_mode' => 'electronic', 'gps_policy' => 'none', 'effective_from' => '2026-03-15'])
            ->assertStatus(422)->assertJsonPath('reason', 'attendance_config_unchanged');
        $this->putJson($url, ['attendance_mode' => 'exempt', 'effective_from' => '2026-03-10'])
            ->assertStatus(422)->assertJsonPath('reason', 'effective_date_not_after_latest');

        $this->postJson("/api/hr/employees/{$employee}/attendance-configs/{$rows[0]['id']}/cancel")->assertOk();
        $this->getJson("/api/hr/employees/{$employee}/attendance-configs")
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.effective_to', null);
        $this->assertTrue(collect($this->getJson("/api/hr/employees/{$employee}/history")->json('data'))
            ->contains(fn ($row) => $row['change_type'] === 'attendance_method' && $row['action'] === 'cancelled'));

        HrSettings::update(self::CENTER, ['payroll' => ['changes_locked_until' => '2026-02-28']]);
        $this->putJson($url, ['attendance_mode' => 'electronic', 'gps_policy' => 'none', 'effective_from' => '2026-02-20'])
            ->assertStatus(422)->assertJsonPath('reason', 'effective_date_locked');

        $this->putJson($url, ['attendance_mode' => 'electronic', 'gps_policy' => 'none', 'effective_from' => '2026-03-03'])->assertOk();
        $this->travelTo(Carbon::parse('2026-03-03 00:10', 'Asia/Riyadh'));
        $this->artisan('hr:apply-attendance-changes')->assertSuccessful();
        $this->assertSame('electronic', DB::table('hr_employees')->where('id', $employee)->value('attendance_mode'));
    }

    public function test_one_change_can_move_shift_and_work_location_from_the_same_date(): void
    {
        $branch = $this->defaultBranchId();
        $site = $this->postJson('/api/hr/work-locations', ['branch_id' => $branch, 'name_ar' => 'المقر', 'latitude' => 24.7, 'longitude' => 46.7])->assertOk()->json('data.id');
        $shift = $this->postJson('/api/hr/shifts', [
            'code' => 'AM', 'name_ar' => 'صباحي', 'start_time' => '08:00', 'end_time' => '16:00', 'working_days' => [0, 1, 2, 3, 4],
        ])->assertOk()->json('data.id');
        $employee = $this->createEmployee($this->makeUser('emp@example.test', 'teacher'));

        $this->putJson("/api/hr/employees/{$employee}/attendance", [
            'attendance_mode' => 'electronic', 'gps_policy' => 'required', 'shift_id' => $shift, 'work_location_id' => $site,
            'grace_minutes' => 7, 'effective_from' => '2026-03-08',
        ])->assertOk();

        $this->assertSame('2026-03-08', substr((string) DB::table('hr_employee_shift_assignments')->where('employee_id', $employee)->value('effective_from'), 0, 10));
        $this->assertTrue(DB::table('hr_employee_work_assignments')->where('employee_id', $employee)
            ->where('work_location_id', $site)->where('effective_from', 'like', '2026-03-08%')->exists());

        $days = array_column($this->getJson("/api/hr/employees/{$employee}/schedule?from=2026-03-08&to=2026-03-08")->json('data.days'), null, 'date');
        $this->assertSame('working', $days['2026-03-08']['status']);
        $this->assertSame('electronic', $days['2026-03-08']['attendance_mode']);
        $this->assertSame('required', $days['2026-03-08']['gps_requirement']);
        $this->assertSame(7, $days['2026-03-08']['late_grace_minutes']);

        // Only the shift changes: the attendance configuration row stays as it is.
        $evening = $this->postJson('/api/hr/shifts', [
            'code' => 'PM', 'name_ar' => 'مسائي', 'start_time' => '14:00', 'end_time' => '22:00', 'working_days' => [0, 1, 2, 3, 4],
        ])->assertOk()->json('data.id');
        $this->putJson("/api/hr/employees/{$employee}/attendance", [
            'attendance_mode' => 'electronic', 'gps_policy' => 'required', 'grace_minutes' => 7, 'shift_id' => $evening, 'effective_from' => '2026-03-20',
        ])->assertOk();
        $this->assertSame(2, DB::table('hr_employee_attendance_configs')->where('employee_id', $employee)->count());
        $this->assertSame(2, DB::table('hr_employee_shift_assignments')->where('employee_id', $employee)->count());
    }

    public function test_unauthorized_users_cannot_change_attendance_configuration(): void
    {
        $policy = $this->createPolicy();
        $employee = $this->createEmployee($this->makeUser('emp@example.test', 'teacher'));
        $clerk = $this->makeUser('clerk@example.test', 'clerk', self::CENTER, [
            'access_hr-employees', 'edit_hr-employees', 'access_hr-all-branches', 'access_hr-attendance-setup',
        ]);
        $this->actingAsUser($clerk);

        $this->getJson("/api/hr/employees/{$employee}")->assertOk()->assertJsonPath('data.attendance.can_configure', false);
        $this->getJson("/api/hr/employees/{$employee}/attendance-configs")->assertOk();
        $this->putJson("/api/hr/employees/{$employee}/attendance", ['attendance_mode' => 'electronic'])->assertStatus(403);
        $configId = DB::table('hr_employee_attendance_configs')->where('employee_id', $employee)->value('id');
        $this->postJson("/api/hr/employees/{$employee}/attendance-configs/{$configId}/cancel")->assertStatus(403);
        $this->putJson("/api/hr/attendance-policies/{$policy}", ['late_grace_minutes' => 30])->assertStatus(403);

        $this->postJson('/api/hr/employees', ['attendance_mode' => 'electronic'] + $this->employeePayload($this->makeUser('new1@example.test', 'teacher')))
            ->assertStatus(403)->assertJsonPath('reason', 'attendance_config_forbidden');
        $this->postJson('/api/hr/employees', ['attendance_policy_id' => $policy] + $this->employeePayload($this->makeUser('new2@example.test', 'teacher')))
            ->assertStatus(403);
        $this->postJson('/api/hr/employees', $this->employeePayload($this->makeUser('new3@example.test', 'teacher')))
            ->assertOk()->assertJsonPath('data.attendance.mode', 'manual');

        $configOnly = $this->makeUser('config@example.test', 'config', self::CENTER, ['edit_hr-attendance-config']);
        $this->actingAsUser($configOnly);
        $this->putJson("/api/hr/employees/{$employee}/attendance", ['attendance_mode' => 'electronic'])->assertStatus(403);
    }

    public function test_policy_in_use_cannot_be_deactivated_and_lists_its_employees(): void
    {
        $policy = $this->createPolicy();
        $other = $this->createPolicy(['code' => 'ALT']);
        $employee = $this->createEmployee($this->makeUser('emp@example.test', 'teacher'));
        $this->putJson("/api/hr/employees/{$employee}/attendance", ['attendance_mode' => 'manual', 'attendance_policy_id' => $policy])->assertOk();

        $this->patchJson("/api/hr/attendance-policies/{$policy}/deactivate")->assertStatus(422)->assertJsonPath('reason', 'attendance_policy_in_use');
        $number = $this->getJson("/api/hr/attendance-policies/{$policy}/employees")
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.employee_id', $employee)
            ->json('data.0.employee_number');
        $this->getJson("/api/hr/attendance-policies/{$policy}/employees?q={$number}")->assertJsonPath('total', 1);
        $this->getJson("/api/hr/attendance-policies/{$policy}/employees?q=no-such-employee")->assertJsonPath('total', 0);

        // Moving the employee to another policy from a later date keeps the policy in use until then.
        $this->putJson("/api/hr/employees/{$employee}/attendance", [
            'attendance_mode' => 'manual', 'attendance_policy_id' => $other, 'effective_from' => '2026-03-05',
        ])->assertOk();
        $this->patchJson("/api/hr/attendance-policies/{$policy}/deactivate")->assertStatus(422);
        $this->getJson("/api/hr/attendance-policies/{$other}/employees")->assertJsonPath('data.0.is_scheduled', true);

        $this->travelTo(Carbon::parse('2026-03-05 09:00', 'Asia/Riyadh'));
        $this->patchJson("/api/hr/attendance-policies/{$policy}/deactivate")->assertOk()->assertJsonPath('data.is_active', false);
        $this->deleteJson("/api/hr/attendance-policies/{$policy}")->assertOk();

        // The deleted policy still answers for the dates it applied to.
        $this->getJson("/api/hr/employees/{$employee}/attendance-rules?date=2026-03-02")
            ->assertJsonPath('data.attendance_policy_id', $policy);
    }

    public function test_attendance_config_permission_is_granted_to_roles_that_edit_employees(): void
    {
        DB::table('permissions')->where('name', PermissionCatalog::HR_ATTENDANCE_CONFIG_PERMISSION)->delete();
        DB::table('permissions')->insert(['name' => PermissionCatalog::HR_ATTENDANCE_CONFIG_PERMISSION, 'guard_name' => 'web']);
        $editor = Role::query()->create(['name' => 'Editor', 'default_name' => 'hr_editor', 'guard_name' => 'web']);
        $editor->givePermissionTo('edit_hr-employees');
        $viewer = Role::query()->create(['name' => 'Viewer', 'default_name' => 'hr_viewer', 'guard_name' => 'web']);
        $viewer->givePermissionTo('access_hr-employees');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $stats = PermissionCatalog::migrateHrAttendanceConfigPermission();

        $this->assertGreaterThanOrEqual(1, $stats['roles_granted']);
        $this->assertTrue($editor->fresh()->hasPermissionTo(PermissionCatalog::HR_ATTENDANCE_CONFIG_PERMISSION));
        $this->assertFalse($viewer->fresh()->hasPermissionTo(PermissionCatalog::HR_ATTENDANCE_CONFIG_PERMISSION));
    }

    public function test_migration_backfills_history_from_existing_configuration(): void
    {
        $migration = 'database/migrations/2026_11_05_160000_create_hr_attendance_configuration_tables.php';
        $this->artisan('migrate:rollback', ['--path' => [$migration]])->assertSuccessful();

        $policyId = DB::table('hr_attendance_policies')->insertGetId([
            'center_id' => self::CENTER, 'code' => 'OLD', 'name_ar' => 'قديمة', 'late_grace_minutes' => 9,
            'early_leave_grace_minutes' => 4, 'allow_manual_attendance' => true, 'is_active' => true,
            'created_at' => '2026-02-10 08:00:00', 'updated_at' => '2026-02-10 08:00:00',
        ]);
        $userId = $this->makeUser('legacy@example.test', 'teacher')->id;
        $employeeId = DB::table('hr_employees')->insertGetId([
            'center_id' => self::CENTER, 'user_id' => $userId, 'employee_number' => 'L1', 'employment_status' => 'active',
            'employment_start_date' => '2025-06-01', 'attendance_mode' => 'electronic', 'gps_policy' => 'flexible',
            'grace_minutes' => 3, 'attendance_policy_id' => $policyId,
        ]);

        $this->artisan('migrate', ['--path' => [$migration]])->assertSuccessful();

        $version = DB::table('hr_attendance_policy_versions')->where('attendance_policy_id', $policyId)->first();
        $this->assertSame('2025-06-01', substr((string) $version->effective_from, 0, 10), 'starts with the first employee using it');
        $this->assertSame(9, (int) $version->late_grace_minutes);
        $this->assertSame((int) $version->id, (int) DB::table('hr_attendance_policies')->where('id', $policyId)->value('current_version_id'));

        $config = DB::table('hr_employee_attendance_configs')->where('employee_id', $employeeId)->first();
        $this->assertSame('2025-06-01', substr((string) $config->effective_from, 0, 10));
        $this->assertSame('electronic', $config->attendance_mode);
        $this->assertSame('flexible', $config->gps_policy);
        $this->assertSame(3, (int) $config->grace_minutes);
        $this->assertSame($policyId, (int) $config->attendance_policy_id);
    }

    private function createPolicy(array $overrides = []): int
    {
        return (int) $this->actingAsUser($this->hrManager)
            ->postJson('/api/hr/attendance-policies', $overrides + ['code' => 'STD', 'name_ar' => 'قياسية'])
            ->assertOk()
            ->json('data.id');
    }
}
