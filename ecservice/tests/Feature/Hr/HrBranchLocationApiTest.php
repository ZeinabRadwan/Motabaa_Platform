<?php

namespace Tests\Feature\Hr;

use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeWorkAssignment;
use App\Support\Hr\HrWorkAssignments;
use Illuminate\Support\Facades\DB;

class HrBranchLocationApiTest extends HrTestCase
{
    private function createLocation(int $branchId, array $overrides = []): int
    {
        return (int) $this->actingAsUser($this->hrManager)
            ->postJson('/api/hr/work-locations', $overrides + [
                'branch_id' => $branchId, 'name_ar' => 'موقع', 'name_en' => 'Site', 'latitude' => 24.7136, 'longitude' => 46.6753, 'radius_meters' => 150,
            ])
            ->assertOk()
            ->json('data.id');
    }

    public function test_branch_fields_are_validated_and_status_changes_only_through_activate_and_deactivate(): void
    {
        $this->actingAsUser($this->hrManager)
            ->postJson('/api/hr/branches', ['code' => 'JED', 'name_ar' => 'جدة', 'email' => 'not-an-email', 'phone' => 'abc', 'country' => 'SAU'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'phone', 'country']);

        $id = $this->createBranch(['code' => 'JED', 'name_ar' => 'جدة', 'name_en' => 'Jeddah', 'city' => 'Jeddah', 'country' => 'sa', 'email' => 'jed@example.test', 'phone' => '+966 12 000 0000']);
        $this->getJson("/api/hr/branches/{$id}")
            ->assertOk()
            ->assertJsonPath('data.country', 'SA')
            ->assertJsonPath('data.email', 'jed@example.test');

        $this->putJson("/api/hr/branches/{$id}", ['is_active' => false, 'city' => 'Makkah'])->assertOk()->assertJsonPath('data.is_active', true);

        $this->patchJson("/api/hr/branches/{$id}/deactivate")->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertSame(1, $this->getJson('/api/hr/branches?is_active=0')->json('total'));
        $this->patchJson("/api/hr/branches/{$id}/activate")->assertOk()->assertJsonPath('data.is_active', true);

        $this->assertSame([$id], array_column($this->getJson('/api/hr/branches?q=makk')->json('data'), 'id'));
        $this->assertContains('Makkah', $this->getJson('/api/hr/branches/cities')->assertOk()->json('data'));

        $editor = $this->makeUser('editor@example.test', 'hr_officer', self::CENTER, ['edit_hr-organization', 'access_hr-all-branches']);
        $this->actingAsUser($editor)->patchJson("/api/hr/branches/{$id}/deactivate")->assertForbidden();
    }

    public function test_branch_and_location_in_use_cannot_be_deactivated_and_list_their_employees(): void
    {
        $this->defaultBranchId();
        $branch = $this->createBranch(['code' => 'DMM', 'name_ar' => 'الدمام', 'name_en' => 'Dammam']);
        $location = $this->createLocation($branch);
        $noCoordinates = $this->createLocation($branch, ['name_ar' => 'مستودع', 'latitude' => null, 'longitude' => null]);
        $employee = $this->createEmployee($this->makeUser('emp@example.test', 'teacher'), ['branch_id' => $branch, 'work_location_id' => $location]);

        $this->getJson("/api/hr/branches/{$branch}")
            ->assertJsonPath('data.employees_count', 1)
            ->assertJsonPath('data.work_locations_count', 2);
        $this->getJson("/api/hr/branches/{$branch}/employees")
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $employee)
            ->assertJsonPath('data.0.work_location_id', $location)
            ->assertJsonPath('data.0.assigned_since', '2025-01-01');
        $this->getJson("/api/hr/work-locations/{$location}/employees")->assertOk()->assertJsonPath('total', 1);
        $this->getJson("/api/hr/work-locations/{$noCoordinates}/employees")->assertOk()->assertJsonPath('total', 0);

        $map = $this->getJson('/api/hr/work-locations/map')->assertOk()->json('data');
        $this->assertSame([$location], array_column($map, 'id'), 'only locations with coordinates are drawn');
        $this->assertSame(150, $map[0]['radius_meters']);
        $this->assertCount(2, $this->getJson('/api/hr/work-locations/map?include_without_coordinates=1')->json('data'));

        $this->patchJson("/api/hr/branches/{$branch}/deactivate")->assertStatus(422)->assertJsonPath('reason', 'branch_has_employees');
        $this->patchJson("/api/hr/work-locations/{$location}/deactivate")->assertStatus(422)->assertJsonPath('reason', 'work_location_has_employees');
        $this->putJson("/api/hr/work-locations/{$location}", ['branch_id' => $this->defaultBranchId()])
            ->assertStatus(422)->assertJsonPath('reason', 'work_location_has_employees');

        $this->patchJson("/api/hr/work-locations/{$noCoordinates}/deactivate")->assertOk()->assertJsonPath('data.is_active', false);
        $this->postJson("/api/hr/employees/{$employee}/work-assignments", [
            'branch_id' => $branch, 'work_location_id' => $noCoordinates, 'effective_from' => today()->toDateString(), 'reason' => 'Move',
        ])->assertStatus(422)->assertJsonPath('reason', 'reference_inactive');

        $empty = $this->createBranch(['code' => 'ABH', 'name_ar' => 'أبها']);
        $this->patchJson("/api/hr/branches/{$empty}/deactivate")->assertOk();
        $this->postJson('/api/hr/employees', ['branch_id' => $empty] + $this->employeePayload($this->makeUser('late@example.test', 'teacher')))
            ->assertStatus(422)->assertJsonPath('reason', 'reference_inactive');
        $this->postJson('/api/hr/work-locations', ['branch_id' => $empty, 'name_ar' => 'x'])
            ->assertStatus(422)->assertJsonPath('reason', 'reference_inactive');
    }

    public function test_work_assignments_are_effective_dated_and_preserve_history(): void
    {
        $headOffice = $this->defaultBranchId();
        $branch = $this->createBranch(['code' => 'DMM', 'name_ar' => 'الدمام']);
        $location = $this->createLocation($branch);
        $otherBranchLocation = $this->createLocation($headOffice, ['name_ar' => 'المقر']);
        $id = $this->createEmployee($this->makeUser('emp@example.test', 'teacher'));
        $url = "/api/hr/employees/{$id}/work-assignments";

        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.branch_id', $headOffice);

        $this->postJson($url, ['branch_id' => $branch, 'work_location_id' => $otherBranchLocation, 'effective_from' => today()->toDateString(), 'reason' => 'x'])
            ->assertStatus(422)->assertJsonPath('reason', 'work_location_branch_mismatch');
        $this->postJson($url, ['branch_id' => $branch, 'effective_from' => today()->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors('reason');

        // Scheduled transfer: nothing changes until its date.
        $future = today()->addDays(10);
        $scheduled = $this->postJson($url, ['branch_id' => $branch, 'work_location_id' => $location, 'effective_from' => $future->toDateString(), 'reason' => 'Opening'])
            ->assertOk()->assertJsonPath('data.is_scheduled', true)->json('data.id');
        $employee = Employee::query()->findOrFail($id);
        $this->assertSame($headOffice, (int) $employee->branch_id);
        $this->assertNull($employee->effectiveWorkLocation());
        $this->assertSame($location, $employee->effectiveWorkLocation($future)->id, 'GPS on the transfer date uses the new location');
        $this->getJson("/api/hr/employees/{$id}")->assertJsonPath('data.work_assignment.next.id', $scheduled);

        $this->postJson($url, ['branch_id' => $branch, 'work_location_id' => $location, 'effective_from' => $future->copy()->addDay()->toDateString(), 'reason' => 'x'])
            ->assertStatus(422)->assertJsonPath('reason', 'assignment_unchanged');

        $this->assertSame(0, HrWorkAssignments::applyDue(self::CENTER));
        $this->postJson("{$url}/{$scheduled}/cancel")->assertOk();
        $this->getJson($url)->assertJsonCount(1, 'data')->assertJsonPath('data.0.effective_to', null);

        // Back-dated transfer: applied immediately, previous row closed the day before.
        $this->postJson($url, ['branch_id' => $branch, 'work_location_id' => $location, 'effective_from' => '2025-06-01', 'reason' => 'Transfer'])
            ->assertOk()->assertJsonPath('data.is_current', true);
        $employee->refresh();
        $this->assertSame($branch, (int) $employee->branch_id);
        $this->assertSame($location, (int) $employee->work_location_id);
        $this->assertSame('2025-05-31', $this->getJson($url)->json('data.1.effective_to'));
        $this->assertSame($headOffice, (int) $employee->workAssignmentOn('2025-03-01')->branch_id, 'the earlier branch stays in history');

        $this->postJson($url, ['branch_id' => $headOffice, 'effective_from' => '2025-03-01', 'reason' => 'x'])
            ->assertStatus(422)->assertJsonPath('reason', 'effective_date_not_after_latest');

        // Same-day correction amends the row instead of adding one.
        $this->postJson($url, ['branch_id' => $branch, 'effective_from' => '2025-06-01', 'reason' => 'No fixed site'])->assertOk();
        $this->getJson($url)->assertJsonCount(2, 'data')->assertJsonPath('data.0.work_location_id', null);
        $this->assertNull($employee->fresh()->work_location_id);

        $history = $this->getJson("/api/hr/employees/{$id}/history?change_types[]=branch&change_types[]=work_location")->json('data');
        $this->assertContains('branch', array_column($history, 'change_type'));
        $this->assertContains('cancelled', array_column($history, 'action'));
    }

    public function test_scheduled_transfers_are_applied_on_their_date(): void
    {
        $headOffice = $this->defaultBranchId();
        $branch = $this->createBranch(['code' => 'DMM', 'name_ar' => 'الدمام']);
        $id = $this->createEmployee($this->makeUser('emp@example.test', 'teacher'));
        $date = today()->addDays(3);

        $this->postJson("/api/hr/employees/{$id}/work-assignments", ['branch_id' => $branch, 'effective_from' => $date->toDateString(), 'reason' => 'x'])->assertOk();
        $this->assertSame($headOffice, (int) Employee::query()->find($id)->branch_id);

        $this->assertSame(0, HrWorkAssignments::applyDue(self::CENTER, $date->copy()->subDay()));
        $this->assertSame(1, HrWorkAssignments::applyDue(self::CENTER, $date));
        $this->assertSame($branch, (int) Employee::query()->find($id)->branch_id);
        $this->assertSame(0, HrWorkAssignments::applyDue(self::CENTER, $date), 'idempotent');

        $this->artisan('hr:apply-work-assignments', ['--center' => self::CENTER])->assertSuccessful();
    }

    public function test_employee_update_and_attendance_route_placement_changes_through_assignments(): void
    {
        $headOffice = $this->defaultBranchId();
        $branch = $this->createBranch(['code' => 'DMM', 'name_ar' => 'الدمام']);
        $site = $this->createLocation($headOffice);
        $id = $this->createEmployee($this->makeUser('emp@example.test', 'teacher'));
        $url = "/api/hr/employees/{$id}";

        $this->putJson("{$url}/attendance", ['attendance_mode' => 'electronic', 'work_location_id' => $site, 'change_reason' => 'Site'])->assertOk();
        $this->assertSame($site, (int) Employee::query()->find($id)->work_location_id);
        $this->assertSame(2, EmployeeWorkAssignment::query()->where('employee_id', $id)->count(), 'a new row from today');

        $branchSite = $this->createLocation($branch);
        $this->putJson("{$url}/attendance", ['attendance_mode' => 'electronic', 'work_location_id' => $branchSite])
            ->assertStatus(422)->assertJsonPath('reason', 'work_location_branch_mismatch');

        $this->putJson($url, ['branch_id' => $branch, 'change_reason' => 'Transfer'])
            ->assertStatus(422)->assertJsonPath('reason', 'gps_requires_location');
        $this->putJson($url, ['branch_id' => $branch, 'work_location_id' => $branchSite, 'change_reason' => 'Transfer'])->assertOk();
        $employee = Employee::query()->find($id);
        $this->assertSame($branch, (int) $employee->branch_id);
        $this->assertSame($branchSite, (int) $employee->work_location_id);
        $this->assertSame(2, EmployeeWorkAssignment::query()->where('employee_id', $id)->count(), 'same-day change amends today\'s row');
    }

    public function test_branch_managers_only_reach_the_branches_they_manage(): void
    {
        $headOffice = $this->defaultBranchId();
        $branch = $this->createBranch(['code' => 'DMM', 'name_ar' => 'الدمام']);
        $managerUser = $this->makeUser('manager@example.test', 'branch_manager', self::CENTER, [
            'access_hr-organization', 'edit_hr-organization', 'access_hr-employees', 'edit_hr-employees',
        ]);
        $managerEmployee = $this->createEmployee($managerUser, ['branch_id' => $branch]);
        $local = $this->createEmployee($this->makeUser('local@example.test', 'teacher'), ['branch_id' => $branch]);
        $remote = $this->createEmployee($this->makeUser('remote@example.test', 'teacher'));
        $this->putJson("/api/hr/branches/{$branch}", ['manager_employee_id' => $managerEmployee])->assertOk();

        $this->actingAsUser($managerUser);
        $this->assertSame([$branch], array_column($this->getJson('/api/hr/branches')->assertOk()->json('data'), 'id'));
        $this->getJson("/api/hr/branches/{$headOffice}")->assertNotFound();
        $this->getJson("/api/hr/branches/{$headOffice}/employees")->assertNotFound();

        $visible = array_column($this->getJson('/api/hr/employees')->assertOk()->json('data'), 'id');
        $this->assertEqualsCanonicalizing([$managerEmployee, $local], $visible);
        $this->getJson("/api/hr/employees/{$remote}")->assertNotFound();
        $this->getJson("/api/hr/employees/{$local}")->assertOk();

        $this->postJson('/api/hr/branches', ['code' => 'NEW', 'name_ar' => 'جديد'])->assertForbidden()->assertJsonPath('reason', 'branch_scope_required');
        $this->putJson("/api/hr/branches/{$branch}", ['manager_employee_id' => $local])->assertForbidden();
        $this->putJson("/api/hr/branches/{$branch}", ['address' => 'King Fahd Rd'])->assertOk();

        $this->postJson('/api/hr/work-locations', ['branch_id' => $headOffice, 'name_ar' => 'x'])
            ->assertForbidden()->assertJsonPath('reason', 'branch_not_authorized');
        $this->postJson('/api/hr/work-locations', ['branch_id' => $branch, 'name_ar' => 'موقع الفرع'])->assertOk();

        $this->postJson("/api/hr/employees/{$local}/work-assignments", ['branch_id' => $headOffice, 'effective_from' => today()->toDateString(), 'reason' => 'x'])
            ->assertForbidden();

        $lookups = $this->getJson('/api/hr/lookups')->assertOk()->json('data');
        $this->assertTrue($lookups['branch_scope']['restricted']);
        $this->assertSame([$branch], array_column($lookups['branches'], 'value'));

        $stranger = $this->makeUser('stranger@example.test', 'hr_officer', self::CENTER, ['access_hr-organization']);
        $this->actingAsUser($stranger)->getJson('/api/hr/branches')->assertOk()->assertJsonPath('total', 0);

        $this->assertFalse($this->actingAsUser($this->hrManager)->getJson('/api/hr/lookups')->json('data.branch_scope.restricted'));
        $this->assertSame(3, DB::table('hr_employees')->count());
    }
}
