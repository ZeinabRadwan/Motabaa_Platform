<?php

namespace Tests\Feature\Hr;

use App\Models\Hr\ApprovalRequest;
use App\Models\Hr\Employee;
use App\Support\Hr\Approvals\Approvable;
use App\Support\Hr\Approvals\ApprovalService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class HrApprovalApiTest extends HrTestCase
{
    private Employee $employee;

    private \App\Models\User $employeeUser;

    private \App\Models\User $managerUser;

    private \App\Models\User $approverUser;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('hr_test_approvables', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('pending');
        });

        $this->managerUser = $this->makeUser('manager@example.test', 'teacher');
        $this->employeeUser = $this->makeUser('employee@example.test', 'teacher');
        $this->approverUser = $this->makeUser('approver@example.test', 'accountant', self::CENTER, ['apply_hr-approvals']);

        $managerId = $this->createEmployee($this->managerUser);
        $this->employee = Employee::query()->findOrFail($this->createEmployee($this->employeeUser, ['manager_id' => $managerId]));

        $this->actingAsUser($this->hrManager)->putJson('/api/hr/approval-workflows', [
            'request_type' => 'leave_request',
            'name_ar' => 'اعتماد الإجازات',
            'steps' => [
                ['approver_type' => 'direct_manager'],
                ['approver_type' => 'permission', 'approver_value' => 'apply_hr-approvals'],
            ],
        ])->assertOk()
            ->assertJsonCount(2, 'data.steps')
            ->assertJsonPath('data.steps.0.approver_value_label', null)
            ->assertJsonPath('data.steps.1.approver_value_label', 'Approve HR requests (default approver)');
    }

    private function submit(): ApprovalRequest
    {
        return ApprovalService::submit(
            HrTestApprovable::create(),
            'leave_request',
            self::CENTER,
            $this->employeeUser,
            $this->employee
        );
    }

    public function test_steps_run_in_order_and_the_record_is_notified_when_done(): void
    {
        $request = $this->submit();

        $this->actingAsUser($this->employeeUser)
            ->postJson("/api/hr/approvals/{$request->id}/approve")
            ->assertForbidden()->assertJsonPath('reason', 'approval_not_allowed');
        $this->actingAsUser($this->approverUser)
            ->postJson("/api/hr/approvals/{$request->id}/approve")
            ->assertForbidden();

        $inbox = $this->actingAsUser($this->managerUser)->getJson('/api/hr/approvals?scope=awaiting_me')->assertOk();
        $this->assertSame([$request->id], array_column($inbox->json('data'), 'id'));
        $this->assertTrue($inbox->json('data.0.can_act'));

        $this->postJson("/api/hr/approvals/{$request->id}/approve", ['comment' => 'موافق'])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.current_step', 2)
            ->assertJsonPath('data.steps.0.comment', 'موافق');

        $this->actingAsUser($this->approverUser)
            ->postJson("/api/hr/approvals/{$request->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertSame('approved', HrTestApprovable::query()->findOrFail($request->approvable_id)->status);

        $this->postJson("/api/hr/approvals/{$request->id}/reject")
            ->assertForbidden()->assertJsonPath('reason', 'approval_not_pending');
    }

    public function test_rejection_ends_the_request_and_requester_can_cancel_pending_ones(): void
    {
        $rejected = $this->submit();
        $this->actingAsUser($this->managerUser)
            ->postJson("/api/hr/approvals/{$rejected->id}/reject", ['comment' => 'ضغط عمل'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.steps.1.status', 'skipped');
        $this->assertSame('rejected', HrTestApprovable::query()->findOrFail($rejected->approvable_id)->status);

        $cancelled = $this->submit();
        $this->postJson("/api/hr/approvals/{$cancelled->id}/cancel")->assertForbidden();
        $this->actingAsUser($this->employeeUser)
            ->postJson("/api/hr/approvals/{$cancelled->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $mine = $this->getJson('/api/hr/approvals?scope=mine')->assertOk()->json('data');
        $this->assertEqualsCanonicalizing([$rejected->id, $cancelled->id], array_column($mine, 'id'));
        $this->getJson('/api/hr/approvals?scope=all')->assertForbidden();
    }

    public function test_without_a_workflow_requests_go_to_holders_of_the_default_approver_permission(): void
    {
        $request = ApprovalService::submit(HrTestApprovable::create(), 'manual_attendance', self::CENTER, $this->employeeUser, $this->employee);

        $this->assertSame('permission', $request->steps->first()->approver_type);
        $this->assertSame(ApprovalService::FALLBACK_PERMISSION, $request->steps->first()->approver_value);

        $this->actingAsUser($this->approverUser)
            ->postJson("/api/hr/approvals/{$request->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_workflow_changes_do_not_rewrite_submitted_requests(): void
    {
        $request = $this->submit();

        $this->actingAsUser($this->hrManager)->putJson('/api/hr/approval-workflows', [
            'request_type' => 'leave_request',
            'name_ar' => 'اعتماد الإجازات',
            'steps' => [['approver_type' => 'user', 'approver_value' => (string) $this->hrManager->id]],
        ])->assertOk()->assertJsonPath('data.steps.0.approver_value_label', $this->hrManager->name);

        $this->assertSame(
            ['direct_manager', 'permission'],
            $request->fresh()->steps->pluck('approver_type')->all()
        );
    }
}

class HrTestApprovable extends Model implements Approvable
{
    public $timestamps = false;

    protected $table = 'hr_test_approvables';

    protected $guarded = [];

    public function approvalResolved(ApprovalRequest $request): void
    {
        $this->update(['status' => $request->status]);
    }
}
