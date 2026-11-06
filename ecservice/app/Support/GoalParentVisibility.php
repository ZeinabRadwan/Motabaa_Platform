<?php

namespace App\Support;

use App\Models\Goal;
use App\Models\Message;
use App\Models\SCase;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\Lang;

/**
 * Parent visibility of goals and of their session messages.
 *
 * Goals: a parent sees every goal of a beneficiary linked to them, in their center and visible
 * term. Messages: only rows staff shared with parents (`parents_can_see = 1`), excluding system
 * session/meeting markers. Attachments are optional in both cases.
 */
class GoalParentVisibility
{
    /** @var string[] Treatment plan categories (Support Services). */
    public const TREATMENT_CATEGORIES = [
        'physical therapy',
        'occupational therapy',
        'pronouncement',
        'psychiatric treatment',
    ];

    public static function isTreatmentCategory(?string $category): bool
    {
        return $category !== null
            && $category !== ''
            && in_array($category, self::TREATMENT_CATEGORIES, true);
    }

    public static function parentCanAccessCase(User $user, int $caseId, ?int $centerId): bool
    {
        if (! isParentUser($user) || $caseId <= 0) {
            return false;
        }

        return SCase::query()
            ->where('scases.id', $caseId)
            ->when($centerId, fn ($q) => $q->where('scases.center_id', $centerId))
            ->linkedToParentUser($user->id)
            ->exists();
    }

    /**
     * Same case, center and term scope as the parent treatment-plan list (`GoalController::index`),
     * so service types never point at goals the list will not return.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $goalQuery
     */
    public static function applyParentTreatmentCaseScope($goalQuery, User $user, int $centerId, int $caseId): void
    {
        $goalQuery
            ->where('goals.case_id', $caseId)
            ->whereIn('goals.category', self::TREATMENT_CATEGORIES)
            ->whereHas('case', function ($query) use ($centerId, $user) {
                $query->where('scases.center_id', $centerId);
                $query->usersRoles($user->roles, $user->id);
            });

        if (! Term::applyVisibleTerm($goalQuery, $user, $centerId, null, 'goals.term_id')) {
            $goalQuery->whereRaw('0 = 1');
        }
    }

    public static function categoryLabel(string $category): string
    {
        foreach (["tr.parent_service_types.{$category}", "tr.{$category}"] as $key) {
            if (Lang::has($key)) {
                return (string) __($key);
            }
        }

        return $category;
    }

    /**
     * Maps stored category values (MySQL compares them case/trailing-space insensitively)
     * to the canonical treatment category, or null when it is not a treatment category.
     */
    public static function canonicalTreatmentCategory(mixed $category): ?string
    {
        if (! is_string($category)) {
            return null;
        }

        $normalized = strtolower(trim($category));

        return self::isTreatmentCategory($normalized) ? $normalized : null;
    }

    /**
     * @param  iterable<int, mixed>  $categories
     * @return array<int, string>
     */
    public static function uniqueTreatmentCategories(iterable $categories): array
    {
        $unique = [];
        foreach ($categories as $category) {
            $canonical = self::canonicalTreatmentCategory($category);
            if ($canonical !== null) {
                $unique[$canonical] = $canonical;
            }
        }

        return array_values($unique);
    }

    /**
     * @param  iterable<int, mixed>  $categories
     * @param  (callable(string): string)|null  $label
     * @return array<int, array{value: string, title: string}>
     */
    public static function treatmentCategoryOptions(iterable $categories, ?callable $label = null): array
    {
        $label ??= fn (string $category) => self::categoryLabel($category);

        $items = array_map(
            fn (string $category) => ['value' => $category, 'title' => (string) $label($category)],
            self::uniqueTreatmentCategories($categories)
        );

        usort($items, fn ($a, $b) => strcmp($a['title'], $b['title']));

        return $items;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function treatmentCategoriesQuery(User $user, int $centerId, int $caseId)
    {
        $query = Goal::query()->select('goals.category')->distinct();
        self::applyParentTreatmentCaseScope($query, $user, $centerId, $caseId);

        return $query;
    }

    /**
     * @return array<int, array{value: string, title: string}>
     */
    public static function parentTreatmentCategoryOptions(User $user, int $centerId, int $caseId): array
    {
        return self::treatmentCategoryOptions(
            self::treatmentCategoriesQuery($user, $centerId, $caseId)->pluck('goals.category')
        );
    }

    /**
     * System / session log rows are bookkeeping, not messages for parents.
     *
     * @return array<int, string>
     */
    public static function systemMessageTypes(): array
    {
        return [
            Message::SYS_TYPE,
            Message::SYS_STARTED_SESSION,
            Message::SYS_ENDED_SESSION,
            Message::SYS_START_NOW_MEETING,
            Message::SYS_START_LATER_MEETING,
        ];
    }

    /**
     * Messages staff shared with parents, with or without attachments.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $messagesQuery
     */
    public static function applyParentVisibleMessageConstraints($messagesQuery): void
    {
        $messagesQuery
            ->where('parents_can_see', 1)
            ->where(function ($type) {
                $type->whereNull('type')
                    ->orWhereNotIn('type', self::systemMessageTypes());
            });
    }

    /**
     * @param  Message  $message
     */
    public static function messageIsParentVisible($message): bool
    {
        return (int) $message->parents_can_see === 1 && ! $message->isSystemMessage();
    }
}
