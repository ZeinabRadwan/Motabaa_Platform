<?php

namespace App\Support;

use App\Models\QuestionnaireTask;
use App\Models\System\System;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class QuestionnaireParentTermAccess
{
    /**
     * Whether term-scoped parent eligibility should be enforced for this user.
     */
    public static function appliesParentTermGate(User $user): bool
    {
        if (! CenterActivityAccess::isParentAccount($user)) {
            return false;
        }

        if (can('access_questionnaires', $user) && ! isParentUser($user)) {
            return false;
        }

        return true;
    }

    public static function parentCanAccessQuestionnaireTask(User $user, QuestionnaireTask $task): bool
    {
        if (! self::appliesParentTermGate($user)) {
            return true;
        }

        if (! $task->isParentAudience()) {
            return false;
        }

        $termId = (int) $task->term_id;
        if ($termId <= 0) {
            return true;
        }

        return self::eligibleParentUsersQuery($termId, (int) $task->center_id)
            ->where('users.id', $user->id)
            ->exists();
    }

    public static function denyResponseIfBlocked(?User $user, QuestionnaireTask $task): ?JsonResponse
    {
        if (! $user || self::parentCanAccessQuestionnaireTask($user, $task)) {
            return null;
        }

        return self::accessDeniedResponse();
    }

    public static function accessDeniedResponse(): JsonResponse
    {
        return response()->json([
            'errors' => ['error' => [__('validation.questionnaires.Not eligible for this term')]],
        ], 403);
    }

    /**
     * @return Collection<int, int>
     */
    public static function eligibleParentUserIdsForTerm(int $termId, int $centerId): Collection
    {
        if ($termId <= 0) {
            return collect();
        }

        return self::eligibleParentUsersQuery($termId, $centerId)
            ->distinct()
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id);
    }

    public static function eligibleParentUsersQuery(int $termId, int $centerId): Builder
    {
        $caseIds = CenterActivityAccess::caseIdsEnrolledInTerm($termId);

        $parentQuery = User::query()
            ->where('status', User::STATUS_CAN_LOGIN)
            ->whereHas('roles', function ($query) {
                $query->where('default_name', System::USER_TYPE_PARENT_ROLE_NAME);
            })
            ->whereHas('centers', function ($query) use ($centerId) {
                $query->where('centers.id', $centerId);
            });

        if ($caseIds !== []) {
            $parentQuery->where(function ($query) use ($caseIds, $termId) {
                $query->whereHas('scaseParent', function ($caseQuery) use ($caseIds) {
                    $caseQuery->whereIn('scases.id', $caseIds);
                });
                if (Term::hasUserAssignmentTable()) {
                    $query->orWhereHas('terms', function ($termQuery) use ($termId) {
                        $termQuery->where('terms.id', $termId);
                    });
                }
            });
        } elseif (Term::hasUserAssignmentTable()) {
            $parentQuery->whereHas('terms', function ($termQuery) use ($termId) {
                $termQuery->where('terms.id', $termId);
            });
        } else {
            return User::query()->whereRaw('1 = 0');
        }

        return $parentQuery;
    }
}
