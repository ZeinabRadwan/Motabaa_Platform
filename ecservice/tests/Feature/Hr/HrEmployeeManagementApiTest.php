<?php

namespace Tests\Feature\Hr;

use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeChange;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HrEmployeeManagementApiTest extends HrTestCase
{
    public function test_new_employee_registration_validates_saudi_identity_age_dates_and_uniqueness(): void
    {
        $roleId = (int) DB::table('roles')->insertGetId(['name' => 'Therapist', 'default_name' => 'therapist', 'guard_name' => 'web']);
        $parentRoleId = (int) DB::table('roles')->insertGetId(['name' => 'Parent', 'default_name' => 'parent', 'guard_name' => 'web']);
        $idNumber = self::validIdNumber('1', 777);
        $base = [
            'mode' => 'new', 'role_id' => $roleId, 'name' => 'سارة أحمد', 'phone' => '0551234567', 'email' => 'sara@example.test',
            'nationality' => 'SA', 'gender' => 2, 'birthdate' => '1995-03-10', 'id_or_residence_number' => $idNumber,
            'job_title' => 'Therapist', 'branch_id' => $this->defaultBranchId(), 'department_id' => $this->defaultDepartmentId(),
            'hire_date' => '2026-01-01', 'employment_start_date' => '2026-01-05', 'attendance_mode' => 'manual',
        ];
        $this->actingAsUser($this->hrManager);

        $this->postJson('/api/hr/employees', ['id_or_residence_number' => '1000000001'] + $base)
            ->assertStatus(422)->assertJsonValidationErrors('id_or_residence_number');
        $this->postJson('/api/hr/employees', ['id_or_residence_number' => self::validIdNumber('2', 777)] + $base)
            ->assertStatus(422)->assertJsonValidationErrors('id_or_residence_number');
        $this->postJson('/api/hr/employees', ['birthdate' => today()->subYears(14)->toDateString()] + $base)
            ->assertStatus(422)->assertJsonValidationErrors('birthdate');
        $this->postJson('/api/hr/employees', ['employment_start_date' => '2025-12-31'] + $base)
            ->assertStatus(422)->assertJsonValidationErrors('employment_start_date');
        $this->postJson('/api/hr/employees', ['contract_type' => 'temporary'] + $base)
            ->assertStatus(422)->assertJsonValidationErrors('contract_end_date');
        $this->postJson('/api/hr/employees', array_diff_key($base, ['branch_id' => 1, 'job_title' => 1, 'nationality' => 1]))
            ->assertStatus(422)->assertJsonValidationErrors(['branch_id', 'job_title', 'nationality']);
        $this->postJson('/api/hr/employees', ['role_id' => $parentRoleId] + $base)
            ->assertStatus(422)->assertJsonPath('reason', 'role_not_staff');

        $arabicDigits = strtr($idNumber, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
        $created = $this->postJson('/api/hr/employees', ['id_or_residence_number' => $arabicDigits] + $base)
            ->assertOk()
            ->assertJsonPath('data.id_or_residence_number', $idNumber)
            ->assertJsonPath('data.gender', 2)
            ->assertJsonPath('data.employment_start_date', '2026-01-05');

        $user = User::query()->where('email', 'sara@example.test')->firstOrFail();
        $this->assertSame(User::STATUS_CANNT_LOGIN, (int) $user->status, 'new employees cannot log in until an admin enables it');
        $this->assertTrue(DB::table('centers_users')->where(['user_id' => $user->id, 'center_id' => self::CENTER])->exists());
        $this->assertTrue(DB::table('model_has_roles')->where(['model_id' => $user->id, 'role_id' => $roleId])->exists());

        $other = $this->makeUser('other@example.test', 'teacher');
        $this->postJson('/api/hr/employees', ['id_or_residence_number' => $idNumber] + $this->employeePayload($other))
            ->assertStatus(422)->assertJsonPath('reason', 'id_number_taken');
        $this->postJson('/api/hr/employees', ['employee_number' => $created->json('data.employee_number')] + $this->employeePayload($other))
            ->assertStatus(422)->assertJsonPath('reason', 'employee_number_taken');
        $this->postJson('/api/hr/employees', ['phone' => '0551234567'] + $this->employeePayload($other))
            ->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->assertNull($other->fresh()->id_or_residence_number, 'a rejected registration leaves the user untouched');
    }

    public function test_list_filters_searches_and_sorts(): void
    {
        $therapist = $this->createEmployee($this->makeUser('a@example.test', 'teacher'), ['job_title' => 'Therapist', 'attendance_mode' => 'exempt']);
        $teacherUser = $this->makeUser('b@example.test', 'teacher');
        $teacher = $this->createEmployee($teacherUser);

        $ids = fn (string $query) => array_column($this->getJson('/api/hr/employees'.$query)->assertOk()->json('data'), 'id');

        $this->assertSame([$therapist], $ids('?job_title=Therapist'));
        $this->assertSame([$therapist], $ids('?attendance_mode=exempt'));
        $this->assertSame([$teacher], $ids('?q='.self::validIdNumber('1', $teacherUser->id)));
        $this->assertSame([$teacher, $therapist], $ids('?options[sortBy][0][key]=employee_number&options[sortBy][0][order]=desc'));
        $this->assertSame([], $ids('?employment_status=terminated'));

        $titles = array_column($this->getJson('/api/hr/lookups')->json('data.job_titles'), 'value');
        $this->assertSame(['Teacher', 'Therapist'], $titles);
    }

    public function test_edits_are_logged_with_effective_dates_and_frozen_labels_and_respect_the_payroll_lock(): void
    {
        $id = $this->createEmployee($this->makeUser('emp@example.test', 'teacher'));
        $jeddah = $this->createBranch(['code' => 'JED', 'name_ar' => 'جدة', 'name_en' => 'Jeddah']);
        $effective = today()->subDays(3)->toDateString();

        $this->putJson("/api/hr/employees/{$id}", [
            'branch_id' => $jeddah, 'job_title' => 'Senior Teacher', 'effective_date' => $effective, 'change_reason' => 'Promotion',
        ])->assertOk()->assertJsonPath('data.branch_id', $jeddah)->assertJsonPath('data.job_title', 'Senior Teacher');

        $this->putJson("/api/hr/branches/{$jeddah}", ['code' => 'JED', 'name_ar' => 'جدة الجديد', 'name_en' => 'Renamed'])->assertOk();

        $history = collect($this->getJson("/api/hr/employees/{$id}/history")->assertOk()->json('data'));
        $transfer = $history->firstWhere('change_type', 'branch');
        $this->assertSame('Jeddah', $transfer['new_display'], 'history keeps the name the branch had at the time');
        $this->assertSame($effective, $transfer['effective_date']);
        $this->assertSame('Promotion', $transfer['reason']);
        $title = $history->firstWhere('change_type', 'job_title');
        $this->assertSame(['Teacher', 'Senior Teacher'], [$title['old_display'], $title['new_display']]);
        $this->assertNotNull($history->firstWhere('change_type', 'hired'));

        $this->putJson("/api/hr/employees/{$id}", ['job_title' => 'X', 'effective_date' => today()->addDay()->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors('effective_date');

        $this->putJson('/api/hr/settings', ['settings' => ['payroll' => ['changes_locked_until' => today()->subDay()->toDateString()]]])->assertOk();
        $this->putJson("/api/hr/employees/{$id}", ['job_title' => 'Head Teacher', 'effective_date' => today()->subDays(2)->toDateString()])
            ->assertStatus(422)->assertJsonPath('reason', 'effective_date_locked');
        $this->putJson("/api/hr/employees/{$id}", ['address_city' => 'Riyadh', 'effective_date' => today()->subDays(2)->toDateString()])
            ->assertOk()->assertJsonPath('data.address.city', 'Riyadh');

        $this->assertFalse(EmployeeChange::query()->first()->delete(), 'the timeline is append-only');
    }

    public function test_salary_changes_are_effective_dated_immutable_and_only_visible_with_compensation_access(): void
    {
        $id = $this->createEmployee($this->makeUser('emp@example.test', 'teacher'));
        $url = "/api/hr/employees/{$id}/salaries";
        $package = ['basic_salary' => 8000, 'housing_allowance' => 2000, 'transportation_allowance' => 800, 'reason' => 'Hiring'];

        $this->postJson($url, ['effective_from' => '2025-01-01'] + $package)
            ->assertOk()->assertJsonPath('data.gross_salary', '10800.00')->assertJsonPath('data.is_current', true);
        $this->postJson($url, ['effective_from' => '2025-01-01'] + $package)
            ->assertStatus(422)->assertJsonPath('reason', 'effective_date_not_after_latest');
        $this->postJson($url, ['effective_from' => '2026-01-01', 'basic_salary' => 0] + $package)->assertStatus(422)->assertJsonValidationErrors('basic_salary');
        $this->postJson($url, ['effective_from' => '2026-01-01', 'housing_allowance' => -1] + $package)->assertStatus(422)->assertJsonValidationErrors('housing_allowance');
        $this->postJson($url, ['effective_from' => '2026-01-01', 'basic_salary' => '12.345'] + $package)->assertStatus(422)->assertJsonValidationErrors('basic_salary');

        $future = today()->addMonthNoOverflow()->startOfMonth();
        $raise = $this->postJson($url, ['effective_from' => $future->toDateString(), 'basic_salary' => 9000, 'reason' => 'Raise'] + $package)
            ->assertOk()->assertJsonPath('data.is_scheduled', true)->json('data.id');

        $rows = $this->getJson($url)->assertOk()->json('data');
        $this->assertCount(2, $rows);
        $this->assertSame($future->copy()->subDay()->toDateString(), $rows[1]['effective_to'], 'the previous package closes the day before');

        $employee = Employee::query()->findOrFail($id);
        $this->assertSame('8000.00', (string) $employee->salaryOn('2025-06-01')->basic_salary);
        $this->assertSame('9000.00', (string) $employee->salaryOn($future->copy()->addDays(5))->basic_salary);

        $types = array_column($this->getJson("/api/hr/employees/{$id}/history?change_types[]=salary&change_types[]=allowances")->json('data'), 'change_type');
        $this->assertSame(['salary', 'allowances', 'salary'], $types, 'the raise changed the basic salary only');

        $this->postJson("{$url}/{$rows[1]['id']}/cancel")->assertStatus(422)->assertJsonPath('reason', 'change_not_cancellable');
        $this->postJson("{$url}/{$raise}/cancel")->assertOk();
        $this->assertNull($this->getJson($url)->json('data.0.effective_to'), 'cancelling reopens the previous package');

        $viewer = $this->makeUser('viewer@example.test', 'hr_officer', self::CENTER, ['access_hr-employees', 'access_hr-all-branches']);
        $this->actingAsUser($viewer)->getJson("/api/hr/employees/{$id}")
            ->assertOk()->assertJsonPath('data.financial', null)->assertJsonPath('data.can_view_compensation', false);
        $this->getJson($url)->assertForbidden();
        $visibleTypes = array_column($this->getJson("/api/hr/employees/{$id}/history")->json('data'), 'change_type');
        $this->assertEmpty(array_intersect($visibleTypes, EmployeeChange::FINANCIAL_TYPES));
    }

    public function test_insurance_category_must_match_nationality_and_requires_a_contribution_start(): void
    {
        $id = $this->createEmployee($this->makeUser('emp@example.test', 'teacher'));
        $url = "/api/hr/employees/{$id}/insurances";
        $row = ['effective_from' => '2025-01-01', 'employee_rate' => 9.75, 'employer_rate' => 11.75, 'contribution_start_date' => '2025-01-01'];

        $this->postJson($url, ['category' => 'non_saudi'] + $row)->assertStatus(422)->assertJsonPath('reason', 'insurance_category_nationality');
        $this->postJson($url, ['category' => 'saudi', 'employee_rate' => 101] + $row)->assertStatus(422)->assertJsonValidationErrors('employee_rate');
        $this->postJson($url, ['category' => 'saudi', 'contribution_start_date' => null] + $row)->assertStatus(422)->assertJsonValidationErrors('contribution_start_date');
        $this->postJson($url, ['category' => 'saudi'] + $row)->assertOk()->assertJsonPath('data.total_rate', '21.50');

        $this->getJson("/api/hr/employees/{$id}")->assertJsonPath('data.financial.current_insurance.category', 'saudi');
    }

    public function test_deactivation_closes_the_employment_period_and_drives_payroll_eligibility(): void
    {
        $id = $this->createEmployee($this->makeUser('emp@example.test', 'teacher'), ['hire_date' => '2026-01-01', 'employment_start_date' => '2026-01-01']);
        $editor = $this->makeUser('editor@example.test', 'hr_officer', self::CENTER, ['access_hr-employees', 'edit_hr-employees']);
        $end = ['end_date' => '2026-03-15', 'termination_reason' => 'resignation', 'notes' => 'Resigned'];

        $this->actingAsUser($editor)->patchJson("/api/hr/employees/{$id}/deactivate", $end)->assertForbidden();

        $this->actingAsUser($this->hrManager);
        $this->patchJson("/api/hr/employees/{$id}/deactivate", ['end_date' => '2025-12-31'] + $end)
            ->assertStatus(422)->assertJsonPath('reason', 'deactivation_before_start');
        $this->patchJson("/api/hr/employees/{$id}/deactivate", $end)->assertOk()->assertJsonPath('data.terminated_at', '2026-03-15');
        $this->patchJson("/api/hr/employees/{$id}/deactivate", $end)->assertStatus(422)->assertJsonPath('reason', 'employee_already_inactive');

        $eligible = fn (string $from, string $to) => collect($this->getJson("/api/hr/employees/payroll-eligibility?period_start={$from}&period_end={$to}")->assertOk()->json('data'))->firstWhere('id', $id);
        $this->assertSame([['from' => '2026-03-01', 'to' => '2026-03-15']], $eligible('2026-03-01', '2026-03-31')['intervals']);
        $this->assertNull($eligible('2026-04-01', '2026-04-30'), 'deactivated employees are not in later payroll periods');

        $this->postJson("/api/hr/employees/{$id}/salaries", ['effective_from' => '2026-04-01', 'basic_salary' => 5000, 'reason' => 'x'])
            ->assertStatus(422)->assertJsonPath('reason', 'effective_date_after_termination');

        $this->patchJson("/api/hr/employees/{$id}/activate", ['start_date' => '2026-03-10'])
            ->assertStatus(422)->assertJsonPath('reason', 'activation_before_last_end');
        $this->patchJson("/api/hr/employees/{$id}/activate", ['start_date' => '2026-05-01'])
            ->assertOk()->assertJsonPath('data.employment_status', 'active')->assertJsonCount(2, 'data.employment_periods');

        $this->assertNull($eligible('2026-04-01', '2026-04-30'));
        $this->assertSame([['from' => '2026-05-01', 'to' => '2026-05-31']], $eligible('2026-05-01', '2026-05-31')['intervals']);

        $this->putJson('/api/hr/settings', ['settings' => ['payroll' => ['changes_locked_until' => '2026-05-31']]])->assertOk();
        $this->patchJson("/api/hr/employees/{$id}/deactivate", ['end_date' => '2026-05-20'] + $end)
            ->assertStatus(422)->assertJsonPath('reason', 'effective_date_locked');

        $types = array_column($this->getJson("/api/hr/employees/{$id}/history")->json('data'), 'change_type');
        $this->assertContains('terminated', $types);
        $this->assertContains('rehired', $types);
    }

    public function test_attendance_configuration_shifts_and_policies(): void
    {
        $branchId = $this->defaultBranchId();
        $id = $this->createEmployee($this->makeUser('emp@example.test', 'teacher'));
        $bare = $this->postJson('/api/hr/work-locations', ['branch_id' => $branchId, 'name_ar' => 'بدون إحداثيات'])->assertOk()->json('data.id');
        $site = $this->postJson('/api/hr/work-locations', ['branch_id' => $branchId, 'name_ar' => 'المقر', 'latitude' => 24.7, 'longitude' => 46.7])->assertOk()->json('data.id');
        $policy = $this->postJson('/api/hr/attendance-policies', [
            'code' => 'STD', 'name_ar' => 'قياسية', 'late_grace_minutes' => 10, 'early_leave_grace_minutes' => 5,
        ])->assertOk()->json('data.id');

        $url = "/api/hr/employees/{$id}/attendance";
        $this->putJson($url, ['attendance_mode' => 'electronic', 'gps_policy' => 'required', 'work_location_id' => $bare])
            ->assertStatus(422)->assertJsonPath('reason', 'gps_requires_location');
        $this->putJson($url, ['attendance_mode' => 'electronic', 'gps_policy' => 'required', 'work_location_id' => $site, 'attendance_policy_id' => $policy])
            ->assertOk()
            ->assertJsonPath('data.attendance.mode', 'electronic')
            ->assertJsonPath('data.attendance.effective_grace_minutes', 10);
        $this->putJson($url, ['attendance_mode' => 'manual', 'gps_policy' => 'required', 'grace_minutes' => 20, 'attendance_policy_id' => $policy])
            ->assertOk()
            ->assertJsonPath('data.attendance.gps_policy', 'none')
            ->assertJsonPath('data.attendance.effective_grace_minutes', 20);

        $types = array_column($this->getJson("/api/hr/employees/{$id}/history")->json('data'), 'change_type');
        $this->assertSame(2, count(array_keys($types, 'attendance_method')));

        $shift = $this->postJson('/api/hr/shifts', [
            'code' => 'AM', 'name_ar' => 'صباحي', 'start_time' => '08:00', 'end_time' => '16:00', 'working_days' => [0, 1, 2, 3, 4],
        ])->assertOk()->assertJsonPath('data.time_range', '08:00 - 16:00')->json('data.id');
        $this->postJson('/api/hr/shifts', ['code' => 'AM', 'name_ar' => 'x', 'start_time' => '08:00', 'end_time' => '16:00', 'working_days' => [0]])
            ->assertStatus(422)->assertJsonValidationErrors('code');

        $this->postJson("/api/hr/employees/{$id}/shifts", ['shift_id' => $shift, 'effective_from' => '2025-01-01'])->assertOk();
        $this->getJson("/api/hr/employees/{$id}")->assertJsonPath('data.attendance.current_shift.shift_id', $shift);
        $this->deleteJson("/api/hr/shifts/{$shift}")->assertStatus(422)->assertJsonPath('reason', 'shift_in_use');
        $this->deleteJson("/api/hr/attendance-policies/{$policy}")->assertStatus(422)->assertJsonPath('reason', 'attendance_policy_in_use');

        $this->assertSame('hr_shift', \App\Models\Hr\Shift::query()->firstOrFail()->getMorphClass());
    }

    public function test_documents_are_private_and_typed_and_deletion_needs_admin(): void
    {
        Storage::fake('local');
        $id = $this->createEmployee($this->makeUser('emp@example.test', 'teacher'));
        $url = "/api/hr/employees/{$id}/documents";
        $file = UploadedFile::fake()->create('contract.pdf', 120, 'application/pdf');

        $this->post($url, ['file' => $file, 'category' => 'contract', 'document_type' => 'iqama', 'title' => 'Contract'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('reason', 'document_type_category_mismatch');
        $this->post($url, ['file' => UploadedFile::fake()->create('x.exe', 10), 'category' => 'contract', 'document_type' => 'employment_contract', 'title' => 'x'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('file');

        $documentId = $this->post($url, [
            'file' => $file, 'category' => 'contract', 'document_type' => 'employment_contract', 'title' => 'Employment contract',
            'issue_date' => '2025-01-01', 'expiry_date' => today()->addDays(30)->toDateString(),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.expires_soon', true)->json('data.id');

        $path = DB::table('hr_employee_documents')->where('id', $documentId)->value('path');
        $this->assertStringStartsWith("hr/center_1/employees/{$id}/", $path);
        Storage::disk('local')->assertExists($path);

        $this->get("{$url}/{$documentId}/download")->assertOk()->assertDownload('contract.pdf');
        $this->getJson("{$url}?category=contract")->assertOk()->assertJsonCount(1, 'data');

        $uploader = $this->makeUser('uploader@example.test', 'hr_officer', self::CENTER, ['access_hr-documents', 'edit_hr-documents']);
        $this->actingAsUser($uploader)->deleteJson("{$url}/{$documentId}")->assertForbidden();
        $outsider = $this->makeUser('other@example.test', 'hr_manager', self::OTHER_CENTER, $this->hrPermissions());
        $this->actingAsUser($outsider)->get("{$url}/{$documentId}/download")->assertNotFound();

        $this->actingAsUser($this->hrManager)->deleteJson("{$url}/{$documentId}")->assertOk();
        Storage::disk('local')->assertExists($path);
    }

    public function test_photo_upload_requires_an_image(): void
    {
        $id = $this->createEmployee($this->makeUser('emp@example.test', 'teacher'));

        $this->postJson("/api/hr/employees/{$id}/photo", [])->assertStatus(422)->assertJsonValidationErrors('photo');
    }
}
