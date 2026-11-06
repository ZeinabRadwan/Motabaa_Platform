<?php

namespace App\Http\Resources\Hr;

use App\Models\Hr\ApprovalRequest;
use App\Models\Hr\ApprovalRequestStep;
use App\Support\Hr\Approvals\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApprovalRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $actor = $request->user();
        $approverLabels = ApprovalRequestStep::approverTypeLabels();

        return [
            'id' => $this->id,
            'request_type' => $this->request_type,
            'request_type_label' => ApprovalRequest::typeLabels()[$this->request_type] ?? $this->request_type,
            'approvable_type' => $this->approvable_type,
            'approvable_id' => $this->approvable_id,
            'branch_id' => $this->branch_id,
            'employee' => EmployeeReference::make($this->employee),
            'requested_by' => minimalUserReference($this->requester),
            'status' => $this->status,
            'status_label' => ApprovalRequest::statusLabels()[$this->status] ?? $this->status,
            'current_step' => $this->current_step,
            'submitted_at' => $this->submitted_at,
            'completed_at' => $this->completed_at,
            'can_act' => $actor ? ApprovalService::canAct($this->resource, $actor) : false,
            'can_cancel' => $actor ? ApprovalService::canCancel($this->resource, $actor) : false,
            'subject' => $this->relationLoaded('approvable') && $this->approvable && method_exists($this->approvable, 'approvalSummary')
                ? $this->approvable->approvalSummary()
                : null,
            'steps' => $this->steps->map(fn ($step) => [
                'step_order' => $step->step_order,
                'approver_type' => $step->approver_type,
                'approver_type_label' => $approverLabels[$step->approver_type] ?? $step->approver_type,
                'approver_value' => $step->approver_value,
                'approver_value_label' => ApprovalRequestStep::approverValueLabel($step->approver_type, $step->approver_value),
                'status' => $step->status,
                'acted_by' => minimalUserReference($step->actor),
                'acted_at' => $step->acted_at,
                'comment' => $step->comment,
            ])->values(),
        ];
    }
}
