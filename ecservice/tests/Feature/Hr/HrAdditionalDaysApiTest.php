<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * Additional working days: drafts, edits, submission, manager then HR approval, rejection, cancellation, duplicate
 * and employment checks, audit history, filters and summary, and payroll: settlement with the period approval
 * (gross / 30 × days), carry-forward of approved unpaid days, no double payment and reopening.
 */
class HrAdditionalDaysApiTest extends HrTestCase
{
    use InteractsWithElectronicAttendance;

    // February 2026: Sunday 1st; the shift works Sunday to Thursday, Friday and Saturday are rest days.
    private const REST_DAY = '2026-02-06'; // Friday

    private const WORKING_DAY = '2026-02-05'; // Thursday

    private User $employeeUser;

    private User $managerUser;

    private User $hrApprover;

    private User $editor;

    private int $employeeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpElectronicAttendance(); // now: Sunday 2026-03-01
        $policyId = $this->createPolicy();

        $this->managerUser = $this->makeUser('manager@example.test', 'teacher');
        $managerId = $this->createEmployee($this->managerUser);
        $this->employeeUser = $this->makeUser('employee@example.test', 'teacher');
        $this->employeeId = $this->createEmployee($this->employeeUser, ['manager_id' => $managerId]);
        $this->actingAsUser($this->hrManager)->putJson("/api/hr/employees/{$this->employeeId}/attendance", [
            'attendance_mode' => 'manual', 'shift_id' => $this->shiftId, 'attendance_policy_id' => $policyId, 'effective_from' => '2026-01-01',
        ])->assertOk();
        $this->salary($this->employeeId, 6000, 2000, 1000); // gross 9000: 300 a day

        $this->hrApprover = $this->makeUser('hr-approver@example.test', 'accountant', self::CENTER, ['apply_hr-additional-days']);
        $this->editor = $this->makeUser('editor@example.test', 'accountant', self::CENTER, ['edit_hr-additional-days', 'access_hr-all-branches']);
    }

    public function test_add_edit_submit_and_manager_then_hr_approval_with_history(): void
    {
        $record = $this->add(self::REST_DAY)->assertOk()
            ->assertJsonPath('data.0.status', 'draft')
            ->assertJsonPath('data.0.day_type', 'rest')
            ->assertJsonPath('data.0.days', 1)
            ->assertJsonPath('data.0.created_by.id', $this->editor->id)
            ->assertJsonPath('data.0.estimated.amount', '300.00')
            ->assertJsonPath('data.0.can_submit', true)
            ->json('data.0');

        $this->actingAsUser($this->editor)->putJson("/api/hr/additional-days/{$record['id']}", ['days' => 0.5, 'reason' => 'Open day, morning only'])->assertOk()
            ->assertJsonPath('data.days', 0.5)
            ->assertJsonPath('data.estimated.amount', '150.00');

        $submitted = $this->actingAsUser($this->editor)->postJson("/api/hr/additional-days/{$record['id']}/submit")->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.submitted_by.id', $this->editor->id)
            ->assertJsonCount(2, 'data.approval.steps')
            ->assertJsonPath('data.approval.steps.0.approver_type', 'direct_manager')
            ->assertJsonPath('data.approval.steps.1.approver_type', 'permission');
        $approvalId = $submitted->json('data.approval.id');

        $this->actingAsUser($this->managerUser)->getJson('/api/hr/approvals?scope=awaiting_me')->assertOk()
            ->assertJsonPath('data.0.request_type', 'additional_work_day')
            ->assertJsonPath('data.0.subject.kind', 'additional_work_day')
            ->assertJsonPath('data.0.subject.work_date', self::REST_DAY)
            ->assertJsonPath('data.0.subject.days', 0.5);
        $this->actingAsUser($this->editor)->postJson("/api/hr/approvals/{$approvalId}/approve")->assertForbidden();
        $this->actingAsUser($this->employeeUser)->postJson("/api/hr/approvals/{$approvalId}/approve")->assertForbidden();
        $this->actingAsUser($this->hrApprover)->postJson("/api/hr/approvals/{$approvalId}/approve")->assertForbidden();

        $this->actingAsUser($this->managerUser)->postJson("/api/hr/approvals/{$approvalId}/approve", ['comment' => 'Confirmed'])->assertOk();
        $this->show($record['id'])->assertJsonPath('data.status', 'pending');
        $this->actingAsUser($this->hrApprover)->postJson("/api/hr/approvals/{$approvalId}/approve")->assertOk();

        $this->show($record['id'])
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by.id', $this->hrApprover->id)
            ->assertJsonPath('data.can_edit', false);
        $this->assertNotNull($this->show($record['id'])->json('data.approved_at'));

        $this->actingAsUser($this->editor)->putJson("/api/hr/additional-days/{$record['id']}", ['days' => 1])
            ->assertStatus(409)->assertJsonPath('reason', 'additional_day_not_editable');

        $history = $this->actingAsUser($this->editor)->getJson("/api/hr/additional-days/{$record['id']}/history")->assertOk();
        $audit = collect($history->json('data.audit'));
        $this->assertSame('created', $audit->first()['event']);
        $this->assertTrue($audit->contains(fn ($log) => ($log['old_values']['days'] ?? null) == 1 && ($log['new_values']['days'] ?? null) == 0.5), 'the edit is audited');
        $this->assertTrue($audit->contains(fn ($log) => ($log['new_values']['status'] ?? null) === 'approved'));
        $this->assertSame('Confirmed', $history->json('data.record.approval.steps.0.comment'));
    }

    public function test_duplicates_dates_employment_days_and_permissions_are_validated(): void
    {
        $this->add(self::REST_DAY)->assertOk();
        $this->add(self::REST_DAY)->assertStatus(409)->assertJsonPath('reason', 'additional_day_duplicate');
        $this->add('2026-03-06')->assertStatus(422)->assertJsonPath('reason', 'additional_day_future');
        $this->add(self::WORKING_DAY)->assertStatus(422)->assertJsonPath('reason', 'additional_day_scheduled_working');
        $this->add('2024-12-27')->assertStatus(422)->assertJsonPath('reason', 'additional_day_not_employed');
        $this->add('2026-02-13', ['days' => 2])->assertStatus(422)->assertJsonValidationErrors('days');
        $this->add('2026-02-13', ['days' => 0.25])->assertStatus(422)->assertJsonValidationErrors('days');
        $this->add('2026-02-13', ['reason' => ''])->assertStatus(422)->assertJsonValidationErrors('reason');

        // A holiday on a working day is an additional day.
        $this->actingAsUser($this->hrManager)->postJson('/api/hr/holidays', [
            'name_ar' => 'عطلة', 'name_en' => 'Holiday', 'date_from' => '2026-02-10', 'date_to' => '2026-02-10', 'holiday_type' => 'official',
        ])->assertOk();
        $this->add('2026-02-10')->assertOk()->assertJsonPath('data.0.day_type', 'holiday');

        // All or nothing for several employees.
        $other = $this->createEmployee($this->makeUser('other@example.test', 'teacher'));
        $this->add(self::REST_DAY, ['employee_ids' => [$other, $this->employeeId]])->assertStatus(409);
        $this->assertSame(0, DB::table('hr_additional_work_days')->where('employee_id', $other)->count());
        $this->add('2026-02-07', ['employee_ids' => [$other, $this->employeeId]])->assertOk()->assertJsonCount(2, 'data');

        // Nobody records their own additional days.
        $selfUser = $this->makeUser('self@example.test', 'accountant', self::CENTER, ['edit_hr-additional-days', 'access_hr-all-branches']);
        $selfId = $this->createEmployee($selfUser);
        $this->add('2026-02-13', ['employee_ids' => [$selfId]], $selfUser)->assertForbidden()->assertJsonPath('reason', 'additional_day_own_record');

        $viewer = $this->makeUser('viewer@example.test', 'accountant', self::CENTER, ['access_hr-additional-days', 'access_hr-all-branches']);
        $this->actingAsUser($viewer)->getJson('/api/hr/additional-days')->assertOk();
        $this->add('2026-02-13', [], $viewer)->assertForbidden();
        $this->actingAsUser($this->employeeUser)->getJson('/api/hr/additional-days')->assertForbidden();
        $this->actingAsUser($this->employeeUser)->getJson('/api/hr/additional-days/employees')->assertForbidden();
    }

    public function test_rejection_frees_the_date_and_editing_a_pending_record_restarts_its_approval(): void
    {
        $record = $this->add(self::REST_DAY, ['submit' => true])->assertOk()->assertJsonPath('data.0.status', 'pending')->json('data.0');
        $first = $record['approval']['id'];
        $this->actingAsUser($this->managerUser)->postJson("/api/hr/approvals/{$first}/approve")->assertOk();

        $edited = $this->actingAsUser($this->editor)->putJson("/api/hr/additional-days/{$record['id']}", ['reason' => 'Exam supervision, corrected'])->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.approval.steps.0.status', 'pending');
        $second = $edited->json('data.approval.id');
        $this->assertNotSame($first, $second);
        $this->assertSame('cancelled', DB::table('hr_approval_requests')->where('id', $first)->value('status'));

        $this->actingAsUser($this->managerUser)->postJson("/api/hr/approvals/{$second}/reject", ['comment' => 'Not requested by the school'])->assertOk();
        $this->show($record['id'])
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejected_by.id', $this->managerUser->id)
            ->assertJsonPath('data.rejection_reason', 'Not requested by the school');

        $this->add(self::REST_DAY)->assertOk();
        $this->assertSame(2, DB::table('hr_additional_work_days')->where('employee_id', $this->employeeId)->count(), 'the rejected record is kept');
    }

    public function test_cancellation_rules(): void
    {
        $draft = $this->add(self::REST_DAY)->json('data.0.id');
        $this->actingAsUser($this->editor)->postJson("/api/hr/additional-days/{$draft}/cancel", [])->assertStatus(422);
        $this->actingAsUser($this->editor)->postJson("/api/hr/additional-days/{$draft}/cancel", ['reason' => 'Entered by mistake'])->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancel_reason', 'Entered by mistake')
            ->assertJsonPath('data.cancelled_by.id', $this->editor->id);
        $this->actingAsUser($this->editor)->postJson("/api/hr/additional-days/{$draft}/cancel", ['reason' => 'Again please'])
            ->assertStatus(409)->assertJsonPath('reason', 'additional_day_not_cancellable');

        $pending = $this->add(self::REST_DAY, ['submit' => true])->json('data.0');
        $this->actingAsUser($this->editor)->postJson("/api/hr/additional-days/{$pending['id']}/cancel", ['reason' => 'Event postponed'])->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
        $this->assertSame('cancelled', DB::table('hr_approval_requests')->where('id', $pending['approval']['id'])->value('status'));

        $approved = $this->approved(self::REST_DAY);
        $this->actingAsUser($this->editor)->postJson("/api/hr/additional-days/{$approved}/cancel", ['reason' => 'Event postponed'])
            ->assertForbidden()->assertJsonPath('reason', 'additional_day_cancel_approved_not_allowed');
        $this->actingAsUser($this->hrManager)->postJson("/api/hr/additional-days/{$approved}/cancel", ['reason' => 'Event postponed'])->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(3, DB::table('hr_additional_work_days')->count(), 'records are never deleted');
    }

    public function test_payroll_settlement_carry_forward_no_double_payment_and_reopening(): void
    {
        $first = $this->approved(self::REST_DAY);
        $second = $this->approved('2026-02-13', 0.5);
        $noSalary = $this->createEmployee($this->makeUser('nosalary@example.test', 'teacher'));
        $unpaid = $this->approved(self::REST_DAY, 1, $noSalary);

        $february = $this->period('February', '2026-02-01', '2026-02-28');
        $this->show($first)->assertJsonPath('data.attendance_period.id', $february);
        $this->actingAsUser($this->hrManager)->postJson("/api/hr/attendance-periods/{$february}/process")->assertOk()
            ->assertJsonPath('data.totals.additional_days', 1.5)
            ->assertJsonPath('data.totals.additional_amount', 450)
            ->assertJsonPath('data.totals.additional_missing_salary', 1);
        $this->assertSame('approved', $this->show($first)->json('data.status'), 'processing only previews');

        $this->actingAsUser($this->hrManager)->patchJson("/api/hr/attendance-periods/{$february}/approve")->assertOk()
            ->assertJsonPath('data.totals.additional_records', 2)
            ->assertJsonPath('data.totals.additional_amount', 450);
        $this->show($first)
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.paid_period.id', $february)
            ->assertJsonPath('data.gross_salary', '9000.00')
            ->assertJsonPath('data.daily_rate', '300.00')
            ->assertJsonPath('data.amount', '300.00');
        $this->show($second)->assertJsonPath('data.amount', '150.00');
        $this->show($unpaid)->assertJsonPath('data.status', 'approved')->assertJsonPath('data.estimated', null);

        $payroll = $this->actingAsUser($this->hrManager)->getJson("/api/hr/attendance-periods/{$february}/payroll")->assertOk()
            ->assertJsonPath('data.additional_totals.records', 2)
            ->assertJsonPath('data.additional_totals.amount', 450)
            ->assertJsonCount(2, 'data.additional_work_days');
        $row = collect($payroll->json('data.employees'))->firstWhere('employee.id', $this->employeeId);
        $this->assertEquals(1.5, $row['additional_days']);
        $this->assertEquals(450, $row['additional_amount']);

        // Paid days can no longer change.
        $this->actingAsUser($this->hrManager)->postJson("/api/hr/additional-days/{$first}/cancel", ['reason' => 'Paid by mistake'])
            ->assertStatus(409)->assertJsonPath('reason', 'additional_day_paid');
        $this->actingAsUser($this->editor)->putJson("/api/hr/additional-days/{$first}", ['days' => 0.5])->assertStatus(409);

        // A day of February approved after February was paid is carried forward to March.
        $late = $this->approved('2026-02-20');
        $this->show($late)->assertJsonPath('data.carried_forward', true)->assertJsonPath('data.attendance_period.id', $february);
        $this->summary()->assertJsonPath('data.carried_forward.records', 2); // with the day skipped for missing salary

        $this->at('2026-04-02 09:00');
        $march = $this->period('March', '2026-03-01', '2026-03-31');
        $this->actingAsUser($this->hrManager)->postJson("/api/hr/attendance-periods/{$march}/process")->assertOk();
        $this->actingAsUser($this->hrManager)->patchJson("/api/hr/attendance-periods/{$march}/approve")->assertOk()
            ->assertJsonPath('data.totals.additional_records', 1)
            ->assertJsonPath('data.totals.additional_amount', 300);
        $march_payroll = $this->actingAsUser($this->hrManager)->getJson("/api/hr/attendance-periods/{$march}/payroll")->assertOk()
            ->assertJsonCount(1, 'data.additional_work_days')
            ->assertJsonPath('data.additional_work_days.0.id', $late)
            ->assertJsonPath('data.additional_work_days.0.carried_forward', true);
        $this->assertNotContains($first, collect($march_payroll->json('data.additional_work_days'))->pluck('id'), 'never paid twice');

        // Reopening February returns its days to approved, still reserved to February.
        $this->actingAsUser($this->hrManager)->patchJson("/api/hr/attendance-periods/{$february}/reopen", ['reason' => 'Correction of absences'])->assertOk();
        $this->show($first)->assertJsonPath('data.status', 'approved')->assertJsonPath('data.paid_period.id', $february)->assertJsonPath('data.amount', null);
        $april = $this->period('April', '2026-04-01', '2026-04-30');
        $this->actingAsUser($this->hrManager)->postJson("/api/hr/attendance-periods/{$april}/process")->assertOk()
            ->assertJsonPath('data.totals.additional_records', 0);
        $this->actingAsUser($this->hrManager)->postJson("/api/hr/attendance-periods/{$february}/process")->assertOk();
        $this->actingAsUser($this->hrManager)->patchJson("/api/hr/attendance-periods/{$february}/approve")->assertOk()
            ->assertJsonPath('data.totals.additional_records', 2)
            ->assertJsonPath('data.totals.additional_amount', 450);
        $this->assertSame(1, DB::table('hr_additional_work_days')->where('id', $first)->where('paid_period_id', $february)->where('status', 'paid')->count());

        // Once a salary exists, the skipped day is paid by the next period.
        $this->salary($noSalary, 3000, 0, 0);
        $this->actingAsUser($this->hrManager)->postJson("/api/hr/attendance-periods/{$april}/process")->assertOk()
            ->assertJsonPath('data.totals.additional_records', 1)
            ->assertJsonPath('data.totals.additional_amount', 100);
    }

    public function test_filters_bulk_submit_and_summary(): void
    {
        $other = $this->createEmployee($this->makeUser('other@example.test', 'teacher'));
        $draft = $this->add(self::REST_DAY)->json('data.0.id');
        $secondDraft = $this->add('2026-02-07', ['employee_ids' => [$other]])->json('data.0.id');
        $approved = $this->approved('2026-02-13');
        $rejected = $this->add('2026-02-14', ['submit' => true])->json('data.0');
        $this->actingAsUser($this->managerUser)->postJson('/api/hr/approvals/'.$rejected['approval']['id'].'/reject', ['comment' => 'No'])->assertOk();

        $this->assertSame([$draft], $this->ids('status=draft&employee_id='.$this->employeeId));
        $this->assertSame([$approved], $this->ids('status=approved'));
        $this->assertSame([$secondDraft], $this->ids("employee_id={$other}"));
        $this->assertSame([$approved, $draft], $this->ids('from=2026-02-06&to=2026-02-13&employee_id='.$this->employeeId));
        $this->assertCount(4, $this->ids('branch_id='.$this->defaultBranchId()));
        $this->assertSame([], $this->ids('branch_id=999'));
        $period = $this->period('February', '2026-02-01', '2026-02-28');
        $this->assertCount(4, $this->ids("attendance_period_id={$period}"));
        $this->actingAsUser($this->editor)->getJson('/api/hr/additional-days/periods')->assertOk()
            ->assertJsonPath('data.0.value', $period)
            ->assertJsonPath('data.0.status', 'draft');

        $this->actingAsUser($this->editor)->postJson('/api/hr/additional-days/submit', ['ids' => [$draft, $secondDraft]])->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.1.status', 'pending');
        $this->actingAsUser($this->editor)->postJson('/api/hr/additional-days/submit', ['ids' => [$approved]])
            ->assertStatus(409)->assertJsonPath('reason', 'additional_day_not_submittable');

        $this->summary()
            ->assertJsonPath('data.draft.records', 0)
            ->assertJsonPath('data.pending.records', 2)
            ->assertJsonPath('data.approved.records', 1)
            ->assertJsonPath('data.approved.days', 1)
            ->assertJsonPath('data.rejected.records', 1)
            ->assertJsonPath('data.paid.records', 0)
            ->assertJsonPath('data.paid.amount', 0);
        $this->summary('employee_id='.$other)->assertJsonPath('data.pending.records', 1)->assertJsonPath('data.approved.records', 0);

        $this->actingAsUser($this->hrManager)->getJson('/api/hr/lookups')->assertOk()
            ->assertJsonFragment(['value' => 'paid', 'title' => 'Paid']);
    }

    private function add(string $date, array $overrides = [], ?User $as = null): TestResponse
    {
        return $this->actingAsUser($as ?? $this->editor)->postJson('/api/hr/additional-days', $overrides + [
            'employee_ids' => [$this->employeeId],
            'work_date' => $date,
            'days' => 1,
            'reason' => 'Exam supervision',
        ]);
    }

    /** An approved record (manager when the employee has one, then HR). */
    private function approved(string $date, float $days = 1, ?int $employeeId = null): int
    {
        $record = $this->add($date, ['employee_ids' => [$employeeId ?? $this->employeeId], 'days' => $days, 'submit' => true])->assertOk()->json('data.0');
        foreach ($record['approval']['steps'] as $step) {
            $approver = $step['approver_type'] === 'direct_manager' ? $this->managerUser : $this->hrApprover;
            $this->actingAsUser($approver)->postJson('/api/hr/approvals/'.$record['approval']['id'].'/approve')->assertOk();
        }
        $this->show($record['id'])->assertJsonPath('data.status', 'approved');

        return $record['id'];
    }

    private function show(int $id): TestResponse
    {
        return $this->actingAsUser($this->hrManager)->getJson("/api/hr/additional-days/{$id}")->assertOk();
    }

    private function summary(string $query = ''): TestResponse
    {
        return $this->actingAsUser($this->hrManager)->getJson('/api/hr/additional-days/summary?'.$query)->assertOk();
    }

    /** @return int[] */
    private function ids(string $query): array
    {
        return collect($this->actingAsUser($this->hrManager)->getJson('/api/hr/additional-days?options[itemsPerPage]=50&'.$query)->assertOk()->json('data'))
            ->pluck('id')->all();
    }

    private function period(string $name, string $start, string $end): int
    {
        return (int) $this->actingAsUser($this->hrManager)->postJson('/api/hr/attendance-periods', [
            'name' => $name, 'period_start' => $start, 'period_end' => $end,
        ])->assertOk()->json('data.id');
    }

    private function salary(int $employeeId, float $basic, float $housing, float $transportation): void
    {
        $this->actingAsUser($this->hrManager)->postJson("/api/hr/employees/{$employeeId}/salaries", [
            'effective_from' => '2025-01-01',
            'basic_salary' => $basic,
            'housing_allowance' => $housing,
            'transportation_allowance' => $transportation,
            'reason' => 'Initial package',
        ])->assertOk();
    }
}
