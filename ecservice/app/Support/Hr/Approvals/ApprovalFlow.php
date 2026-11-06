<?php

namespace App\Support\Hr\Approvals;

use App\Models\Hr\ApprovalRequest;
use App\Models\Hr\ApprovalRequestStep;
use App\Support\Hr\HrException;

/**
 * Pure state transitions of an approval request. Steps run strictly in step_order; one rejection
 * ends the request; cancelling skips whatever is still pending.
 */
final class ApprovalFlow
{
    public const ACTION_APPROVE = 'approve';
    public const ACTION_REJECT = 'reject';
    public const ACTION_CANCEL = 'cancel';

    /**
     * @param  array<int, string>  $stepStatuses  step_order => status
     * @return array{status: string, current_step: int, steps: array<int, string>}
     */
    public static function apply(string $requestStatus, int $currentStep, array $stepStatuses, string $action): array
    {
        if ($requestStatus !== ApprovalRequest::STATUS_PENDING) {
            throw new HrException('approval_not_pending');
        }

        ksort($stepStatuses);

        if ($action === self::ACTION_CANCEL) {
            return [
                'status' => ApprovalRequest::STATUS_CANCELLED,
                'current_step' => $currentStep,
                'steps' => self::skipPending($stepStatuses),
            ];
        }

        if (($stepStatuses[$currentStep] ?? null) !== ApprovalRequestStep::STATUS_PENDING) {
            throw new HrException('approval_step_not_pending');
        }

        if ($action === self::ACTION_REJECT) {
            $stepStatuses[$currentStep] = ApprovalRequestStep::STATUS_REJECTED;

            return [
                'status' => ApprovalRequest::STATUS_REJECTED,
                'current_step' => $currentStep,
                'steps' => self::skipPending($stepStatuses),
            ];
        }

        if ($action !== self::ACTION_APPROVE) {
            throw new \InvalidArgumentException("Unknown approval action [{$action}].");
        }

        $stepStatuses[$currentStep] = ApprovalRequestStep::STATUS_APPROVED;
        foreach ($stepStatuses as $order => $status) {
            if ($order > $currentStep && $status === ApprovalRequestStep::STATUS_PENDING) {
                return [
                    'status' => ApprovalRequest::STATUS_PENDING,
                    'current_step' => $order,
                    'steps' => $stepStatuses,
                ];
            }
        }

        return [
            'status' => ApprovalRequest::STATUS_APPROVED,
            'current_step' => $currentStep,
            'steps' => $stepStatuses,
        ];
    }

    private static function skipPending(array $stepStatuses): array
    {
        return array_map(
            fn ($status) => $status === ApprovalRequestStep::STATUS_PENDING ? ApprovalRequestStep::STATUS_SKIPPED : $status,
            $stepStatuses
        );
    }
}
