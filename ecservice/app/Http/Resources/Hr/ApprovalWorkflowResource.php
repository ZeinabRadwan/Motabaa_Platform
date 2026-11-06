<?php

namespace App\Http\Resources\Hr;

use App\Models\Hr\ApprovalRequest;
use App\Models\Hr\ApprovalRequestStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApprovalWorkflowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $approverLabels = ApprovalRequestStep::approverTypeLabels();

        return [
            'id' => $this->id,
            'request_type' => $this->request_type,
            'request_type_label' => ApprovalRequest::typeLabels()[$this->request_type] ?? $this->request_type,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'is_active' => (bool) $this->is_active,
            'steps' => $this->steps->map(fn ($step) => [
                'step_order' => $step->step_order,
                'approver_type' => $step->approver_type,
                'approver_type_label' => $approverLabels[$step->approver_type] ?? $step->approver_type,
                'approver_value' => $step->approver_value,
                'approver_value_label' => ApprovalRequestStep::approverValueLabel($step->approver_type, $step->approver_value),
            ])->values(),
            'updated_at' => $this->updated_at,
        ];
    }
}
