<?php

namespace Tests\Unit;

use App\Models\Hr\ApprovalRequestStep;
use App\Models\Hr\Holiday;
use App\Models\Hr\Shift;
use App\Support\Hr\HrLeaveBalances;
use App\Support\Hr\HrLeaveRequests;
use App\Support\Hr\ScheduledShiftDay;
use App\Support\Hr\ShiftDefinition;
use Tests\TestCase;

class HrLeaveRulesTest extends TestCase
{
    private const WEEKEND = [5, 6];

    public function test_leave_days_exclude_rest_days_and_holidays_unless_counted_in_calendar_days(): void
    {
        $definition = new ShiftDefinition(1, 'S', 'Shift', Shift::TYPE_CUSTOM, null, '08:00', '17:00', null, 0, null, null, null, [0, 1, 2, 3, 4], self::WEEKEND);
        $working = ScheduledShiftDay::working('2026-03-01', $definition, 'Asia/Riyadh', 15, 0);
        $rest = new ScheduledShiftDay('2026-03-06', ScheduledShiftDay::STATUS_REST);
        $off = new ScheduledShiftDay('2026-03-02', ScheduledShiftDay::STATUS_OFF);
        $unscheduledWeekday = new ScheduledShiftDay('2026-03-03', ScheduledShiftDay::STATUS_UNSCHEDULED);
        $unscheduledWeekend = new ScheduledShiftDay('2026-03-07', ScheduledShiftDay::STATUS_UNSCHEDULED);
        $notEmployed = new ScheduledShiftDay('2026-03-04', ScheduledShiftDay::STATUS_NOT_EMPLOYED);
        $holiday = new Holiday();

        $this->assertTrue(HrLeaveRequests::isLeaveDay($working, null, self::WEEKEND, false));
        $this->assertFalse(HrLeaveRequests::isLeaveDay($working, $holiday, self::WEEKEND, false), 'holidays are not deducted');
        $this->assertFalse(HrLeaveRequests::isLeaveDay($rest, null, self::WEEKEND, false), 'rest days are not deducted');
        $this->assertFalse(HrLeaveRequests::isLeaveDay($off, null, self::WEEKEND, false));
        $this->assertTrue(HrLeaveRequests::isLeaveDay($unscheduledWeekday, null, self::WEEKEND, false), 'no schedule: weekdays count');
        $this->assertFalse(HrLeaveRequests::isLeaveDay($unscheduledWeekend, null, self::WEEKEND, false));

        foreach ([$working, $rest, $off, $unscheduledWeekend] as $day) {
            $this->assertTrue(HrLeaveRequests::isLeaveDay($day, $holiday, self::WEEKEND, true), 'calendar-day leave counts every day');
        }
        $this->assertFalse(HrLeaveRequests::isLeaveDay($notEmployed, null, self::WEEKEND, true));
    }

    public function test_approval_steps_without_workflow_go_to_the_manager_then_hr_when_required(): void
    {
        $managerThenHr = HrLeaveRequests::approvalSteps([], true, 20, 10, 10);
        $this->assertSame([
            ['step_order' => 1, 'approver_type' => ApprovalRequestStep::APPROVER_DIRECT_MANAGER, 'approver_value' => null],
            ['step_order' => 2, 'approver_type' => ApprovalRequestStep::APPROVER_PERMISSION, 'approver_value' => HrLeaveRequests::HR_APPROVAL_PERMISSION],
        ], $managerThenHr);

        $managerOnly = HrLeaveRequests::approvalSteps([], false, 20, 10, 10);
        $this->assertCount(1, $managerOnly);
        $this->assertSame(ApprovalRequestStep::APPROVER_DIRECT_MANAGER, $managerOnly[0]['approver_type']);

        $noManager = HrLeaveRequests::approvalSteps([], false, null, 10, 10);
        $this->assertSame(HrLeaveRequests::HR_APPROVAL_PERMISSION, $noManager[0]['approver_value'], 'someone must approve');

        $managerIsRequester = HrLeaveRequests::approvalSteps([], false, 20, 20, 10);
        $this->assertCount(1, $managerIsRequester, 'a manager filing for an employee does not approve it himself');
        $this->assertSame(ApprovalRequestStep::APPROVER_PERMISSION, $managerIsRequester[0]['approver_type']);
    }

    public function test_approval_steps_follow_the_workflow_and_add_hr_once(): void
    {
        $workflow = [['approver_type' => ApprovalRequestStep::APPROVER_DEPARTMENT_MANAGER, 'approver_value' => null]];

        $this->assertCount(1, HrLeaveRequests::approvalSteps($workflow, false, 20, 10, 10));
        $withHr = HrLeaveRequests::approvalSteps($workflow, true, 20, 10, 10);
        $this->assertCount(2, $withHr);
        $this->assertSame(2, $withHr[1]['step_order']);

        $workflowWithHr = [...$workflow, ['approver_type' => ApprovalRequestStep::APPROVER_PERMISSION, 'approver_value' => HrLeaveRequests::HR_APPROVAL_PERMISSION]];
        $this->assertCount(2, HrLeaveRequests::approvalSteps($workflowWithHr, true, 20, 10, 10), 'HR is not added twice');
    }

    public function test_annual_entitlement_by_length_of_service_contract_and_proration(): void
    {
        $this->assertSame(21.0, HrLeaveBalances::annualEntitlement('2022-03-01', 2026, 21, 30));
        $this->assertSame(30.0, HrLeaveBalances::annualEntitlement('2021-01-01', 2026, 21, 30), 'five full years on 1 January');
        $this->assertSame(21.0, HrLeaveBalances::annualEntitlement('2021-01-02', 2026, 21, 30), 'five years reached during the year');
        $this->assertSame(25.0, HrLeaveBalances::annualEntitlement('2022-03-01', 2026, 21, 30, 25), 'higher contract entitlement');
        $this->assertSame(21.0, HrLeaveBalances::annualEntitlement('2022-03-01', 2026, 21, 30, 10), 'lower contract entitlement is ignored');
        $this->assertSame(round(21 * 184 / 365, 2), HrLeaveBalances::annualEntitlement('2026-07-01', 2026, 21, 30), 'joined on 1 July');
        $this->assertSame(0.0, HrLeaveBalances::annualEntitlement('2027-01-05', 2026, 21, 30));
        $this->assertSame(21.0, HrLeaveBalances::annualEntitlement(null, 2026, 21, 30));
    }
}
