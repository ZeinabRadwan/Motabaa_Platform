<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * Manual attendance and corrections in the central attendance records: calculations (grace periods, overnight
 * shifts), duplicate / overlap / date and time validation, history limits and payroll lock, own-record and branch
 * authorization, electronic record corrections, change history and audit, bulk entry, Excel / CSV import, and the
 * electronic flow ignoring manual records.
 */
class HrManualAttendanceApiTest extends HrTestCase
{
    use InteractsWithElectronicAttendance;

    private int $policyId;

    private int $employeeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpElectronicAttendance();
        $this->policyId = $this->createPolicy([
            'early_leave_grace_minutes' => 0, 'late_counting' => 'full', 'early_leave_counting' => 'full',
        ]);
        $this->employeeId = $this->manualEmployee();
        $this->at('2026-03-10 12:00'); // Tuesday
    }

    public function test_manual_record_without_gps_calculates_duration_lateness_early_departure_and_status(): void
    {
        $response = $this->record([
            'attendance_date' => '2026-03-09', 'check_in' => '08:30', 'check_out' => '15:30', 'notes' => 'Paper sheet',
        ])->assertOk();

        $response->assertJsonPath('data.attendance_method', 'manual')
            ->assertJsonPath('data.attendance_status', 'late_early_departure')
            ->assertJsonPath('data.status', 'checked_out')
            ->assertJsonPath('data.check_in.time', '08:30')
            ->assertJsonPath('data.check_in.verification', 'manual')
            ->assertJsonPath('data.check_out.time', '15:30')
            ->assertJsonPath('data.late_minutes', 30)
            ->assertJsonPath('data.early_departure_minutes', 30)
            ->assertJsonPath('data.duration_minutes', 420)
            ->assertJsonPath('data.worked_minutes', 420)
            ->assertJsonPath('data.entry_source', 'form')
            ->assertJsonPath('data.entry_reason', 'manual_attendance_method')
            ->assertJsonPath('data.notes', 'Paper sheet')
            ->assertJsonPath('data.created_by.id', $this->hrManager->id)
            ->assertJsonPath('data.is_corrected', false);

        $row = DB::table('hr_attendance_records')->where('employee_id', $this->employeeId)->first();
        $this->assertSame('2026-03-09 08:30:00', (string) $row->check_in_at);
        $this->assertNull($row->check_in_latitude);
        $this->assertNull($row->check_out_latitude);
        $this->assertSame('none', $row->gps_requirement);
        $this->assertNull($row->open_session_key);
        $this->assertSame($this->shiftId, (int) $row->shift_id);

        $change = DB::table('hr_attendance_record_changes')->where('attendance_record_id', $row->id)->first();
        $this->assertSame('created', $change->action);
        $this->assertSame((int) $this->hrManager->id, (int) $change->changed_by);
        $this->assertStringContainsString('08:30', (string) $change->new_values);
        $this->assertSame(1, DB::table('hr_audit_logs')->where('auditable_type', 'hr_attendance_record')->where('auditable_id', $row->id)->count());
    }

    public function test_grace_period_and_overnight_shift(): void
    {
        $this->record(['attendance_date' => '2026-03-08', 'check_in' => '08:10', 'check_out' => '16:00'])->assertOk()
            ->assertJsonPath('data.late_minutes', 0)
            ->assertJsonPath('data.attendance_status', 'present');
        $this->record(['attendance_date' => '2026-03-09', 'check_in' => '08:12', 'check_out' => '16:05'])->assertOk()
            ->assertJsonPath('data.late_minutes', 12)
            ->assertJsonPath('data.attendance_status', 'late');

        $night = $this->createShift('NT', '22:00', '06:00', [0, 1, 2, 3, 4, 5]);
        $nightEmployee = $this->manualEmployee(['shift_id' => $night], 'night@example.test');
        $this->actingAsUser($this->hrManager);

        $this->postJson('/api/hr/attendance-records/preview', [
            'employee_id' => $nightEmployee, 'attendance_date' => '2026-03-08', 'check_in' => '22:05', 'check_out' => '06:00',
        ])->assertOk()
            ->assertJsonPath('data.check_out.date', '2026-03-09')
            ->assertJsonPath('data.duration_minutes', 475)
            ->assertJsonPath('data.attendance_status', 'present');

        $this->record(['employee_id' => $nightEmployee, 'attendance_date' => '2026-03-08', 'check_in' => '22:05', 'check_out' => '06:00'])->assertOk()
            ->assertJsonPath('data.attendance_date', '2026-03-08')
            ->assertJsonPath('data.check_in.date', '2026-03-08')
            ->assertJsonPath('data.check_out.date', '2026-03-09')
            ->assertJsonPath('data.worked_minutes', 475);

        // A check-in written after midnight still belongs to the shift that started the evening before.
        $this->record(['employee_id' => $nightEmployee, 'attendance_date' => '2026-03-09', 'check_in' => '00:30', 'check_out' => '06:00'])->assertOk()
            ->assertJsonPath('data.check_in.date', '2026-03-10')
            ->assertJsonPath('data.late_minutes', 150);
    }

    public function test_duplicates_overlaps_and_invalid_dates_or_times_are_refused(): void
    {
        $this->record(['attendance_date' => '2026-03-08', 'check_in' => '08:00', 'check_out' => '16:00'])->assertOk();
        $this->record(['attendance_date' => '2026-03-08', 'check_in' => '09:00'])->assertStatus(409)
            ->assertJsonPath('reason', 'attendance_record_exists');

        // Saturday (no shift) running into Sunday's record.
        $this->record(['attendance_date' => '2026-03-07', 'check_in' => '20:00', 'check_out' => '09:00'])->assertStatus(422)
            ->assertJsonPath('reason', 'attendance_overlaps_record');
        $this->record(['attendance_date' => '2026-03-07', 'check_in' => '09:00', 'check_out' => '13:00'])->assertOk()
            ->assertJsonPath('data.attendance_status', 'off_schedule');

        $this->record(['attendance_date' => '2026-03-11', 'check_in' => '08:00'])->assertStatus(422)->assertJsonPath('reason', 'attendance_date_in_future');
        $this->record(['attendance_date' => '2026-03-10', 'check_in' => '08:00', 'check_out' => '13:00'])->assertStatus(422)
            ->assertJsonPath('reason', 'attendance_time_in_future');
        $this->record(['attendance_date' => '2026-03-09', 'check_in' => '08:00', 'check_out' => '07:00', 'check_out_date' => '2026-03-09'])->assertStatus(422)
            ->assertJsonPath('reason', 'attendance_check_out_before_check_in');
        $this->record(['attendance_date' => '2026-03-09', 'check_in' => '02:00'])->assertStatus(422)
            ->assertJsonPath('reason', 'attendance_time_outside_day');

        $this->record(['attendance_date' => '2026-03-09'])->assertStatus(422)->assertJsonValidationErrors('check_in');
        $this->record(['attendance_date' => '2026-03-09', 'check_in' => '8am'])->assertStatus(422)->assertJsonValidationErrors('check_in');
        $this->record(['attendance_date' => '09/03/2026', 'check_in' => '08:00'])->assertStatus(422)->assertJsonValidationErrors('attendance_date');
        $this->record(['attendance_date' => '2026-03-09', 'check_in' => '08:00', 'entry_reason' => null])->assertStatus(422)->assertJsonValidationErrors('entry_reason');
        $this->record(['attendance_date' => '2026-03-09', 'check_in' => '08:00', 'entry_reason' => 'other'])->assertStatus(422)->assertJsonValidationErrors('entry_reason_notes');

        $this->assertSame(2, DB::table('hr_attendance_records')->count());
    }

    public function test_history_limit_payroll_lock_and_permissions(): void
    {
        $this->updateSettings(['manual_entry_max_days_back' => 5]);
        $clerk = $this->makeUser('clerk@example.test', 'hr_officer', self::CENTER, ['access_hr-attendance-records', 'edit_hr-attendance-records', 'access_hr-all-branches']);

        $this->actingAsUser($clerk)->getJson('/api/hr/attendance-records/summary')->assertOk()
            ->assertJsonPath('data.limits.oldest_date', '2026-03-05')
            ->assertJsonPath('data.limits.can_enter_historical', false);
        $this->record(['attendance_date' => '2026-03-02', 'check_in' => '08:00'], $clerk)->assertForbidden()
            ->assertJsonPath('reason', 'attendance_date_too_old');
        $this->record(['attendance_date' => '2026-03-05', 'check_in' => '08:00'], $clerk)->assertOk();

        $this->postJson('/api/hr/attendance-records/preview', ['employee_id' => $this->employeeId, 'attendance_date' => '2026-03-02', 'check_in' => '08:00'])
            ->assertForbidden();
        $this->actingAsUser($this->hrManager)
            ->postJson('/api/hr/attendance-records/preview', ['employee_id' => $this->employeeId, 'attendance_date' => '2026-03-02', 'check_in' => '08:00'])
            ->assertOk()->assertJsonPath('data.warnings', ['missing_check_out', 'historical']);
        $this->record(['attendance_date' => '2026-03-02', 'check_in' => '08:00', 'check_out' => '16:00'])->assertOk();

        $this->putJson('/api/hr/settings', ['settings' => ['payroll' => ['changes_locked_until' => '2026-03-03']]])->assertOk();
        $this->record(['attendance_date' => '2026-03-03', 'check_in' => '08:00'])->assertStatus(422)->assertJsonPath('reason', 'attendance_date_locked');
        $locked = (int) DB::table('hr_attendance_records')->whereDate('attendance_date', '2026-03-02')->value('id');
        $this->putJson("/api/hr/attendance-records/{$locked}", ['check_out' => '17:00', 'reason' => 'Late correction'])->assertStatus(422)
            ->assertJsonPath('reason', 'attendance_date_locked');

        $viewer = $this->makeUser('viewer@example.test', 'hr_officer', self::CENTER, ['access_hr-attendance-records', 'access_hr-all-branches']);
        $this->actingAsUser($viewer)->getJson('/api/hr/attendance-records')->assertOk();
        $this->record(['attendance_date' => '2026-03-09', 'check_in' => '08:00'], $viewer)->assertForbidden();
        $this->putJson("/api/hr/attendance-records/{$locked}", ['notes' => 'x', 'reason' => 'Not allowed'])->assertForbidden();

        $staff = $this->makeUser('staff@example.test', 'teacher', self::CENTER, ['apply_hr-self-attendance']);
        $this->actingAsUser($staff)->getJson('/api/hr/attendance-records')->assertForbidden();
    }

    public function test_employees_cannot_edit_their_own_records_unless_authorized(): void
    {
        $clerk = $this->makeUser('clerk@example.test', 'hr_officer', self::CENTER, ['access_hr-attendance-records', 'edit_hr-attendance-records', 'access_hr-all-branches']);
        $own = $this->manualEmployee([], 'clerk@example.test', [], $clerk);
        $this->actingAsUser($clerk);

        $this->record(['employee_id' => $own, 'attendance_date' => '2026-03-09', 'check_in' => '08:00'], $clerk)->assertForbidden()
            ->assertJsonPath('reason', 'attendance_own_record');
        $id = $this->record(['employee_id' => $own, 'attendance_date' => '2026-03-09', 'check_in' => '08:00'])->assertOk()->json('data.id');
        $this->actingAsUser($clerk)->putJson("/api/hr/attendance-records/{$id}", ['check_out' => '16:00', 'reason' => 'Left at four'])->assertForbidden()
            ->assertJsonPath('reason', 'attendance_own_record');
        $this->actingAsUser($clerk)->getJson('/api/hr/attendance-records/summary')->assertOk()
            ->assertJsonPath('data.limits.own_employee_id', $own)
            ->assertJsonPath('data.limits.can_edit_own', false);

        $clerk->givePermissionTo('edit_hr-own-attendance-records');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAsUser($clerk)->putJson("/api/hr/attendance-records/{$id}", ['check_out' => '16:00', 'reason' => 'Left at four'])->assertOk()
            ->assertJsonPath('data.attendance_status', 'present');
    }

    public function test_branch_managers_only_reach_their_branch(): void
    {
        $branch = $this->createBranch(['code' => 'DMM', 'name_ar' => 'الدمام']);
        $managerUser = $this->makeUser('manager@example.test', 'branch_manager', self::CENTER, ['access_hr-attendance-records', 'edit_hr-attendance-records']);
        $managerEmployee = $this->createEmployee($managerUser, ['branch_id' => $branch]);
        $this->putJson("/api/hr/branches/{$branch}", ['manager_employee_id' => $managerEmployee])->assertOk();
        $local = $this->manualEmployee([], 'local@example.test', ['branch_id' => $branch]);
        $remoteRecord = $this->record(['attendance_date' => '2026-03-09', 'check_in' => '08:00'])->assertOk()->json('data.id');

        $this->record(['employee_id' => $local, 'attendance_date' => '2026-03-09', 'check_in' => '08:00', 'check_out' => '16:00'], $managerUser)->assertOk();
        $this->record(['attendance_date' => '2026-03-08', 'check_in' => '08:00'], $managerUser)->assertStatus(422)
            ->assertJsonPath('reason', 'attendance_employee_not_found');
        $this->putJson("/api/hr/attendance-records/{$remoteRecord}", ['check_out' => '16:00', 'reason' => 'Out of scope'])->assertNotFound();
        $this->getJson('/api/hr/attendance-records')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.employee.id', $local);
        $this->assertSame([$managerEmployee, $local], array_column($this->getJson('/api/hr/attendance-records/employees')->assertOk()->json('data'), 'id'));
    }

    public function test_exempt_employees_and_policies_without_manual_attendance_are_refused(): void
    {
        $exempt = $this->manualEmployee(['attendance_mode' => 'exempt', 'shift_id' => null], 'exempt@example.test');
        $this->record(['employee_id' => $exempt, 'attendance_date' => '2026-03-09', 'check_in' => '08:00'])->assertStatus(422)
            ->assertJsonPath('reason', 'attendance_employee_exempt');

        $strict = $this->createPolicy(['code' => 'STRICT', 'allow_manual_attendance' => false]);
        [, $electronic] = $this->electronicEmployee(['attendance_policy_id' => $strict, 'effective_from' => '2026-01-01'], 'strict@example.test');
        $this->record(['employee_id' => $electronic, 'attendance_date' => '2026-03-09', 'check_in' => '08:00'])->assertStatus(422)
            ->assertJsonPath('reason', 'attendance_manual_not_allowed');

        [, $flexible] = $this->electronicEmployee(['effective_from' => '2026-01-01'], 'flex@example.test');
        $this->actingAsUser($this->hrManager)
            ->postJson('/api/hr/attendance-records/preview', ['employee_id' => $flexible, 'attendance_date' => '2026-03-09', 'check_in' => '08:00', 'check_out' => '16:00'])
            ->assertOk()->assertJsonPath('data.warnings', ['electronic_employee']);
    }

    public function test_missing_check_in_and_check_out_are_corrected_with_a_reason_and_history(): void
    {
        $id = $this->record(['attendance_date' => '2026-03-09', 'check_out' => '16:00', 'entry_reason' => 'forgot_check_in'])->assertOk()
            ->assertJsonPath('data.status', 'missing_check_in')
            ->assertJsonPath('data.attendance_status', 'missing_check_in')
            ->assertJsonPath('data.check_in', null)
            ->assertJsonPath('data.duration_minutes', null)
            ->json('data.id');

        $this->putJson("/api/hr/attendance-records/{$id}", ['check_in' => '08:00'])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->putJson("/api/hr/attendance-records/{$id}", ['check_in' => '08:00', 'reason' => 'Signed the paper register at 8'])->assertOk()
            ->assertJsonPath('data.attendance_status', 'present')
            ->assertJsonPath('data.worked_minutes', 480)
            ->assertJsonPath('data.is_corrected', true)
            ->assertJsonPath('data.updated_by.id', $this->hrManager->id);
        $this->putJson("/api/hr/attendance-records/{$id}", ['check_in' => '08:00', 'reason' => 'Nothing changes here'])->assertStatus(422)
            ->assertJsonPath('reason', 'attendance_no_changes');
        $this->putJson("/api/hr/attendance-records/{$id}", ['shift_id' => $this->createShift('LT', '10:00', '18:00'), 'reason' => 'Worked the late shift'])->assertOk()
            ->assertJsonPath('data.shift_overridden', true)
            ->assertJsonPath('data.late_minutes', 0)
            ->assertJsonPath('data.early_departure_minutes', 120)
            ->assertJsonPath('data.attendance_status', 'early_departure');

        $missingOut = $this->record(['attendance_date' => '2026-03-08', 'check_in' => '08:00', 'entry_reason' => 'forgot_check_out'])->assertOk()
            ->assertJsonPath('data.attendance_status', 'missing_check_out')->json('data.id');
        $this->putJson("/api/hr/attendance-records/{$missingOut}", ['check_out' => '16:00', 'reason' => 'Left with the team'])->assertOk()
            ->assertJsonPath('data.attendance_status', 'present');

        $history = $this->getJson("/api/hr/attendance-records/{$id}/history")->assertOk()->json('data.changes');
        $this->assertSame(['updated', 'check_in_corrected', 'created'], array_column($history, 'action'));
        $this->assertSame('Signed the paper register at 8', $history[1]['reason']);
        $this->assertSame(['check_in' => null, 'status' => 'missing_check_in', 'attendance_status' => 'missing_check_in', 'worked_minutes' => null],
            array_intersect_key($history[1]['old_values'], array_flip(['check_in', 'status', 'attendance_status', 'worked_minutes'])));
        $this->assertSame('2026-03-09 08:00', $history[1]['new_values']['check_in']);
        $this->assertSame($this->hrManager->id, $history[1]['changed_by']['id']);
        $this->assertSame('check_out_corrected', DB::table('hr_attendance_record_changes')->where('attendance_record_id', $missingOut)->orderByDesc('id')->value('action'));

        $audit = DB::table('hr_audit_logs')->where('auditable_type', 'hr_attendance_record')->where('auditable_id', $id)->orderBy('id')->get();
        $this->assertSame(['created', 'updated', 'updated'], $audit->pluck('event')->all());
        $this->assertSame('Signed the paper register at 8', json_decode($audit[1]->new_values, true)['reason']);
    }

    public function test_electronic_records_are_corrected_without_changing_their_method(): void
    {
        $this->at('2026-03-01 07:00');
        [$user, $employee] = $this->electronicEmployee();
        $this->at('2026-03-09 07:55');
        $this->punch($user, 'check-in', $this->near(10))->assertOk();
        $id = (int) DB::table('hr_attendance_records')->where('employee_id', $employee)->value('id');

        $this->at('2026-03-09 12:00');
        $this->actingAsUser($this->hrManager)->putJson("/api/hr/attendance-records/{$id}", ['check_out' => '11:00', 'reason' => 'Still checked in'])
            ->assertStatus(409)->assertJsonPath('reason', 'attendance_record_open');

        $this->at('2026-03-09 20:01');
        $this->artisan('hr:close-missed-checkouts')->assertSuccessful();
        $this->at('2026-03-10 12:00');
        $this->actingAsUser($this->hrManager);

        $this->putJson("/api/hr/attendance-records/{$id}", ['shift_id' => $this->createShift('LT', '10:00', '18:00'), 'reason' => 'Other shift'])
            ->assertStatus(422)->assertJsonPath('reason', 'attendance_electronic_shift_locked');
        $this->putJson("/api/hr/attendance-records/{$id}", ['check_in' => null, 'check_out' => '16:00', 'reason' => 'Remove check-in'])
            ->assertStatus(422)->assertJsonPath('reason', 'attendance_electronic_time_required');

        $this->putJson("/api/hr/attendance-records/{$id}", ['check_in' => '07:55', 'check_out' => '16:05', 'reason' => 'Forgot to check out'])->assertOk()
            ->assertJsonPath('data.attendance_method', 'electronic')
            ->assertJsonPath('data.check_in.verification', 'verified')
            ->assertJsonPath('data.check_out.verification', 'manual')
            ->assertJsonPath('data.needs_review', false)
            ->assertJsonPath('data.attendance_status', 'present')
            ->assertJsonPath('data.is_corrected', true);

        $row = DB::table('hr_attendance_records')->find($id);
        $this->assertNotNull($row->check_in_latitude);
        $this->assertSame('check_out_corrected', DB::table('hr_attendance_record_changes')->where('attendance_record_id', $id)->value('action'));
    }

    public function test_bulk_entry_saves_valid_entries_and_reports_the_others(): void
    {
        $second = $this->manualEmployee([], 'second@example.test');
        $this->actingAsUser($this->hrManager);

        $response = $this->postJson('/api/hr/attendance-records/bulk', [
            'entry_reason' => 'system_or_device_issue',
            'entries' => [
                ['employee_id' => $this->employeeId, 'attendance_date' => '2026-03-09', 'check_in' => '08:00', 'check_out' => '16:00'],
                ['employee_id' => $second, 'attendance_date' => '2026-03-09', 'check_in' => '08:20', 'check_out' => '16:00', 'notes' => 'Traffic'],
                ['employee_id' => $this->employeeId, 'attendance_date' => '2026-03-09', 'check_in' => '09:00'],
                ['employee_id' => $second, 'attendance_date' => '2026-03-11', 'check_in' => '08:00'],
            ],
        ])->assertOk();

        $response->assertJsonPath('data.created', 2)
            ->assertJsonPath('data.failed', 2)
            ->assertJsonPath('data.results.1.attendance_status', 'late')
            ->assertJsonPath('data.results.2.reason', 'attendance_record_exists')
            ->assertJsonPath('data.results.3.reason', 'attendance_date_in_future');
        $this->assertSame(['bulk', 'bulk'], DB::table('hr_attendance_records')->pluck('entry_source')->all());

        $this->postJson('/api/hr/attendance-records/bulk', ['entry_reason' => 'other', 'entries' => [['employee_id' => $second, 'attendance_date' => '2026-03-08']]])
            ->assertStatus(422)->assertJsonValidationErrors(['entry_reason_notes', 'entries.0.check_in']);
    }

    public function test_import_dry_run_reports_rows_and_commit_saves_the_valid_ones(): void
    {
        $number = DB::table('hr_employees')->where('id', $this->employeeId)->value('employee_number');
        $csv = implode("\n", [
            'employee_number,employee_name,attendance_date,check_in,check_out,shift_code,notes',
            "{$number},Ahmed,2026-03-09,08:15,16:00,,From register",
            "{$number},Ahmed,08/03/2026,8:00 AM,4:00 PM,,",
            "{$number},Ahmed,2026-03-07,,,,",
            "{$number},Ahmed,2026-03-09,09:00,,,",
            'X-404,Nobody,2026-03-09,08:00,16:00,,',
            "{$number},Ahmed,2026-03-05,25:00,,,",
            "{$number},Ahmed,2026-03-04,08:00,16:00,ZZ,",
        ]);
        $file = fn () => UploadedFile::fake()->createWithContent('attendance.csv', $csv);
        $this->actingAsUser($this->hrManager);

        $dry = $this->post('/api/hr/attendance-records/import', ['file' => $file(), 'dry_run' => 1, 'entry_reason' => 'manual_attendance_method'], ['Accept' => 'application/json'])
            ->assertOk();
        $dry->assertJsonPath('data.summary', ['total' => 7, 'valid' => 2, 'invalid' => 4, 'skipped' => 1, 'created' => 0, 'failed' => 0])
            ->assertJsonPath('data.rows.0.attendance_status', 'late')
            ->assertJsonPath('data.rows.1.attendance_date', '2026-03-08')
            ->assertJsonPath('data.rows.1.check_out', '16:00')
            ->assertJsonPath('data.rows.3.status', 'invalid')
            ->assertJsonPath('data.rows.4.field', 'employee_number')
            ->assertJsonPath('data.rows.5.field', 'check_in')
            ->assertJsonPath('data.rows.6.field', 'shift_code');
        $this->assertSame(0, DB::table('hr_attendance_records')->count());

        $this->post('/api/hr/attendance-records/import', ['file' => $file(), 'entry_reason' => 'manual_attendance_method'], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.summary.created', 2);
        $this->assertSame(['import', 'import'], DB::table('hr_attendance_records')->pluck('entry_source')->all());
        $this->assertSame('From register', DB::table('hr_attendance_records')->whereDate('attendance_date', '2026-03-09')->value('notes'));

        $this->post('/api/hr/attendance-records/import', ['file' => UploadedFile::fake()->createWithContent('a.csv', "name,date\nx,y"), 'entry_reason' => 'other', 'entry_reason_notes' => 'x'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('reason', 'attendance_import_invalid_template');
    }

    public function test_electronic_check_in_and_location_monitoring_ignore_manual_records(): void
    {
        $this->at('2026-03-01 07:00');
        [$user, $employee] = $this->electronicEmployee();
        $this->at('2026-03-10 07:55');
        $this->record(['employee_id' => $employee, 'attendance_date' => '2026-03-10', 'check_in' => '07:50', 'entry_reason' => 'system_or_device_issue'])->assertOk();

        $this->actingAsUser($user)->getJson('/api/hr/my-attendance')->assertOk()->assertJsonPath('data.hint.key', 'recorded_manually');
        $this->punch($user, 'check-in', $this->near(10))->assertStatus(422)->assertJsonPath('reason', 'attendance_recorded_manually');

        $this->actingAsUser($this->hrManager)->getJson('/api/hr/location-monitoring/sessions?date=2026-03-10')->assertOk()
            ->assertJsonCount(0, 'data.sessions');
    }

    public function test_central_list_combines_both_methods_with_filters_and_summary(): void
    {
        $this->at('2026-03-01 07:00');
        [$user, $electronic] = $this->electronicEmployee();
        $this->at('2026-03-09 07:55');
        $this->punch($user, 'check-in', $this->near(10))->assertOk();
        $this->at('2026-03-09 16:00');
        $this->punch($user, 'check-out', $this->near(10))->assertOk();
        $this->at('2026-03-10 12:00');

        $this->record(['attendance_date' => '2026-03-09', 'check_in' => '08:30', 'check_out' => '16:00'])->assertOk();
        $this->record(['attendance_date' => '2026-03-08', 'check_in' => '08:00'])->assertOk();

        $this->getJson('/api/hr/attendance-records')->assertOk()->assertJsonPath('total', 3)
            ->assertJsonPath('data.0.attendance_date', '2026-03-09');
        $this->getJson('/api/hr/attendance-records?method=electronic')->assertOk()->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.employee.id', $electronic)
            ->assertJsonPath('data.0.check_in.verification', 'verified');
        $this->getJson('/api/hr/attendance-records?attendance_status[]=late&attendance_status[]=missing_check_out')->assertOk()->assertJsonPath('total', 2);
        $this->getJson("/api/hr/attendance-records?employee_id={$this->employeeId}&from=2026-03-09&to=2026-03-09")->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/hr/attendance-records?branch_id='.$this->defaultBranchId().'&q=manual@')->assertOk()->assertJsonPath('total', 2);

        $this->getJson('/api/hr/attendance-records/summary?attendance_status[]=late')->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.by_method', ['electronic' => 1, 'manual' => 2])
            ->assertJsonPath('data.by_status.present', 1)
            ->assertJsonPath('data.by_status.late', 1)
            ->assertJsonPath('data.by_status.missing_check_out', 1)
            ->assertJsonPath('data.limits.can_edit', true)
            ->assertJsonPath('data.limits.today', '2026-03-10');

        $this->getJson("/api/hr/employees/{$this->employeeId}/attendance-records?method=manual")->assertOk()->assertJsonPath('total', 2);
    }

    private function manualEmployee(array $attendance = [], string $email = 'manual@example.test', array $employee = [], ?User $user = null): int
    {
        $user ??= $this->makeUser($email, 'teacher');
        $id = $this->createEmployee($user, $employee);
        $this->actingAsUser($this->hrManager)->putJson("/api/hr/employees/{$id}/attendance", $attendance + [
            'attendance_mode' => 'manual',
            'shift_id' => $this->shiftId,
            'attendance_policy_id' => $this->policyId,
            'grace_minutes' => 10,
            'effective_from' => '2026-01-01',
        ])->assertOk();

        return $id;
    }

    private function record(array $payload, ?User $as = null): TestResponse
    {
        return $this->actingAsUser($as ?? $this->hrManager)->postJson('/api/hr/attendance-records', $payload + [
            'employee_id' => $this->employeeId,
            'entry_reason' => 'manual_attendance_method',
        ]);
    }
}
