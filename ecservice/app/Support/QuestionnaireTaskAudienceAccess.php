<?php

namespace App\Support;

use App\Models\QuestionnaireTask;
use App\Models\System\System;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class QuestionnaireTaskAudienceAccess
{
    public static function appliesRespondentAudienceGate(?User $user): bool
    {
        if (! $user) {
            return true;
        }

        if (can('access_questionnaires', $user) && ! isParentUser($user)) {
            return false;
        }

        return true;
    }

    public static function userCanAccessQuestionnaireTask(?User $user, QuestionnaireTask $task): bool
    {
        if (! self::appliesRespondentAudienceGate($user)) {
            return true;
        }

        if (! $user) {
            return $task->isParentAudience();
        }

        if ($task->isRoleAudience()) {
            return self::userMatchesRoleAudience($user, $task);
        }

        return QuestionnaireParentTermAccess::parentCanAccessQuestionnaireTask($user, $task);
    }

    public static function denyResponseIfBlocked(?User $user, QuestionnaireTask $task): ?JsonResponse
    {
        if (! $user) {
            if ($task->isRoleAudience()) {
                return self::accessDeniedResponse();
            }

            return null;
        }

        if (self::userCanAccessQuestionnaireTask($user, $task)) {
            return null;
        }

        return self::accessDeniedResponse();
    }

    public static function accessDeniedResponse(): JsonResponse
    {
        return response()->json([
            'errors' => ['error' => [__('validation.questionnaires.Not eligible for this questionnaire')]],
        ], 403);
    }

    public static function userMatchesRoleAudience(User $user, QuestionnaireTask $task): bool
    {
        $roleId = (int) $task->target_role_id;
        if ($roleId <= 0) {
            return false;
        }

        if ((int) $user->status !== User::STATUS_CAN_LOGIN) {
            return false;
        }

        if (! $user->isInCenter((int) $task->center_id)) {
            return false;
        }

        return $user->roles()->where('roles.id', $roleId)->exists();
    }

    /**
     * @return Collection<int, int>
     */
    public static function eligibleUserIdsForTask(QuestionnaireTask $task): Collection
    {
        if ($task->isRoleAudience()) {
            return self::eligibleRoleUserIdsForTask($task);
        }

        $termId = (int) $task->term_id;
        if ($termId <= 0) {
            return collect();
        }

        return QuestionnaireParentTermAccess::eligibleParentUserIdsForTerm($termId, (int) $task->center_id);
    }

    /**
     * @return Collection<int, int>
     */
    public static function eligibleRoleUserIdsForTask(QuestionnaireTask $task): Collection
    {
        $roleId = (int) $task->target_role_id;
        $centerId = (int) $task->center_id;
        if ($roleId <= 0 || $centerId <= 0) {
            return collect();
        }

        return User::query()
            ->where('status', User::STATUS_CAN_LOGIN)
            ->whereHas('centers', function ($query) use ($centerId) {
                $query->where('centers.id', $centerId);
            })
            ->whereHas('roles', function ($query) use ($roleId) {
                $query->where('roles.id', $roleId);
            })
            ->distinct()
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id);
    }

    /**
     * Open questionnaire tasks visible to the user (in-memory filter for complex rules).
     *
     * @return \Illuminate\Support\Collection<int, QuestionnaireTask>
     */
    public static function availableOpenTasksForUser(User $user, int $centerId): Collection
    {
        $todayDate = now(QuestionnaireTask::AVAILABILITY_TIMEZONE)->format('Y-m-d');

        $tasks = QuestionnaireTask::query()
            ->with(['questionnaire', 'term'])
            ->where('center_id', $centerId)
            ->whereDate('starts_at', '<=', $todayDate)
            ->whereDate('ends_at', '>=', $todayDate)
            ->orderByDesc('starts_at')
            ->get();

        return $tasks->filter(fn (QuestionnaireTask $task) => $task->isOpen()
            && self::userCanAccessQuestionnaireTask($user, $task))
            ->values();
    }

    public static function normalizeAudienceType(?string $value): string
    {
        return $value === QuestionnaireTask::AUDIENCE_ROLE
            ? QuestionnaireTask::AUDIENCE_ROLE
            : QuestionnaireTask::AUDIENCE_PARENT;
    }

    /**
     * @return array{0: bool, 1: ?JsonResponse}
     */
    public static function validateAudienceInput(
        string $audienceType,
        $termId,
        $targetRoleId,
        $user,
        int $centerId
    ): array {
        if ($audienceType === QuestionnaireTask::AUDIENCE_PARENT) {
            $termId = (int) $termId;
            if ($termId <= 0) {
                return [false, response()->json([
                    'errors' => ['error' => [__('validation.questionnaires.Term required for parent audience')]],
                ], 422)];
            }

            $denied = \App\Models\Term::abortIfInaccessible($user, $centerId, $termId);
            if ($denied) {
                return [false, $denied];
            }

            return [true, null];
        }

        $roleId = (int) $targetRoleId;
        if ($roleId <= 0 || ! Role::query()->where('id', $roleId)->exists()) {
            return [false, response()->json([
                'errors' => ['error' => [__('validation.questionnaires.Target role required')]],
            ], 422)];
        }

        return [true, null];
    }
}
