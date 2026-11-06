<?php

namespace Tests\Feature\Hr;

use App\Models\Hr\LeaveType;
use App\Models\User;
use App\Support\Hr\HrLeaveTypes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/**
 * Leave management: configurable types, requests by employees and HR, manager then HR approval, balances
 * (reservation, use, release, restore, adjustments), rest days and holidays excluded from leave days, supporting
 * documents, overlaps, cancellation, leave history and approval history.
 */
class HrLeaveApiTest extends HrTestCase
{
    use InteractsWithElectronicAttendance;

    private User $employeeUser;

    private User $managerUser;

    private User $hrApprover;

    private int $employeeId;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->setUpElectronicAttendance(); // Sunday 2026-03-01; shift 08:00-16:00 Sunday to Thursday
        $policyId = $this->createPolicy(['late_counting' => 'excess_only']);

        $this->managerUser = $this->makeUser('manager@example.test', 'teacher');
        $managerId = $this->createEmployee($this->managerUser);
        $this->employeeUser = $this->makeUser('employee@example.test', 'teacher', self::CENTER, ['apply_hr-self-leave']);
        $this->employeeId = $this->createEmployee($this->employeeUser, ['manager_id' => $managerId]);
        $this->actingAsUser($this->hrManager)->putJson("/api/hr/employees/{$this->employeeId}/attendance", [
            'attendance_mode' => 'manual', 'shift_id' => $this->shiftId, 'attendance_policy_id' => $policyId, 'effective_from' => '2026-01-01',
        ])->assertOk();
        $this->hrApprover = $this->makeUser('hr-approver@example.test', 'accountant', self::CENTER, ['apply_hr-leave']);

        HrLeaveTypes::ensureDefaults(self::CENTER);
    }

    public function test_employee_request_excludes_rest_days_and_holidays_and_the_manager_approves(): void
    {
        $this->holiday('2026-03-10');

        // Sunday 8 to Saturday 14: five working days, Tuesday 10 is a holiday, Friday and Saturday are rest days.
        $this->actingAsUser($this->employeeUser)->postJson('/api/hr/my-leave/preview', [
            'leave_type_id' => $this->type('annual'), 'start_date' => '2026-03-08', 'end_date' => '2026-03-14',
        ])->assertOk()
            ->assertJsonPath('data.days', 4)
            ->assertJsonPath('data.calendar_days', 7)
            ->assertJsonPath('data.dates', ['2026-03-08', '2026-03-09', '2026-03-11', '2026-03-12'])
            ->assertJsonPath('data.balance.available', 21)
            ->assertJsonPath('data.balance.after', 17)
            ->assertJsonPath('data.valid', true);

        $leave = $this->requestLeave('annual', '2026-03-08', '2026-03-14')->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.days', 4)
            ->assertJsonPath('data.source', 'self')
            ->assertJsonCount(1, 'data.approval.steps')
            ->assertJsonPath('data.approval.steps.0.approver_type', 'direct_manager');
        $this->assertBalance('annual', used: 0, pending: 4, available: 17);

        $this->actingAsUser($this->employeeUser)->postJson('/api/hr/approvals/'.$leave->json('data.approval.id').'/approve')->assertForbidden();
        $this->actingAsUser($this->managerUser)->getJson('/api/hr/approvals?scope=awaiting_me')->assertOk()
            ->assertJsonPath('data.0.subject.kind', 'leave_request')
            ->assertJsonPath('data.0.subject.days', 4);
        $this->approve($this->managerUser, $leave);

        $this->actingAsUser($this->employeeUser)->getJson('/api/hr/my-leave/requests/'.$leave->json('data.id'))->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.decided_by.id', $this->managerUser->id)
            ->assertJsonPath('data.approval.steps.0.status', 'approved')
            ->assertJsonPath('data.approval.steps.0.comment', 'Approved');
        $this->assertBalance('annual', used: 4, pending: 0, available: 17);

        $overview = $this->actingAsUser($this->employeeUser)->getJson('/api/hr/my-leave')->assertOk()->assertJsonPath('data.eligible', true);
        $annual = collect($overview->json('data.balances'))->firstWhere('leave_type.code', 'annual');
        $this->assertEquals(4, $annual['used_days']);
        $this->assertSame(1, count($this->actingAsUser($this->employeeUser)->getJson('/api/hr/my-leave/requests')->json('data')), 'leave history');
    }

    public function test_type_requiring_hr_needs_a_document_and_both_approvals(): void
    {
        $this->requestLeave('sick', '2026-03-08', '2026-03-10')->assertStatus(422)->assertJsonPath('reason', 'leave_attachment_required');

        $leave = $this->requestLeave('sick', '2026-03-08', '2026-03-10', [UploadedFile::fake()->create('report.pdf', 120, 'application/pdf')])
            ->assertOk()
            ->assertJsonPath('data.days', 3, 'sick leave counts calendar days')
            ->assertJsonCount(1, 'data.attachments')
            ->assertJsonCount(2, 'data.approval.steps')
            ->assertJsonPath('data.approval.steps.1.approver_type', 'permission');

        $this->actingAsUser($this->hrApprover)->postJson('/api/hr/approvals/'.$leave->json('data.approval.id').'/approve')->assertForbidden();
        $this->actingAsUser($this->managerUser)->getJson('/api/hr/approvals?scope=awaiting_me')->assertOk()
            ->assertJsonPath('data.0.subject.leave_request_id', $leave->json('data.id'))
            ->assertJsonPath('data.0.subject.attachments.0.name', 'report.pdf')
            ->assertJsonPath('data.0.subject.attachments.0.mime_type', 'application/pdf');
        $this->approve($this->managerUser, $leave)->assertJsonPath('data.status', 'pending');
        $this->assertSame('pending', $this->leaveStatus($leave));
        $this->approve($this->hrApprover, $leave)->assertJsonPath('data.status', 'approved');
        $this->assertSame('approved', $this->leaveStatus($leave));
        $this->assertBalance('sick', used: 3, pending: 0, available: 27);

        $attachment = '/api/hr/leave-requests/'.$leave->json('data.id').'/attachments/'.$leave->json('data.attachments.0.id');
        $this->actingAsUser($this->managerUser)->get($attachment)->assertOk();
        $this->actingAsUser($this->makeUser('other@example.test', 'teacher'))->get($attachment)->assertForbidden();
    }

    public function test_rejection_and_cancellation_return_the_days(): void
    {
        $rejected = $this->requestLeave('annual', '2026-03-08', '2026-03-09')->assertOk();
        $this->actingAsUser($this->managerUser)->postJson('/api/hr/approvals/'.$rejected->json('data.approval.id').'/reject', ['comment' => 'Busy week'])->assertOk();
        $this->assertSame('rejected', $this->leaveStatus($rejected));
        $this->assertBalance('annual', used: 0, pending: 0, available: 21);

        $pending = $this->requestLeave('annual', '2026-03-15', '2026-03-16')->assertOk();
        $this->actingAsUser($this->employeeUser)->postJson('/api/hr/my-leave/requests/'.$pending->json('data.id').'/cancel', ['reason' => 'Plans changed'])->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancel_reason', 'Plans changed');
        $this->assertSame('cancelled', DB::table('hr_approval_requests')->where('id', $pending->json('data.approval.id'))->value('status'));
        $this->assertBalance('annual', used: 0, pending: 0, available: 21);

        $approved = $this->requestLeave('annual', '2026-03-22', '2026-03-24')->assertOk();
        $this->approve($this->managerUser, $approved);
        $this->assertBalance('annual', used: 3, pending: 0, available: 18);
        $this->actingAsUser($this->employeeUser)->postJson('/api/hr/my-leave/requests/'.$approved->json('data.id').'/cancel', ['reason' => 'Plans changed'])
            ->assertStatus(409)->assertJsonPath('reason', 'leave_not_cancellable');

        $editor = $this->makeUser('editor@example.test', 'accountant', self::CENTER, ['edit_hr-leave', 'access_hr-all-branches']);
        $this->actingAsUser($editor)->postJson('/api/hr/leave-requests/'.$approved->json('data.id').'/cancel', ['reason' => 'Called back to work'])
            ->assertForbidden()->assertJsonPath('reason', 'leave_cancel_approved_not_allowed');
        $this->actingAsUser($this->hrManager)->postJson('/api/hr/leave-requests/'.$approved->json('data.id').'/cancel', ['reason' => 'Called back to work'])->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
        $this->assertBalance('annual', used: 0, pending: 0, available: 21);

        $kinds = DB::table('hr_leave_balance_transactions')->orderBy('id')->pluck('kind')->all();
        $this->assertSame(['entitlement', 'reserve', 'release', 'reserve', 'release', 'reserve', 'use', 'restore'], $kinds);
    }

    public function test_invalid_requests_are_refused(): void
    {
        $this->requestLeave('annual', '2026-03-15', '2026-04-30')->assertStatus(422)
            ->assertJsonPath('reason', 'leave_insufficient_balance');
        $this->requestLeave('annual', '2026-12-27', '2027-01-03')->assertStatus(422)->assertJsonPath('reason', 'leave_crosses_year');
        $this->requestLeave('annual', '2026-03-13', '2026-03-14')->assertStatus(422)->assertJsonPath('reason', 'leave_no_working_days');
        $this->requestLeave('marriage', '2026-03-08', '2026-03-19', [UploadedFile::fake()->create('contract.pdf', 50, 'application/pdf')])
            ->assertStatus(422)->assertJsonPath('reason', 'leave_exceeds_max_days');

        $this->requestLeave('annual', '2026-03-08', '2026-03-10')->assertOk();
        $this->requestLeave('unpaid', '2026-03-10', '2026-03-11')->assertStatus(422)->assertJsonPath('reason', 'leave_overlaps');

        $this->actingAsUser($this->hrManager)->putJson('/api/hr/leave-types/'.$this->type('hajj'), [
            'code' => 'hajj', 'name_ar' => 'إجازة حج', 'balance_mode' => 'none', 'day_counting' => 'calendar_days', 'allow_self_request' => false,
        ])->assertOk();
        $this->requestLeave('hajj', '2026-05-20', '2026-05-25')->assertStatus(422)->assertJsonPath('reason', 'leave_type_not_self_service');

        $this->actingAsUser($this->makeUser('nobody@example.test', 'teacher'))->getJson('/api/hr/my-leave')->assertForbidden();
        $this->actingAsUser($this->makeUser('not-employee@example.test', 'teacher', self::CENTER, ['apply_hr-self-leave']))
            ->postJson('/api/hr/my-leave/requests', ['leave_type_id' => $this->type('annual'), 'start_date' => '2026-03-08', 'end_date' => '2026-03-08', 'reason' => 'Trip'])
            ->assertForbidden()->assertJsonPath('reason', 'leave_not_employee');
    }

    public function test_hr_requests_records_and_adjusts_balances(): void
    {
        $this->actingAsUser($this->hrManager)->postJson('/api/hr/leave-requests', [
            'employee_id' => $this->employeeId, 'leave_type_id' => $this->type('annual'),
            'start_date' => '2026-03-08', 'end_date' => '2026-03-09', 'reason' => 'Paper form', 'record_as_approved' => true,
        ])->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.source', 'recorded')
            ->assertJsonPath('data.approval', null);
        $this->assertBalance('annual', used: 2, pending: 0, available: 19);

        $editor = $this->makeUser('editor@example.test', 'accountant', self::CENTER, ['edit_hr-leave', 'access_hr-all-branches']);
        $this->actingAsUser($editor)->postJson('/api/hr/leave-requests', [
            'employee_id' => $this->employeeId, 'leave_type_id' => $this->type('annual'),
            'start_date' => '2026-03-15', 'end_date' => '2026-03-15', 'reason' => 'Paper form', 'record_as_approved' => true,
        ])->assertForbidden();
        $this->actingAsUser($editor)->postJson('/api/hr/leave-requests', [
            'employee_id' => $this->employeeId, 'leave_type_id' => $this->type('annual'),
            'start_date' => '2026-03-15', 'end_date' => '2026-03-15', 'reason' => 'On behalf',
        ])->assertOk()->assertJsonPath('data.source', 'hr')->assertJsonPath('data.status', 'pending');

        $adjust = ['employee_id' => $this->employeeId, 'leave_type_id' => $this->type('annual'), 'year' => 2026];
        $this->actingAsUser($this->hrManager)->postJson('/api/hr/leave-balances/adjust', $adjust + ['days' => 2.5, 'reason' => 'Overtime compensation'])->assertOk();
        $this->assertBalance('annual', used: 2, pending: 1, available: 20.5);
        $this->actingAsUser($this->hrManager)->postJson('/api/hr/leave-balances/adjust', $adjust + ['days' => -30, 'reason' => 'Correction of entry'])
            ->assertStatus(422)->assertJsonPath('reason', 'leave_balance_negative');

        $balanceId = DB::table('hr_leave_balances')->where('employee_id', $this->employeeId)->where('leave_type_id', $this->type('annual'))->value('id');
        $this->actingAsUser($this->hrManager)->getJson("/api/hr/leave-balances/{$balanceId}/transactions")->assertOk()
            ->assertJsonFragment(['reason' => 'Overtime compensation']);
        $this->actingAsUser($this->hrManager)->getJson('/api/hr/leave-requests?employee_id='.$this->employeeId)->assertOk()->assertJsonPath('total', 2);

        $this->actingAsUser($editor)->getJson('/api/hr/leave-requests/employees?ids[]='.$this->employeeId)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->employeeId)
            ->assertJsonPath('data.0.name', $this->employeeUser->name);
        $this->assertGreaterThanOrEqual(2, count($this->actingAsUser($editor)->getJson('/api/hr/leave-requests/employees')->assertOk()->json('data')));
        $this->actingAsUser($this->employeeUser)->getJson('/api/hr/leave-requests/employees')->assertForbidden();
    }

    public function test_leave_types_are_configurable(): void
    {
        $this->actingAsUser($this->hrManager)->getJson('/api/hr/leave-types')->assertOk()->assertJsonFragment(['code' => 'annual']);

        $id = $this->actingAsUser($this->hrManager)->postJson('/api/hr/leave-types', [
            'code' => 'exam', 'name_ar' => 'إجازة اختبارات', 'name_en' => 'Exam leave', 'category' => 'other',
            'balance_mode' => 'fixed', 'days_per_year' => 5, 'day_counting' => 'working_days', 'requires_hr_approval' => true,
        ])->assertOk()->json('data.id');
        $this->actingAsUser($this->hrManager)->postJson('/api/hr/leave-types', [
            'code' => 'exam', 'name_ar' => 'مكرر', 'balance_mode' => 'none', 'day_counting' => 'working_days',
        ])->assertStatus(422)->assertJsonPath('reason', 'leave_type_code_taken');

        $this->requestLeave('exam', '2026-03-08', '2026-03-09')->assertOk()->assertJsonCount(2, 'data.approval.steps');
        $this->assertBalance('exam', used: 0, pending: 2, available: 3);

        $this->actingAsUser($this->hrManager)->deleteJson("/api/hr/leave-types/{$id}")->assertStatus(409)->assertJsonPath('reason', 'leave_type_in_use');
        $this->actingAsUser($this->hrManager)->deleteJson('/api/hr/leave-types/'.$this->type('annual'))->assertStatus(409)->assertJsonPath('reason', 'leave_type_system');
    }

    private function requestLeave(string $type, string $start, string $end, array $attachments = []): TestResponse
    {
        return $this->actingAsUser($this->employeeUser)->post('/api/hr/my-leave/requests', [
            'leave_type_id' => $this->type($type), 'start_date' => $start, 'end_date' => $end, 'reason' => 'Family matters',
            'attachments' => $attachments,
        ], ['Accept' => 'application/json']);
    }

    private function approve(User $user, TestResponse $leave): TestResponse
    {
        return $this->actingAsUser($user)->postJson('/api/hr/approvals/'.$leave->json('data.approval.id').'/approve', ['comment' => 'Approved'])->assertOk();
    }

    private function leaveStatus(TestResponse $leave): string
    {
        return (string) DB::table('hr_leave_requests')->where('id', $leave->json('data.id'))->value('status');
    }

    private function type(string $code): int
    {
        return (int) LeaveType::query()->where('center_id', self::CENTER)->where('code', $code)->value('id');
    }

    private function holiday(string $date): void
    {
        $this->actingAsUser($this->hrManager)->postJson('/api/hr/holidays', [
            'name_ar' => 'عطلة', 'name_en' => 'Holiday', 'date_from' => $date, 'date_to' => $date, 'holiday_type' => 'official',
        ])->assertOk();
    }

    private function assertBalance(string $type, float $used, float $pending, float $available): void
    {
        $balance = DB::table('hr_leave_balances')->where('employee_id', $this->employeeId)->where('leave_type_id', $this->type($type))->where('year', 2026)->first();
        $this->assertNotNull($balance);
        $this->assertEquals($used, (float) $balance->used_days, 'used days');
        $this->assertEquals($pending, (float) $balance->pending_days, 'pending days');
        $total = (float) $balance->entitled_days + (float) $balance->carried_over_days + (float) $balance->adjusted_days;
        $this->assertEquals($available, round($total - (float) $balance->used_days - (float) $balance->pending_days, 2), 'available days');
    }
}
