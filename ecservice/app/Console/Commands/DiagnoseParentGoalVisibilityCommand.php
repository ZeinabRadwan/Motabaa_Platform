<?php

namespace App\Console\Commands;

use App\Models\Goal;
use App\Models\Message;
use App\Models\SCase;
use App\Models\Term;
use App\Models\User;
use App\Models\System\System;
use App\Support\GoalParentVisibility;
use Illuminate\Console\Command;

/**
 * Read-only: explains, goal by goal, whether a beneficiary's goals are returned to a parent by
 * `GET /api/goals` and how many of their session messages the parent can read, next to what
 * staff see for the same case.
 */
class DiagnoseParentGoalVisibilityCommand extends Command
{
    public const VISIBLE = 'visible';

    public const DELETED = 'deleted';

    public const OTHER_TERM = 'other_term';

    public const NOT_LINKED = 'beneficiary_not_linked';

    protected $signature = 'parents:diagnose-goal-visibility
                            {case : Beneficiary (scases) id}
                            {--parent= : Parent user id (defaults to every parent linked to the case)}
                            {--category= : educational, treatment, independent or a single category value}
                            {--all : List visible goals too, not only the excluded ones}';

    protected $description = 'Explain which goals and session messages of a beneficiary a parent receives (read-only)';

    public function handle(): int
    {
        $case = SCase::withTrashed()->find((int) $this->argument('case'));
        if (! $case) {
            $this->error('Beneficiary #'.$this->argument('case').' not found.');

            return self::FAILURE;
        }

        $parents = $this->parents($case);
        if ($parents->isEmpty()) {
            $this->error("No parent account is linked to beneficiary #{$case->id} ({$case->name}).");

            return self::FAILURE;
        }

        $this->info("Beneficiary #{$case->id} {$case->name} (center #{$case->center_id})".($case->trashed() ? ' [deleted]' : ''));

        foreach ($parents as $parent) {
            $this->reportParent($case, $parent);
        }

        return self::SUCCESS;
    }

    /**
     * @return array{center_id: int|null, term_id: int|null, linked: bool, goals: array<int, array<string, mixed>>}
     */
    public static function diagnose(SCase $case, User $parent, ?string $category = null): array
    {
        $centerId = Term::resolveCenterId($parent, null);
        $centerId = $centerId ? (int) $centerId : null;
        $linked = GoalParentVisibility::parentCanAccessCase($parent, (int) $case->id, $centerId);
        $visibleTerm = Term::visibleTermId($parent, $centerId, null);
        $termId = $visibleTerm['ok'] ? $visibleTerm['term_id'] : 0;

        $goals = Goal::withTrashed()
            ->where('case_id', $case->id)
            ->when($category !== null && $category !== '', fn ($query) => self::applyCategory($query, $category))
            ->orderBy('category')
            ->orderBy('id')
            ->get();

        return [
            'center_id' => $centerId,
            'term_id' => $termId,
            'linked' => $linked,
            'goals' => $goals->map(function (Goal $goal) use ($termId, $linked) {
                $messages = $goal->messages()->get();
                $shared = $messages->filter(fn (Message $message) => GoalParentVisibility::messageIsParentVisible($message));
                $withAttachment = $shared->filter(fn (Message $message) => self::hasResolvableAttachment($message));

                return [
                    'id' => (int) $goal->id,
                    'category' => (string) $goal->category,
                    'term_id' => $goal->term_id ? (int) $goal->term_id : null,
                    'title' => (string) ($goal->title_local ?: $goal->title ?: $goal->custom_general_goal),
                    'staff_default_list' => ! $goal->trashed(),
                    'reason' => self::reason($goal, $termId, $linked),
                    'shared_messages' => $shared->count(),
                    'shared_with_attachment' => $withAttachment->count(),
                    'staff_only_messages' => $messages->count() - $shared->count(),
                    'visible_under_attachment_rule' => self::wasVisibleUnderAttachmentRule($goal, $messages, $withAttachment->isNotEmpty()),
                ];
            })->all(),
        ];
    }

    public static function reason(Goal $goal, ?int $parentTermId, bool $linked = true): string
    {
        if (! $linked) {
            return self::NOT_LINKED;
        }
        if ($goal->trashed()) {
            return self::DELETED;
        }
        if ($parentTermId !== null && (int) $goal->term_id !== $parentTermId) {
            return self::OTHER_TERM;
        }

        return self::VISIBLE;
    }

    /**
     * The rule this platform used before attachments became optional: the goal had to be started
     * (independent skills excepted) and have a shared message with an attachment that resolves.
     */
    private static function wasVisibleUnderAttachmentRule(Goal $goal, $messages, bool $hasSharedAttachment): bool
    {
        $started = $goal->category === System::TYPE_INDEPENDENT
            || $goal->started_session
            || $messages->contains(fn (Message $message) => $message->type === Message::SYS_STARTED_SESSION);

        return $started && $hasSharedAttachment;
    }

    private static function hasResolvableAttachment(Message $message): bool
    {
        return ! $message->skipsAttachmentLookup()
            && ($message->urlImage() || $message->urlVideo() || $message->urlFile());
    }

    private static function applyCategory($query, string $category): void
    {
        match ($category) {
            'treatment' => $query->whereIn('category', GoalParentVisibility::TREATMENT_CATEGORIES),
            default => $query->where('category', $category),
        };
    }

    private function parents(SCase $case)
    {
        if ($parentId = $this->option('parent')) {
            return User::query()->whereKey((int) $parentId)->get();
        }

        return User::query()
            ->whereIn('id', $case->parents()->pluck('users.id'))
            ->get();
    }

    private function reportParent(SCase $case, User $parent): void
    {
        $this->newLine();
        $this->info("Parent #{$parent->id} {$parent->name}");

        if (! isParentUser($parent)) {
            $this->warn('  This account does not have the parent role; the parent rules below do not apply to it.');
        }
        foreach (['access_education-goals', 'access_treatment-goals'] as $permission) {
            $this->line("  {$permission}: ".($parent->can($permission) ? 'yes' : 'NO (goal details are refused)'));
        }

        $result = self::diagnose($case, $parent, $this->option('category'));
        $this->line('  Parent center: #'.($result['center_id'] ?? '-').' | parent term: #'.($result['term_id'] ?? 'any'));

        if (! $result['linked']) {
            $this->error('  Beneficiary is not linked to this parent in this center: the parent receives no goals at all.');
        }

        $goals = collect($result['goals']);
        $rows = [];
        foreach ($goals->groupBy('category') as $category => $categoryGoals) {
            $active = $categoryGoals->where('staff_default_list', true);
            $visible = $categoryGoals->where('reason', self::VISIBLE);
            $rows[] = [
                $category,
                $active->count(),
                $active->where('term_id', $result['term_id'])->count(),
                $visible->count(),
                $visible->where('visible_under_attachment_rule', true)->count(),
                $visible->sum('shared_messages').' ('.$visible->sum('shared_with_attachment').' with attachment)',
                $categoryGoals->where('reason', '!=', self::VISIBLE)->countBy('reason')
                    ->map(fn ($count, $reason) => "{$reason}: {$count}")->implode(', '),
            ];
        }

        $this->table(
            [
                'Category', 'Staff (all terms, active)', 'Staff (parent term, active)', 'Parent goals',
                'Parent goals under old attachment rule', 'Shared messages on parent goals', 'Excluded by reason',
            ],
            $rows
        );

        $listed = $this->option('all') ? $goals : $goals->where('reason', '!=', self::VISIBLE);
        if ($listed->isNotEmpty()) {
            $this->table(
                ['Goal', 'Category', 'Term', 'Title', 'Reason', 'Shared msgs', 'With attachment', 'Staff-only msgs'],
                $listed->map(fn ($goal) => [
                    $goal['id'],
                    $goal['category'],
                    $goal['term_id'] ?? '-',
                    mb_strimwidth($goal['title'], 0, 60, '...'),
                    $goal['reason'],
                    $goal['shared_messages'],
                    $goal['shared_with_attachment'],
                    $goal['staff_only_messages'],
                ])->values()->all()
            );
        }
    }
}
