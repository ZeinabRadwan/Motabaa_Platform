<?php

namespace App\Http\Resources\Hr;

use App\Models\Hr\ApprovalRequestStep;
use App\Models\Hr\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $type = $this->leaveType;
        $approval = $this->relationLoaded('approvalRequest') ? $this->approvalRequest : null;
        $approverLabels = $approval ? ApprovalRequestStep::approverTypeLabels() : [];

        return [
            'id' => $this->id,
            'employee' => EmployeeReference::make($this->employee),
            'branch_id' => $this->branch_id,
            'leave_type' => $type ? [
                'id' => $type->id,
                'code' => $type->code,
                'name' => $type->name,
                'color' => $type->color,
                'is_paid' => $type->is_paid,
                'day_counting' => $type->day_counting,
            ] : null,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'days' => $this->days,
            'calendar_days' => $this->calendar_days,
            'balance_year' => $this->balance_year,
            'reason' => $this->reason,
            'status' => $this->status,
            'status_label' => LeaveRequest::statusLabels()[$this->status] ?? $this->status,
            'source' => $this->source,
            'source_label' => __('tr.hr.leave_sources.'.$this->source),
            'requested_by' => minimalUserReference($this->requester),
            'requested_at' => $this->created_at,
            'decided_by' => minimalUserReference($this->decider),
            'decided_at' => $this->decided_at,
            'cancelled_by' => minimalUserReference($this->canceller),
            'cancelled_at' => $this->cancelled_at,
            'cancel_reason' => $this->cancel_reason,
            'attachments' => $this->relationLoaded('attachments')
                ? $this->attachments->map(fn ($file) => [
                    'id' => $file->id,
                    'name' => $file->original_name,
                    'mime_type' => $file->mime_type,
                    'size' => $file->size,
                ])->values()
                : [],
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
        ];
    }
}
