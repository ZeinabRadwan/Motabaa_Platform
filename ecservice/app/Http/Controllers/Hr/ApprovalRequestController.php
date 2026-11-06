<?php

namespace App\Http\Controllers\Hr;

use App\Http\Requests\Hr\ApprovalActionRequest;
use App\Http\Resources\Hr\ApprovalRequestResource;
use App\Models\Hr\ApprovalRequest;
use App\Support\Hr\Approvals\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Approval inbox. Business modules create requests through ApprovalService::submit(); this controller
 * only lists them and records decisions.
 *
 * scope=mine        requests I submitted (any user)
 * scope=awaiting_me pending requests whose current step I can act on (any user)
 * scope=all         every request of the center (access_hr-approvals)
 */
class ApprovalRequestController extends HrController
{
    private const AWAITING_SCAN_LIMIT = 500;

    private const RELATIONS = ['steps.actor:id,name', 'employee.user:id,name', 'requester:id,name', 'approvable'];

    public function index(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $user = $request->user();
            $scope = $request->get('scope', 'awaiting_me');

            $query = ApprovalRequest::query()->forCenter($centerId)
                ->with(self::RELATIONS)
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->get('status')))
                ->when($request->filled('request_type'), fn ($query) => $query->where('request_type', $request->get('request_type')))
                ->orderByDesc('id');

            if ($scope === 'mine') {
                $query->where('requested_by', $user->id);
            } elseif ($scope === 'all') {
                if (! canAny(['access_hr-approvals', 'admin_hr-approvals'])) {
                    return $this->denied();
                }
            } else {
                $ids = (clone $query)->where('status', ApprovalRequest::STATUS_PENDING)
                    ->limit(self::AWAITING_SCAN_LIMIT)
                    ->get()
                    ->filter(fn (ApprovalRequest $item) => ApprovalService::canAct($item, $user))
                    ->pluck('id');
                $query->whereIn('id', $ids);
            }

            return $this->paginated($request, $query, ApprovalRequestResource::class, [
                'submitted_at' => 'submitted_at', 'status' => 'status',
            ]);
        });
    }

    public function show(Request $request, int $approvalRequest): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $approvalRequest) {
            $model = $this->findInCenter(ApprovalRequest::class, $approvalRequest, $centerId)->load(self::RELATIONS);
            if (! $this->canView($model, $request)) {
                return $this->denied();
            }

            return success(new ApprovalRequestResource($model));
        });
    }

    public function approve(ApprovalActionRequest $request, int $approvalRequest): JsonResponse
    {
        return $this->decide($request, $approvalRequest, fn ($model, $user, $comment) => ApprovalService::approve($model, $user, $comment));
    }

    public function reject(ApprovalActionRequest $request, int $approvalRequest): JsonResponse
    {
        return $this->decide($request, $approvalRequest, fn ($model, $user, $comment) => ApprovalService::reject($model, $user, $comment));
    }

    public function cancel(ApprovalActionRequest $request, int $approvalRequest): JsonResponse
    {
        return $this->decide($request, $approvalRequest, fn ($model, $user, $comment) => ApprovalService::cancel($model, $user, $comment));
    }

    private function decide(ApprovalActionRequest $request, int $id, callable $action): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $id, $action) {
            $model = $this->findInCenter(ApprovalRequest::class, $id, $centerId);
            $model = $action($model, $request->user(), $request->validated('comment'));

            return success(new ApprovalRequestResource($model->load(self::RELATIONS)), null, __('validation.hr.saved'));
        });
    }

    private function canView(ApprovalRequest $model, Request $request): bool
    {
        $user = $request->user();

        return (int) $model->requested_by === (int) $user->id
            || ($model->employee && (int) $model->employee->user_id === (int) $user->id)
            || canAny(['access_hr-approvals', 'admin_hr-approvals'])
            || ApprovalService::canAct($model, $user)
            || $model->steps->contains(fn ($step) => (int) $step->acted_by === (int) $user->id);
    }
}
