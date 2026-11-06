<?php

namespace App\Http\Resources\Hr;

use App\Models\Hr\AdditionalWorkDay;
use App\Models\Hr\ApprovalRequestStep;
use App\Support\Hr\HrAdditionalWorkDays;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AdditionalWorkDay
 */
class AdditionalWorkDayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $approval = $this->relationLoaded('approvalRequest') ? $this->approvalRequest : null;
        $approverLabels = $approval ? ApprovalRequestStep::approverTypeLabels() : [];
        $period = $this->relationLoaded('attendancePeriod') ? $this->attendancePeriod : null;
        $paidPeriod = $this->relationLoaded('paidPeriod') ? $this->paidPeriod : null;
        $open = in_array($this->status, [AdditionalWorkDay::STATUS_DRAFT, AdditionalWorkDay::STATUS_PENDING, AdditionalWorkDay::STATUS_APPROVED], true);
        $own = $this->employee !== null && (int) $this->employee->user_id === (int) $request->user()?->id;
        $editor = ! $own && canAny(['edit_hr-additional-days', 'admin_hr-additional-days']);

        return [
            'id' => $this->id,
            'employee' => EmployeeReference::make($this->employee),
            'branch_id' => $this->branch_id,
            'branch_name' => $this->relationLoaded('branch') ? $this->branch?->name : null,
            'work_date' => $this->work_date->toDateString(),
            'days' => $this->days,
            'day_type' => $this->day_type,
            'day_type_label' => $this->day_type ? (AdditionalWorkDay::dayTypeLabels()[$this->day_type] ?? $this->day_type) : null,
            'reason' => $this->reason,
            'status' => $this->status,
            'status_label' => AdditionalWorkDay::statusLabels()[$this->status] ?? $this->status,
            'attendance_period' => $period ? ['id' => $period->id, 'name' => $period->name, 'status' => $period->status] : null,
            'paid_period' => $paidPeriod ? ['id' => $paidPeriod->id, 'name' => $paidPeriod->name, 'status' => $paidPeriod->status] : null,
            'carried_forward' => HrAdditionalWorkDays::isCarriedForward($this->resource),
            'paid_at' => $this->paid_at,
            'gross_salary' => $this->gross_salary,
            'daily_rate' => $this->daily_rate,
            'amount' => $this->amount,
            // Unpaid records: what payroll would pay with the salary in force on the work date (null: no salary).
            'estimated' => $open ? HrAdditionalWorkDays::estimate($this->resource) : null,
            'created_by' => minimalUserReference($this->creator),
            'created_at' => $this->created_at,
            'submitted_by' => minimalUserReference($this->submitter),
            'submitted_at' => $this->submitted_at,
            'approved_by' => minimalUserReference($this->approver),
            'approved_at' => $this->approved_at,
            'rejected_by' => minimalUserReference($this->rejecter),
            'rejected_at' => $this->rejected_at,
            'rejection_reason' => $this->rejection_reason,
            'cancelled_by' => minimalUserReference($this->canceller),
            'cancelled_at' => $this->cancelled_at,
            'cancel_reason' => $this->cancel_reason,
            'updated_at' => $this->updated_at,
            'approval' => $approval ? [
                'id' => $approval->id,
                'status' => $approval->status,
                'current_step' => $approval->current_step,
                'steps' => $approval->steps->map(fn ($step) => [
                    'step_order' => $step->step_order,
                    'approver_type' => $step->approver_type,
                    'approver_type_label' => $approverLabels[$step->approver_type] ?? $step->approver_type,
                    'approver_value_label' => ApprovalRequestStep::approverValueLabel($step->approver_type, $step->approver_value),
                    'status' => $step->status,
                    'acted_by' => minimalUserReference($step->actor),
                    'acted_at' => $step->acted_at,
                    'comment' => $step->comment,
                ])->values(),
            ] : null,
            'can_edit' => $editor && $this->isEditable(),
            'can_submit' => $editor && $this->status === AdditionalWorkDay::STATUS_DRAFT,
            'can_cancel' => ! $own && (
                ($this->isEditable() && $editor)
                || ($this->status === AdditionalWorkDay::STATUS_APPROVED && can('admin_hr-additional-days'))
            ),
        ];
    }
}
