<?php

namespace App\Http\Controllers\Hr;

use App\Http\Requests\Hr\ApprovalWorkflowRequest;
use App\Http\Resources\Hr\ApprovalWorkflowResource;
use App\Models\Hr\ApprovalWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApprovalWorkflowController extends HrController
{
    public function index(Request $request): JsonResponse
    {
        if (! canAny(['access_hr-approvals', 'edit_hr-approvals', 'admin_hr-approvals'])) {
            return $this->denied();
        }

        return $this->inCenter($request, function (int $centerId) {
            $workflows = ApprovalWorkflow::query()->forCenter($centerId)->with('steps')->orderBy('request_type')->get();

            return success(ApprovalWorkflowResource::collection($workflows));
        });
    }

    /**
     * Creates or replaces the center's workflow for a request type. Requests already submitted keep
     * their own snapshot of steps.
     */
    public function put(ApprovalWorkflowRequest $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $data = $request->validated();

            $workflow = DB::transaction(function () use ($centerId, $data, $request) {
                $workflow = ApprovalWorkflow::query()->forCenter($centerId)
                    ->where('request_type', $data['request_type'])
                    ->lockForUpdate()
                    ->first() ?? new ApprovalWorkflow([
                        'center_id' => $centerId,
                        'request_type' => $data['request_type'],
                        'created_by' => $request->user()->id,
                    ]);

                $workflow->fill([
                    'name_ar' => $data['name_ar'],
                    'name_en' => $data['name_en'] ?? null,
                    'is_active' => $data['is_active'] ?? true,
                ])->save();

                $workflow->steps()->delete();
                foreach (array_values($data['steps']) as $index => $step) {
                    $workflow->steps()->create([
                        'step_order' => $index + 1,
                        'approver_type' => $step['approver_type'],
                        'approver_value' => $step['approver_value'] ?? null,
                    ]);
                }

                return $workflow;
            });

            return success(new ApprovalWorkflowResource($workflow->load('steps')), null, __('validation.hr.saved'));
        });
    }
}
