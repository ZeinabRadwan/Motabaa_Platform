<?php

namespace App\Support\Hr\Approvals;

use App\Models\Hr\ApprovalRequest;
use App\Models\Hr\ApprovalRequestStep;
use App\Models\Hr\ApprovalWorkflow;
use App\Models\Hr\Employee;
use App\Models\User;
use App\Support\Hr\HrException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ApprovalService
{
    /** Used when a center has no active workflow for the request type. */
    public const FALLBACK_PERMISSION = 'apply_hr-approvals';

    /**
     * @param  (callable(array<int, array{step_order: int, approver_type: string, approver_value: ?string}>): array)|null  $steps
     *         receives the active workflow's steps ([] without a workflow) and returns the steps to use; the
     *         default permission step applies only when the result is empty.
     */
    public static function submit(
        Model $approvable,
        string $requestType,
        int $centerId,
        User $requestedBy,
        ?Employee $employee = null,
        ?callable $steps = null,
    ): ApprovalRequest {
        if (! in_array($requestType, ApprovalRequest::TYPES, true)) {
            throw new HrException('approval_unknown_type', 'request_type');
        }

        return DB::transaction(function () use ($approvable, $requestType, $centerId, $requestedBy, $employee, $steps) {
            $workflow = ApprovalWorkflow::query()
                ->forCenter($centerId)
                ->where('request_type', $requestType)
                ->where('is_active', true)
                ->with('steps')
                ->first();

            $steps = self::stepTemplates($workflow, $steps);

            $request = ApprovalRequest::create([
                'center_id' => $centerId,
                'branch_id' => $employee?->branch_id,
                'workflow_id' => $workflow?->id,
                'request_type' => $requestType,
                'approvable_type' => $approvable->getMorphClass(),
                'approvable_id' => $approvable->getKey(),
                'employee_id' => $employee?->id,
                'requested_by' => $requestedBy->id,
                'status' => ApprovalRequest::STATUS_PENDING,
                'current_step' => $steps[0]['step_order'],
                'submitted_at' => now(),
            ]);

            foreach ($steps as $step) {
                $request->steps()->create($step + ['status' => ApprovalRequestStep::STATUS_PENDING]);
            }

            return $request->load('steps');
        });
    }

    public static function approve(ApprovalRequest $request, User $actor, ?string $comment = null): ApprovalRequest
    {
        return self::act($request, $actor, ApprovalFlow::ACTION_APPROVE, $comment);
    }

    public static function reject(ApprovalRequest $request, User $actor, ?string $comment = null): ApprovalRequest
    {
        return self::act($request, $actor, ApprovalFlow::ACTION_REJECT, $comment);
    }

    public static function cancel(ApprovalRequest $request, User $actor, ?string $comment = null): ApprovalRequest
    {
        return self::act($request, $actor, ApprovalFlow::ACTION_CANCEL, $comment);
    }

    /** Cancels a pending request whose cancellation the caller has already authorized (e.g. leave rules). */
    public static function forceCancel(ApprovalRequest $request, User $actor, ?string $comment = null): ApprovalRequest
    {
        return self::act($request, $actor, ApprovalFlow::ACTION_CANCEL, $comment, true);
    }

    /**
     * Whether $actor may approve or reject the request's current step. Requesters and the employee the
     * request is about can never approve their own request.
     */
    public static function canAct(ApprovalRequest $request, User $actor): bool
    {
        if (! $request->isPending()) {
            return false;
        }

        if ((int) $request->requested_by === (int) $actor->id) {
            return false;
        }

        $request->loadMissing(['employee', 'steps']);
        if ($request->employee && (int) $request->employee->user_id === (int) $actor->id) {
            return false;
        }

        $step = $request->currentStepRecord();

        return $step !== null && self::matchesApprover($step, $request, $actor);
    }

    public static function canCancel(ApprovalRequest $request, User $actor): bool
    {
        return $request->isPending()
            && ((int) $request->requested_by === (int) $actor->id || canAny(['admin_hr-approvals'], $actor));
    }

    private static function act(ApprovalRequest $request, User $actor, string $action, ?string $comment, bool $preAuthorized = false): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $actor, $action, $comment, $preAuthorized) {
            $locked = ApprovalRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $locked->load(['steps', 'employee']);

            $allowed = match (true) {
                $preAuthorized => $locked->isPending(),
                $action === ApprovalFlow::ACTION_CANCEL => self::canCancel($locked, $actor),
                default => self::canAct($locked, $actor),
            };
            if (! $allowed) {
                throw new HrException($locked->isPending() ? 'approval_not_allowed' : 'approval_not_pending', 'error', 403);
            }

            $result = ApprovalFlow::apply(
                $locked->status,
                $locked->current_step,
                $locked->steps->pluck('status', 'step_order')->all(),
                $action
            );

            foreach ($locked->steps as $step) {
                $newStatus = $result['steps'][$step->step_order];
                if ($newStatus === $step->status) {
                    continue;
                }
                $actedNow = $step->step_order === $locked->current_step && $action !== ApprovalFlow::ACTION_CANCEL;
                $step->status = $newStatus;
                if ($actedNow) {
                    $step->acted_by = $actor->id;
                    $step->acted_at = now();
                    $step->comment = $comment;
                }
                $step->save();
            }

            $locked->status = $result['status'];
            $locked->current_step = $result['current_step'];
            if (! $locked->isPending()) {
                $locked->completed_at = now();
            }
            $locked->save();

            if (! $locked->isPending()) {
                $approvable = $locked->approvable;
                if ($approvable instanceof Approvable) {
                    $approvable->approvalResolved($locked);
                }
            }

            return $locked->load('steps');
        });
    }

    /**
     * @return array<int, array{step_order: int, approver_type: string, approver_value: ?string}>
     */
    private static function stepTemplates(?ApprovalWorkflow $workflow, ?callable $customize = null): array
    {
        $steps = $workflow
            ? $workflow->steps->map(fn ($step) => [
                'step_order' => (int) $step->step_order,
                'approver_type' => $step->approver_type,
                'approver_value' => $step->approver_value,
            ])->values()->all()
            : [];

        if ($customize !== null) {
            $steps = array_values($customize($steps));
        }

        if ($steps === []) {
            $steps[] = [
                'step_order' => 1,
                'approver_type' => ApprovalRequestStep::APPROVER_PERMISSION,
                'approver_value' => self::FALLBACK_PERMISSION,
            ];
        }

        return $steps;
    }

    private static function matchesApprover(ApprovalRequestStep $step, ApprovalRequest $request, User $actor): bool
    {
        $employee = $request->employee;

        return match ($step->approver_type) {
            ApprovalRequestStep::APPROVER_USER => (int) $step->approver_value === (int) $actor->id,
            ApprovalRequestStep::APPROVER_ROLE => $actor->roles()->whereKey((int) $step->approver_value)->exists(),
            ApprovalRequestStep::APPROVER_PERMISSION => can((string) $step->approver_value, $actor),
            ApprovalRequestStep::APPROVER_DIRECT_MANAGER => self::isEmployeeUser($employee?->manager, $actor),
            ApprovalRequestStep::APPROVER_DEPARTMENT_MANAGER => self::isEmployeeUser($employee?->department?->manager, $actor),
            ApprovalRequestStep::APPROVER_BRANCH_MANAGER => self::isEmployeeUser($employee?->branch?->manager, $actor),
            default => false,
        };
    }

    private static function isEmployeeUser(?Employee $employee, User $actor): bool
    {
        return $employee !== null && (int) $employee->user_id === (int) $actor->id;
    }
}
