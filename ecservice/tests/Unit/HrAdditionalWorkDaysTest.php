<?php

namespace Tests\Unit;

use App\Models\Hr\AdditionalWorkDay;
use App\Models\Hr\ApprovalRequestStep;
use App\Models\Hr\AttendancePeriod;
use App\Support\Hr\HrAdditionalWorkDays;
use App\Support\Hr\HrLeaveRequests;
use Tests\TestCase;

class HrAdditionalWorkDaysTest extends TestCase
{
    private const PERIOD = 10;

    public function test_amount_is_gross_salary_divided_by_thirty_times_days(): void
    {
        $this->assertSame(['gross_salary' => '9000.00', 'daily_rate' => '300.00', 'amount' => '300.00'], HrAdditionalWorkDays::amount('9000.00', 1));
        $this->assertSame('150.00', HrAdditionalWorkDays::amount('9000.00', 0.5)['amount'], 'half day');
        $this->assertSame(['gross_salary' => '10000.00', 'daily_rate' => '333.33', 'amount' => '333.33'], HrAdditionalWorkDays::amount(10000, 1));
        $this->assertSame('166.67', HrAdditionalWorkDays::amount(10000, 0.5)['amount'], 'rounded once, on the total');
        $this->assertSame('0.00', HrAdditionalWorkDays::amount('0', 1)['amount']);
    }

    public function test_only_approved_unpaid_days_of_the_period_or_carried_forward_are_eligible(): void
    {
        $eligible = fn (array $record, ?int $branch = null) => HrAdditionalWorkDays::eligibleFor($record + [
            'status' => AdditionalWorkDay::STATUS_APPROVED,
            'work_date' => '2026-03-06',
            'branch_id' => 1,
            'paid_period_id' => null,
            'own_period_id' => self::PERIOD,
            'own_period_status' => AttendancePeriod::STATUS_PROCESSED,
        ], self::PERIOD, '2026-03-01', '2026-03-31', $branch);

        $this->assertTrue($eligible([]));
        foreach ([AdditionalWorkDay::STATUS_DRAFT, AdditionalWorkDay::STATUS_PENDING, AdditionalWorkDay::STATUS_REJECTED, AdditionalWorkDay::STATUS_CANCELLED, AdditionalWorkDay::STATUS_PAID] as $status) {
            $this->assertFalse($eligible(['status' => $status]), "{$status} is never paid");
        }
        $this->assertFalse($eligible(['work_date' => '2026-04-03', 'own_period_id' => null]), 'days after the period wait for their own period');
        $this->assertFalse($eligible([], 2), 'a branch period pays the days worked in its branch only');
        $this->assertTrue($eligible([], 1));

        // Carried forward: an earlier day whose own period closed (approved) without it, or that has no period.
        $earlier = ['work_date' => '2026-02-20', 'own_period_id' => 9];
        $this->assertTrue($eligible($earlier + ['own_period_status' => AttendancePeriod::STATUS_APPROVED]));
        $this->assertFalse($eligible($earlier + ['own_period_status' => AttendancePeriod::STATUS_PROCESSED]), 'its own open period pays it');
        $this->assertTrue($eligible(['work_date' => '2026-02-20', 'own_period_id' => null, 'own_period_status' => null]));

        // Reserved to the period that paid it (kept when that period is reopened): no other period pays it.
        $this->assertFalse($eligible(['paid_period_id' => 9]));
        $this->assertTrue($eligible(['paid_period_id' => self::PERIOD, 'work_date' => '2026-02-20', 'own_period_id' => 9, 'own_period_status' => AttendancePeriod::STATUS_PROCESSED]));
    }

    public function test_period_of_a_date_respects_branch_periods(): void
    {
        $center = new AttendancePeriod(['period_start' => '2026-02-01', 'period_end' => '2026-02-28', 'branch_id' => null]);
        $center->id = 1;
        $branch = new AttendancePeriod(['period_start' => '2026-03-01', 'period_end' => '2026-03-31', 'branch_id' => 5]);
        $branch->id = 2;
        $periods = [$center, $branch];

        $this->assertSame(1, HrAdditionalWorkDays::periodFor($periods, '2026-02-14', 7)?->id);
        $this->assertSame(2, HrAdditionalWorkDays::periodFor($periods, '2026-03-14', 5)?->id);
        $this->assertNull(HrAdditionalWorkDays::periodFor($periods, '2026-03-14', 7), 'other branch: no period yet');
        $this->assertNull(HrAdditionalWorkDays::periodFor($periods, '2026-04-01', null));
    }

    public function test_approval_goes_to_the_manager_then_hr_with_the_additional_days_permission(): void
    {
        $steps = HrLeaveRequests::approvalSteps([], true, 20, 10, 30, HrAdditionalWorkDays::HR_APPROVAL_PERMISSION);
        $this->assertSame([
            ['step_order' => 1, 'approver_type' => ApprovalRequestStep::APPROVER_DIRECT_MANAGER, 'approver_value' => null],
            ['step_order' => 2, 'approver_type' => ApprovalRequestStep::APPROVER_PERMISSION, 'approver_value' => 'apply_hr-additional-days'],
        ], $steps);

        $workflow = [['approver_type' => ApprovalRequestStep::APPROVER_DEPARTMENT_MANAGER, 'approver_value' => null]];
        $this->assertCount(1, HrLeaveRequests::approvalSteps($workflow, false, 20, 10, 30, HrAdditionalWorkDays::HR_APPROVAL_PERMISSION), 'a configured workflow is used as is');
        $this->assertSame('apply_hr-leave', HrLeaveRequests::approvalSteps([], true, null, 10, 30)[0]['approver_value'], 'leave keeps its own permission');
    }
}
