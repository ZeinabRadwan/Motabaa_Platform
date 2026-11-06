<?php

namespace Tests\Feature\Hr;

use App\Models\Hr\AuditLog;
use App\Models\Hr\Branch;

class HrOrganizationApiTest extends HrTestCase
{
    public function test_branch_lifecycle_is_scoped_to_the_center_and_audited(): void
    {
        $branchId = $this->createBranch(['cr_number' => '1010123456']);

        $this->actingAsUser($this->hrManager)
            ->getJson('/api/hr/branches')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.code', 'RUH');

        $this->putJson("/api/hr/branches/{$branchId}", ['name_ar' => 'الفرع الرئيسي', 'is_headquarters' => true])
            ->assertOk()
            ->assertJsonPath('data.is_headquarters', true);

        $update = AuditLog::query()->where('auditable_type', 'hr_branch')->where('event', 'updated')->firstOrFail();
        $this->assertSame(self::CENTER, (int) $update->center_id);
        $this->assertSame((int) $this->hrManager->id, (int) $update->user_id);
        $this->assertSame('فرع الرياض', $update->old_values['name_ar']);
        $this->assertSame('الفرع الرئيسي', $update->new_values['name_ar']);

        $this->deleteJson("/api/hr/branches/{$branchId}")->assertOk();
        $this->assertSoftDeleted('hr_branches', ['id' => $branchId]);
        $this->patchJson("/api/hr/branches/{$branchId}/restore")->assertOk();

        $this->assertSame(
            ['created', 'updated', 'deleted', 'restored'],
            AuditLog::query()->where('auditable_type', 'hr_branch')->orderBy('id')->pluck('event')->all()
        );
    }

    public function test_other_centers_cannot_see_or_change_the_branch(): void
    {
        $branchId = $this->createBranch();
        $outsider = $this->makeUser('other@example.test', 'hr_manager', self::OTHER_CENTER, $this->hrPermissions());

        $this->actingAsUser($outsider)->getJson('/api/hr/branches')->assertOk()->assertJsonPath('total', 0);
        $this->getJson("/api/hr/branches/{$branchId}")->assertNotFound();
        $this->putJson("/api/hr/branches/{$branchId}", ['name_ar' => 'x'])->assertNotFound();
        $this->deleteJson("/api/hr/branches/{$branchId}")->assertNotFound();
    }

    public function test_branch_code_is_unique_per_center_only(): void
    {
        $this->createBranch();
        $this->actingAsUser($this->hrManager)
            ->postJson('/api/hr/branches', ['code' => 'RUH', 'name_ar' => 'مكرر'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');

        $outsider = $this->makeUser('other@example.test', 'hr_manager', self::OTHER_CENTER, $this->hrPermissions());
        $this->actingAsUser($outsider)
            ->postJson('/api/hr/branches', ['code' => 'RUH', 'name_ar' => 'فرع الرياض'])
            ->assertOk();
    }

    public function test_only_one_headquarters_per_center(): void
    {
        $first = $this->createBranch(['code' => 'HQ', 'is_headquarters' => true]);
        $second = $this->createBranch(['code' => 'JED', 'is_headquarters' => true]);

        $this->assertFalse(Branch::query()->findOrFail($first)->is_headquarters);
        $this->assertTrue(Branch::query()->findOrFail($second)->is_headquarters);
    }

    public function test_endpoints_require_hr_permissions(): void
    {
        $viewer = $this->makeUser('viewer@example.test', 'teacher', self::CENTER, ['access_hr-organization']);

        $this->actingAsUser($viewer)->getJson('/api/hr/branches')->assertOk();
        $this->postJson('/api/hr/branches', ['code' => 'X', 'name_ar' => 'x'])->assertForbidden();

        $nobody = $this->makeUser('nobody@example.test', 'teacher');
        $this->actingAsUser($nobody)->getJson('/api/hr/branches')->assertForbidden();
        $this->getJson('/api/hr/settings')->assertForbidden();
        $this->getJson('/api/hr/audit-logs')->assertForbidden();
    }

    public function test_work_location_must_belong_to_a_branch_of_the_center_and_gets_default_radius(): void
    {
        $branchId = $this->createBranch();
        $foreignBranch = Branch::create(['center_id' => self::OTHER_CENTER, 'code' => 'F', 'name_ar' => 'أجنبي']);

        $this->actingAsUser($this->hrManager)
            ->postJson('/api/hr/work-locations', ['branch_id' => $foreignBranch->id, 'name_ar' => 'موقع'])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'reference_not_in_center');

        $this->postJson('/api/hr/work-locations', [
            'branch_id' => $branchId, 'name_ar' => 'المبنى الرئيسي', 'latitude' => 24.7136, 'longitude' => 46.6753,
        ])->assertOk()->assertJsonPath('data.radius_meters', 100);

        $this->postJson('/api/hr/work-locations', ['branch_id' => $branchId, 'name_ar' => 'بدون خط طول', 'latitude' => 24.7])
            ->assertStatus(422)
            ->assertJsonValidationErrors('longitude');
    }

    public function test_department_cannot_be_moved_under_its_own_descendant(): void
    {
        $this->actingAsUser($this->hrManager);
        $parent = $this->postJson('/api/hr/departments', ['code' => 'therapy', 'name_ar' => 'العلاج'])->json('data.id');
        $child = $this->postJson('/api/hr/departments', ['code' => 'physio', 'name_ar' => 'العلاج الطبيعي', 'parent_id' => $parent])->json('data.id');

        $this->putJson("/api/hr/departments/{$parent}", ['parent_id' => $child])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'department_parent_cycle');

        $this->deleteJson("/api/hr/departments/{$parent}")
            ->assertStatus(422)
            ->assertJsonPath('reason', 'department_has_children');
    }
}
