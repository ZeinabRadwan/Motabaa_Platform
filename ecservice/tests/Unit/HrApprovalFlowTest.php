<?php

namespace Tests\Unit;

use App\Support\Hr\Approvals\ApprovalFlow;
use App\Support\Hr\HrException;
use Tests\TestCase;

class HrApprovalFlowTest extends TestCase
{
    public function test_approving_a_middle_step_moves_to_the_next_pending_step(): void
    {
        $result = ApprovalFlow::apply('pending', 1, [1 => 'pending', 2 => 'pending', 3 => 'pending'], 'approve');

        $this->assertSame('pending', $result['status']);
        $this->assertSame(2, $result['current_step']);
        $this->assertSame([1 => 'approved', 2 => 'pending', 3 => 'pending'], $result['steps']);
    }

    public function test_approving_the_last_step_approves_the_request(): void
    {
        $result = ApprovalFlow::apply('pending', 2, [1 => 'approved', 2 => 'pending'], 'approve');

        $this->assertSame('approved', $result['status']);
        $this->assertSame(2, $result['current_step']);
        $this->assertSame([1 => 'approved', 2 => 'approved'], $result['steps']);
    }

    public function test_rejecting_ends_the_request_and_skips_remaining_steps(): void
    {
        $result = ApprovalFlow::apply('pending', 1, [1 => 'pending', 2 => 'pending'], 'reject');

        $this->assertSame('rejected', $result['status']);
        $this->assertSame([1 => 'rejected', 2 => 'skipped'], $result['steps']);
    }

    public function test_cancelling_skips_pending_steps_and_keeps_decided_ones(): void
    {
        $result = ApprovalFlow::apply('pending', 2, [1 => 'approved', 2 => 'pending', 3 => 'pending'], 'cancel');

        $this->assertSame('cancelled', $result['status']);
        $this->assertSame([1 => 'approved', 2 => 'skipped', 3 => 'skipped'], $result['steps']);
    }

    public function test_steps_are_ordered_by_step_order_not_input_order(): void
    {
        $result = ApprovalFlow::apply('pending', 1, [3 => 'pending', 1 => 'pending'], 'approve');

        $this->assertSame(3, $result['current_step']);
    }

    public function test_decided_requests_cannot_change(): void
    {
        foreach (['approved', 'rejected', 'cancelled'] as $status) {
            try {
                ApprovalFlow::apply($status, 1, [1 => 'approved'], 'approve');
                $this->fail("{$status} request accepted a decision");
            } catch (HrException $e) {
                $this->assertSame('approval_not_pending', $e->reason);
            }
        }
    }

    public function test_current_step_must_still_be_pending(): void
    {
        $this->expectException(HrException::class);

        ApprovalFlow::apply('pending', 1, [1 => 'approved', 2 => 'pending'], 'approve');
    }
}
