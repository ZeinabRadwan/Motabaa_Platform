<?php

namespace Tests\Feature\Hr;

use App\Models\Hr\AuditLog;
use App\Support\Hr\HrStaffSync;

class HrEmployeeAndSettingsApiTest extends HrTestCase
{
    public function test_staff_get_profiles_with_generated_numbers_and_parents_are_refused(): void
    {
        $teacher = $this->makeUser('teacher@example.test', 'teacher', self::CENTER, [], ['nationality' => 'SA', 'job_title' => 'معلمة']);
        $parent = $this->makeUser('parent@example.test', 'parent');

        $candidates = $this->actingAsUser($this->hrManager)->getJson('/api/hr/employees/candidates')->assertOk()->json('data');
        $this->assertContains($teacher->id, array_column($candidates, 'id'));
        $this->assertNotContains($parent->id, array_column($candidates, 'id'));

        $this->postJson('/api/hr/employees', ['job_title' => 'معلمة'] + $this->employeePayload($teacher))
            ->assertOk()
            ->assertJsonPath('data.employee_number', 'EMP0001')
            ->assertJsonPath('data.is_saudi', true)
            ->assertJsonPath('data.id_type', 'national_id')
            ->assertJsonPath('data.job_title', 'معلمة')
            ->assertJsonPath('data.employment_status', 'active')
            ->assertJsonPath('data.employment_periods.0.start_date', '2025-01-01');

        $this->postJson('/api/hr/employees', $this->employeePayload($teacher))
            ->assertStatus(422)->assertJsonPath('reason', 'employee_exists');
        $this->postJson('/api/hr/employees', $this->employeePayload($parent))
            ->assertStatus(422)->assertJsonPath('reason', 'employee_not_staff');

        $this->assertNotContains($teacher->id, array_column($this->getJson('/api/hr/employees/candidates')->json('data'), 'id'));
    }

    public function test_manager_chain_cannot_loop_and_branch_with_employees_cannot_be_deleted(): void
    {
        $branchId = $this->createBranch();
        $otherBranchId = $this->createBranch(['code' => 'JED', 'name_ar' => 'جدة']);
        $boss = $this->createEmployee($this->makeUser('boss@example.test', 'teacher'), ['branch_id' => $branchId]);
        $report = $this->createEmployee($this->makeUser('report@example.test', 'teacher'), ['manager_id' => $boss, 'branch_id' => $otherBranchId]);

        $this->putJson("/api/hr/employees/{$boss}", ['manager_id' => $report])
            ->assertStatus(422)->assertJsonPath('reason', 'manager_cycle');

        $this->deleteJson("/api/hr/branches/{$branchId}")
            ->assertStatus(422)->assertJsonPath('reason', 'branch_has_employees');

        $this->putJson("/api/hr/employees/{$boss}", ['employment_status' => 'terminated'])
            ->assertStatus(422)->assertJsonValidationErrors('employment_status');
        $this->patchJson("/api/hr/employees/{$boss}/deactivate", [])
            ->assertStatus(422)->assertJsonValidationErrors(['end_date', 'termination_reason', 'notes']);
        $this->patchJson("/api/hr/employees/{$boss}/deactivate", [
            'end_date' => '2026-11-01', 'termination_reason' => 'resignation', 'notes' => 'Resigned',
        ])->assertOk()->assertJsonPath('data.employment_status', 'terminated');

        $this->deleteJson("/api/hr/branches/{$branchId}")->assertOk();
    }

    public function test_lookups_list_staff_roles_and_hr_permissions_and_employee_search_keeps_selected_ids(): void
    {
        $this->makeUser('parent@example.test', 'parent');
        $branchId = $this->createBranch();
        $alice = $this->createEmployee($this->makeUser('alice@example.test', 'teacher'));
        $this->createEmployee($this->makeUser('bob@example.test', 'teacher'));

        $lookups = $this->actingAsUser($this->hrManager)->getJson('/api/hr/lookups')->assertOk()->json('data');

        $this->assertSame([$branchId], array_column($lookups['branches'], 'value'));
        $roleTitles = array_column($lookups['approver_roles'], 'title');
        $this->assertContains('Teacher', $roleTitles);
        $this->assertNotContains('Parent', $roleTitles);
        $this->assertContainsOnly('string', array_column($lookups['approver_roles'], 'value'));
        $permissions = array_column($lookups['approver_permissions'], 'value');
        $this->assertContains('apply_hr-approvals', $permissions);
        $this->assertEmpty(array_filter($permissions, fn ($name) => ! str_contains($name, '_hr-')));

        $ids = array_column($this->getJson('/api/hr/employees?q=bob&ids[]='.$alice)->assertOk()->json('data'), 'name');
        sort($ids);
        $this->assertSame(['alice@example.test', 'bob@example.test'], $ids);
    }

    public function test_settings_return_defaults_validate_and_audit_changes(): void
    {
        $this->actingAsUser($this->hrManager)
            ->getJson('/api/hr/settings')
            ->assertOk()
            ->assertJsonPath('data.values.general.weekend_days', [5, 6])
            ->assertJsonPath('data.values.leave.annual_days', 21)
            ->assertJsonPath('data.schema.general.calendar_display.options', ['gregorian', 'hijri', 'both']);

        $this->putJson('/api/hr/settings', ['settings' => ['leave' => ['annual_days' => 10]]])
            ->assertStatus(422)->assertJsonValidationErrors('settings.leave.annual_days');

        $this->putJson('/api/hr/settings', ['settings' => ['leave' => ['annual_days' => 25], 'general' => ['weekend_days' => [5]]]])
            ->assertOk()
            ->assertJsonPath('data.values.leave.annual_days', 25)
            ->assertJsonPath('data.values.general.weekend_days', [5])
            ->assertJsonPath('data.values.leave.annual_days_after_five_years', 30);

        $this->assertSame(2, AuditLog::query()->where('auditable_type', 'hr_setting')->where('event', 'created')->count());

        $outsider = $this->makeUser('other@example.test', 'hr_manager', self::OTHER_CENTER, $this->hrPermissions());
        $this->actingAsUser($outsider)->getJson('/api/hr/settings')->assertJsonPath('data.values.leave.annual_days', 21);
    }

    public function test_audit_log_endpoint_only_returns_the_callers_center(): void
    {
        $this->createBranch();
        $outsider = $this->makeUser('other@example.test', 'hr_manager', self::OTHER_CENTER, $this->hrPermissions());
        $this->actingAsUser($outsider)->postJson('/api/hr/branches', ['code' => 'JED', 'name_ar' => 'جدة'])->assertOk();

        $rows = $this->actingAsUser($this->hrManager)->getJson('/api/hr/audit-logs')->assertOk()->json('data');
        $this->assertCount(1, $rows);
        $this->assertSame('hr_branch', $rows[0]['auditable_type']);
        $this->assertSame('RUH', $rows[0]['new_values']['code']);
    }

    public function test_staff_sync_creates_legacy_departments_and_missing_profiles(): void
    {
        $this->makeUser('ot@example.test', 'teacher', self::CENTER, [], ['department' => 'occupational_therapy']);
        $this->makeUser('parent@example.test', 'parent');

        $plan = HrStaffSync::plan(self::CENTER);
        $this->assertContains('occupational_therapy', $plan['departments_to_create']);
        $this->assertCount(2, $plan['employees_to_create'], 'the HR manager and the therapist, not the parent');

        HrStaffSync::apply($plan);

        $employees = $this->actingAsUser($this->hrManager)->getJson('/api/hr/employees')->json('data');
        $therapist = collect($employees)->firstWhere('email', 'ot@example.test');
        $this->assertNotNull($therapist['department_id']);

        $again = HrStaffSync::plan(self::CENTER);
        $this->assertSame([], $again['departments_to_create']);
        $this->assertSame([], $again['employees_to_create']);
    }
}
