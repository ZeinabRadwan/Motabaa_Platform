<?php

namespace Tests\Feature\Hr;

use App\Models\Hr\LeaveType;
use App\Support\Hr\HrLeaveTypes;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * Attendance processing: daily status from the assigned shift, attendance records, rest days, holidays and
 * approved leave; absence detection; failed GPS verification and missing punches as exceptions; HR corrections
 * with reasons; late minutes after the grace period; payroll periods (boundaries, overnight shifts, approval,
 * locking, no double counting) and the payroll summary.
 */
class HrAttendanceProcessingApiTest extends HrTestCase
{
    use InteractsWithElectronicAttendance;

    private int $policyId;

    private int $employeeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpElectronicAttendance(); // Sunday 2026-03-01; shift 08:00-16:00 Sunday to Thursday
        $this->policyId = $this->createPolicy([
            'late_grace_minutes' => 15, 'late_counting' => 'excess_only',
            'early_leave_grace_minutes' => 0, 'early_leave_counting' => 'full',
        ]);
        $this->employeeId = $this->manualEmployee();
        HrLeaveTypes::ensureDefaults(self::CENTER);
    }

    public function test_days_are_classified_from_shift_records_rest_days_holidays_and_leave(): void
    {
        $this->at('2026-03-09 12:00'); // Monday
        $this->record('2026-03-01', '08:20', '16:00'); // Sunday: 5 minutes late (grace 15)
        $this->record('2026-03-02', '08:00', '15:30'); // Monday: left 30 minutes early
        $this->holiday('2026-03-03'); // Tuesday
        $this->recordLeave('2026-03-04', '2026-03-04'); // Wednesday
        // Thursday 5: nothing recorded. Friday 6 and Saturday 7: rest days.
        $this->record('2026-03-08', '08:40', '16:00'); // Sunday: 25 minutes late

        $this->process('2026-03-01', '2026-03-09')->assertOk()->assertJsonPath('data.processed', 9);

        $days = $this->days('2026-03-01', '2026-03-09');
        $this->assertSame([
            '2026-03-01' => 'late',
            '2026-03-02' => 'early_departure',
            '2026-03-03' => 'holiday',
            '2026-03-04' => 'on_leave',
            '2026-03-05' => 'absent',
            '2026-03-06' => 'rest_day',
            '2026-03-07' => 'rest_day',
            '2026-03-08' => 'late',
            '2026-03-09' => 'pending',
        ], array_map(fn ($day) => $day['status'], $days));
        $this->assertSame(5, $days['2026-03-01']['late_minutes']);
        $this->assertSame(460, $days['2026-03-01']['worked_minutes']);
        $this->assertSame(30, $days['2026-03-02']['early_departure_minutes']);
        $this->assertSame(25, $days['2026-03-08']['late_minutes']);
        $this->assertEquals(1, $days['2026-03-05']['absence_days']);
        $this->assertSame('no_attendance', $days['2026-03-05']['status_reason']);
        $this->assertEquals(0, $days['2026-03-03']['absence_days']);
        $this->assertSame('Annual leave', $days['2026-03-04']['leave']['leave_type']);

        $this->actingAsUser($this->hrManager)->getJson('/api/hr/attendance-days/summary?employee_id='.$this->employeeId.'&from=2026-03-01&to=2026-03-09')->assertOk()
            ->assertJsonPath('data.absence_days', 1)
            ->assertJsonPath('data.late_minutes', 30)
            ->assertJsonPath('data.early_departure_minutes', 30)
            ->assertJsonPath('data.statuses.rest_day', 2);
        $this->actingAsUser($this->hrManager)->getJson('/api/hr/attendance-days/employees?ids[]='.$this->employeeId)->assertOk()
            ->assertJsonPath('data.0.id', $this->employeeId);

        // Reprocessing is idempotent: one row per employee and date.
        $this->process('2026-03-01', '2026-03-09')->assertOk();
        $this->assertSame(9, DB::table('hr_attendance_days')->where('employee_id', $this->employeeId)->count());
    }

    public function test_later_holidays_and_leave_replace_a_detected_absence(): void
    {
        $this->at('2026-03-10 12:00');
        $this->process('2026-03-01', '2026-03-05');
        $this->assertSame('absent', $this->days('2026-03-02', '2026-03-03')['2026-03-02']['status']);

        $this->holiday('2026-03-02');
        $this->recordLeave('2026-03-03', '2026-03-03');

        $days = $this->days('2026-03-01', '2026-03-05');
        $this->assertSame('holiday', $days['2026-03-02']['status'], 'holiday changes reprocess the days already processed');
        $this->assertSame('on_leave', $days['2026-03-03']['status'], 'approved leave reprocesses its days');
        $this->assertEquals(0, $days['2026-03-02']['absence_days']);

        $this->actingAsUser($this->hrManager)->deleteJson('/api/hr/holidays/'.DB::table('hr_holidays')->value('id'))->assertOk();
        $this->assertSame('absent', $this->days('2026-03-02', '2026-03-02')['2026-03-02']['status']);
    }

    public function test_scheduled_command_detects_absence_of_the_last_days(): void
    {
        $this->at('2026-03-03 12:00'); // Tuesday
        $this->artisan('hr:process-attendance', ['--days' => 3])->assertSuccessful();

        $days = $this->days('2026-03-01', '2026-03-03');
        $this->assertSame(['absent', 'absent', 'pending'], array_values(array_map(fn ($day) => $day['status'], $days)));
    }

    public function test_failed_gps_verification_is_an_exception_not_an_absence(): void
    {
        [$user, $employee] = $this->electronicEmployee();
        $this->at('2026-03-01 07:55');
        $this->punch($user, 'check-in', $this->near(1000))->assertStatus(422)->assertJsonPath('reason', 'attendance_outside_radius');

        $this->at('2026-03-02 12:00');
        $this->process('2026-03-01', '2026-03-01', $employee);

        $day = $this->days('2026-03-01', '2026-03-01', $employee)['2026-03-01'];
        $this->assertSame('incomplete', $day['status']);
        $this->assertSame('gps_verification_failed', $day['exception_reasons'][0]['key']);
        $this->assertSame('pending', $day['review_status']);
        $this->assertEquals(0, $day['absence_days']);
    }

    public function test_missing_check_out_is_reviewed_and_corrected_with_a_reason(): void
    {
        $this->at('2026-03-03 12:00');
        $this->record('2026-03-01', '08:30', null);
        $this->process('2026-03-01', '2026-03-01');

        $day = $this->days('2026-03-01', '2026-03-01')['2026-03-01'];
        $this->assertSame('incomplete', $day['status']);
        $this->assertSame('missing_check_out', $day['exception_reasons'][0]['key']);
        $this->assertSame(15, $day['late_minutes']);
        $this->assertSame('pending', $day['review_status']);

        $this->actingAsUser($this->hrManager)->postJson("/api/hr/attendance-days/{$day['id']}/correct", ['status' => 'present'])
            ->assertStatus(422)->assertJsonValidationErrors('reason');
        $viewer = $this->makeUser('viewer@example.test', 'accountant', self::CENTER, ['access_hr-attendance-processing', 'access_hr-all-branches']);
        $this->actingAsUser($viewer)->postJson("/api/hr/attendance-days/{$day['id']}/correct", ['status' => 'present', 'reason' => 'Left at 16:00'])->assertForbidden();

        $this->actingAsUser($this->hrManager)->postJson("/api/hr/attendance-days/{$day['id']}/correct", [
            'status' => 'present', 'late_minutes' => 15, 'early_departure_minutes' => 0, 'reason' => 'Supervisor confirmed check-out at 16:00',
        ])->assertOk()
            ->assertJsonPath('data.status', 'late')
            ->assertJsonPath('data.computed_status', 'incomplete')
            ->assertJsonPath('data.review_status', 'resolved')
            ->assertJsonPath('data.override.reason', 'Supervisor confirmed check-out at 16:00')
            ->assertJsonPath('data.override.by.id', $this->hrManager->id);

        $this->process('2026-03-01', '2026-03-01');
        $this->actingAsUser($this->hrManager)->getJson("/api/hr/attendance-days/{$day['id']}")->assertOk()
            ->assertJsonPath('data.status', 'late')
            ->assertJsonPath('data.corrections.0.action', 'corrected')
            ->assertJsonPath('data.corrections.0.reason', 'Supervisor confirmed check-out at 16:00');
        $this->assertSame(1, DB::table('hr_audit_logs')->where('auditable_type', 'hr_attendance_day')->where('auditable_id', $day['id'])->count());

        $this->actingAsUser($this->hrManager)->postJson("/api/hr/attendance-days/{$day['id']}/reset", ['reason' => 'Entered on the wrong day'])->assertOk()
            ->assertJsonPath('data.status', 'incomplete')
            ->assertJsonPath('data.review_status', 'pending');
    }

    public function test_rest_days_and_holidays_can_only_be_marked_reviewed(): void
    {
        $this->at('2026-03-10 12:00');
        $this->holiday('2026-03-03');
        $this->process('2026-03-01', '2026-03-07');
        $days = $this->days('2026-03-01', '2026-03-07');

        $this->actingAsUser($this->hrManager)->postJson("/api/hr/attendance-days/{$days['2026-03-03']['id']}/correct", ['status' => 'absent', 'reason' => 'Should be absent'])
            ->assertStatus(422)->assertJsonPath('reason', 'attendance_day_not_correctable');
        $this->actingAsUser($this->hrManager)->postJson("/api/hr/attendance-days/{$days['2026-03-06']['id']}/correct", ['status' => 'absent', 'reason' => 'Should be absent'])
            ->assertStatus(422)->assertJsonPath('reason', 'attendance_day_not_correctable');
        $this->actingAsUser($this->hrManager)->postJson("/api/hr/attendance-days/{$days['2026-03-04']['id']}/correct", ['status' => 'excused', 'reason' => 'Medical appointment'])
            ->assertOk()->assertJsonPath('data.status', 'excused')->assertJsonPath('data.absence_days', 0);
    }

    public function test_payroll_period_boundaries_approval_locking_and_no_double_counting(): void
    {
        $night = $this->manualEmployee(['shift_id' => $this->createShift('NT', '22:00', '06:00')], 'night@example.test');
        $this->at('2026-03-10 12:00'); // Tuesday

        $this->record('2026-03-01', '08:20', '16:00'); // 5 late minutes
        $this->record('2026-03-02', '08:00', null); // missing check-out: exception
        $this->holiday('2026-03-03');
        $this->recordLeave('2026-03-04', '2026-03-04');
        // Thursday 5: absent.
        $this->record('2026-03-08', '08:30', '16:00'); // next period: 15 late minutes
        // Night shift of Thursday 5 ends on Friday 6 but belongs to Thursday's period.
        $this->record('2026-03-05', '22:20', '06:00', $night);

        $first = $this->period('March week 1', '2026-03-01', '2026-03-05')->assertOk()->json('data.id');
        $this->period('Overlap', '2026-03-05', '2026-03-12')->assertStatus(422)->assertJsonPath('reason', 'attendance_period_overlaps');
        $second = $this->period('March week 2', '2026-03-06', '2026-03-12')->assertOk()->json('data.id');

        $this->actingAsUser($this->hrManager)->patchJson("/api/hr/attendance-periods/{$first}/approve")->assertStatus(409)->assertJsonPath('reason', 'attendance_period_not_processed');
        $this->actingAsUser($this->hrManager)->postJson("/api/hr/attendance-periods/{$first}/process")->assertOk()
            ->assertJsonPath('data.status', 'processed')
            ->assertJsonPath('data.employees_count', 2);
        $this->actingAsUser($this->hrManager)->getJson("/api/hr/attendance-periods/{$first}/payroll")->assertStatus(409)->assertJsonPath('reason', 'attendance_period_not_approved');
        $this->actingAsUser($this->hrManager)->patchJson("/api/hr/attendance-periods/{$first}/approve")->assertStatus(409)
            ->assertJsonPath('reason', 'attendance_period_unresolved');

        $exception = $this->days('2026-03-02', '2026-03-02')['2026-03-02'];
        $this->actingAsUser($this->hrManager)->postJson("/api/hr/attendance-days/{$exception['id']}/correct", [
            'status' => 'present', 'early_departure_minutes' => 0, 'reason' => 'Forgot to sign out, confirmed by supervisor',
        ])->assertOk();

        $editor = $this->makeUser('editor@example.test', 'accountant', self::CENTER, ['edit_hr-attendance-processing', 'access_hr-all-branches']);
        $this->actingAsUser($editor)->patchJson("/api/hr/attendance-periods/{$first}/approve")->assertForbidden();
        $this->actingAsUser($this->hrManager)->patchJson("/api/hr/attendance-periods/{$first}/approve")->assertOk()->assertJsonPath('data.status', 'approved');

        $payroll = $this->actingAsUser($this->hrManager)->getJson("/api/hr/attendance-periods/{$first}/payroll")->assertOk();
        $rows = collect($payroll->json('data.employees'))->keyBy('employee.id');
        $day = $rows[$this->employeeId];
        $this->assertEquals(1, $day['absence_days']);
        $this->assertSame(5, $day['late_minutes'], 'the 15 late minutes of 8 March belong to the next period');
        $this->assertSame(2, $day['present_days']);
        $this->assertSame(1, $day['leave_days']);
        $this->assertSame(1, $day['holiday_days']);
        $this->assertSame(1, $day['exceptions_count']);
        $this->assertSame(0, $day['unresolved_exceptions']);
        $this->assertSame('2026-03-04', $day['leave_requests'][0]['start_date']);
        $nightRow = $rows[$night];
        $this->assertSame(5, $nightRow['late_minutes'], 'overnight shift counted on its start date');
        $this->assertEquals(3, $nightRow['absence_days'], 'Sunday, Monday and Wednesday; Tuesday is the holiday');
        $this->assertSame(1, $nightRow['holiday_days']);

        // Locked: no reprocessing, correction, attendance entry or leave on the approved days.
        $this->process('2026-03-01', '2026-03-09')->assertOk()->assertJsonPath('data.locked', 5);
        $this->assertSame($payroll->json('data.employees'), $this->actingAsUser($this->hrManager)->getJson("/api/hr/attendance-periods/{$first}/payroll")->json('data.employees'));
        $this->actingAsUser($this->hrManager)->postJson("/api/hr/attendance-days/{$exception['id']}/correct", ['status' => 'absent', 'reason' => 'Changed my mind'])
            ->assertStatus(409)->assertJsonPath('reason', 'attendance_day_locked');
        $this->record('2026-03-05', '08:00', '16:00', null, false)->assertStatus(422)->assertJsonPath('reason', 'attendance_day_locked');
        $this->actingAsUser($this->hrManager)->postJson('/api/hr/leave-requests', [
            'employee_id' => $this->employeeId, 'leave_type_id' => $this->leaveType('annual'), 'start_date' => '2026-03-05', 'end_date' => '2026-03-08',
            'reason' => 'Late request', 'record_as_approved' => true,
        ])->assertStatus(422)->assertJsonPath('reason', 'attendance_day_locked');
        $this->actingAsUser($this->hrManager)->deleteJson("/api/hr/attendance-periods/{$first}")->assertStatus(409);

        // The next period never counts the days of the approved one.
        $this->actingAsUser($this->hrManager)->postJson("/api/hr/attendance-periods/{$second}/process")->assertOk();
        $this->actingAsUser($this->hrManager)->patchJson("/api/hr/attendance-periods/{$second}/approve")->assertStatus(409)->assertJsonPath('reason', 'attendance_period_not_ended');
        $second = collect($this->actingAsUser($this->hrManager)->getJson("/api/hr/attendance-periods/{$second}/summaries")->json('data'))->keyBy('employee.id');
        $this->assertSame(15, $second[$this->employeeId]['late_minutes']);
        $this->assertSame(0, $second[$night]['late_minutes']);
        $this->assertSame(2, $second[$this->employeeId]['rest_days']);

        // Reopening (with a reason) unlocks the days.
        $this->actingAsUser($this->hrManager)->patchJson("/api/hr/attendance-periods/{$first}/reopen", [])->assertStatus(422);
        $this->actingAsUser($this->hrManager)->patchJson("/api/hr/attendance-periods/{$first}/reopen", ['reason' => 'Late medical certificate'])->assertOk()
            ->assertJsonPath('data.status', 'processed');
        $this->actingAsUser($this->hrManager)->getJson("/api/hr/attendance-periods/{$first}/payroll")->assertStatus(409);
        $this->assertSame(0, DB::table('hr_attendance_days')->whereNotNull('locked_at')->count());
    }

    private function manualEmployee(array $attendance = [], string $email = 'manual@example.test'): int
    {
        $id = $this->createEmployee($this->makeUser($email, 'teacher'));
        $this->actingAsUser($this->hrManager)->putJson("/api/hr/employees/{$id}/attendance", $attendance + [
            'attendance_mode' => 'manual',
            'shift_id' => $this->shiftId,
            'attendance_policy_id' => $this->policyId,
            'effective_from' => '2026-01-01',
        ])->assertOk();

        return $id;
    }

    private function record(string $date, ?string $in, ?string $out, ?int $employeeId = null, bool $assert = true): TestResponse
    {
        $response = $this->actingAsUser($this->hrManager)->postJson('/api/hr/attendance-records', array_filter([
            'employee_id' => $employeeId ?? $this->employeeId,
            'attendance_date' => $date,
            'check_in' => $in,
            'check_out' => $out,
            'entry_reason' => 'manual_attendance_method',
        ], fn ($value) => $value !== null));

        return $assert ? $response->assertOk() : $response;
    }

    private function process(string $from, string $to, ?int $employeeId = null): TestResponse
    {
        return $this->actingAsUser($this->hrManager)->postJson('/api/hr/attendance-days/process', [
            'from' => $from, 'to' => $to, 'employee_id' => $employeeId ?? $this->employeeId,
        ]);
    }

    /** @return array<string, array> attendance days keyed by date */
    private function days(string $from, string $to, ?int $employeeId = null): array
    {
        $rows = $this->actingAsUser($this->hrManager)
            ->getJson('/api/hr/attendance-days?options[itemsPerPage]=100&employee_id='.($employeeId ?? $this->employeeId)."&from={$from}&to={$to}")
            ->assertOk()
            ->json('data');

        return collect($rows)->keyBy('attendance_date')->sortKeys()->all();
    }

    private function holiday(string $date): void
    {
        $this->actingAsUser($this->hrManager)->postJson('/api/hr/holidays', [
            'name_ar' => 'عطلة', 'name_en' => 'Holiday', 'date_from' => $date, 'date_to' => $date, 'holiday_type' => 'official',
        ])->assertOk();
    }

    private function recordLeave(string $start, string $end): void
    {
        $this->actingAsUser($this->hrManager)->postJson('/api/hr/leave-requests', [
            'employee_id' => $this->employeeId, 'leave_type_id' => $this->leaveType('annual'),
            'start_date' => $start, 'end_date' => $end, 'reason' => 'Paper form', 'record_as_approved' => true,
        ])->assertOk();
    }

    private function period(string $name, string $start, string $end): TestResponse
    {
        return $this->actingAsUser($this->hrManager)->postJson('/api/hr/attendance-periods', [
            'name' => $name, 'period_start' => $start, 'period_end' => $end,
        ]);
    }

    private function leaveType(string $code): int
    {
        return (int) LeaveType::query()->where('center_id', self::CENTER)->where('code', $code)->value('id');
    }
}
