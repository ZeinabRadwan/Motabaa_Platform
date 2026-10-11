<?php

namespace App\Support;

use App\Models\QuestionnaireAnswer;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireTask;
use App\Models\QuestionnaireTaskStart;
use App\Models\QuestionnaireTaskSubmission;
use App\Models\Term;
use App\Models\User;
use App\Support\QuestionnaireTaskAudienceAccess;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QuestionnaireResponseAnalytics
{
    public const COMPLETION_ALL = 'all';

    public const COMPLETION_COMPLETED = 'completed';

    public const COMPLETION_INCOMPLETE = 'incomplete';

    private ?Collection $eligibleUserIdsCache = null;

    private ?Collection $completedUserIdsCache = null;

    private ?Collection $incompleteUserIdsCache = null;

    public function __construct(
        private readonly QuestionnaireTask $task,
        private readonly User $viewer,
        private readonly int $centerId,
        private readonly array $input,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function appliedFilters(): array
    {
        $termId = $this->audienceTermId();
        $term = Term::query()->find($termId);

        return [
            'term_id' => $termId,
            'term_title' => $term?->getNameAttribute(),
            'date_from' => $this->dateFrom(),
            'date_to' => $this->dateTo(),
            'completion_status' => $this->completionStatus(),
            'respondent_user_id' => $this->respondentUserId(),
        ];
    }

    /**
     * @return array<string, int|float|null>
     */
    public function kpis(): array
    {
        $targeted = $this->eligibleUserIds()->count();
        $completed = $this->matchingCompletedUserIds()->count();
        $startedIncomplete = $this->matchingIncompleteUserIds()->count();

        $rate = $targeted > 0 ? round(($completed / $targeted) * 100, 2) : 0.0;

        return [
            'targeted_users' => $targeted,
            'started_incomplete' => $startedIncomplete,
            'completed_submissions' => $completed,
            'response_rate_percent' => $rate,
        ];
    }

    /**
     * @param  Collection<int, QuestionnaireQuestion>  $questions
     * @return array<int, array<string, mixed>>
     */
    public function questionAnalytics(Collection $questions): array
    {
        $populationUserIds = $this->analyticsPopulationUserIds();
        $populationCount = $populationUserIds->count();
        $includeAnonymous = $this->shouldIncludeAnonymousRespondent();
        $anonymousInPopulation = $includeAnonymous && $this->anonymousMatchesFilters();

        $totalRespondents = $populationCount + ($anonymousInPopulation ? 1 : 0);

        $answerCountsByQuestion = $this->aggregatedAnswerCountsByQuestion($populationUserIds, $includeAnonymous && $anonymousInPopulation);

        $result = [];
        $index = 0;
        foreach ($questions as $question) {
            $index++;
            $title = $question->getNameAttribute();
            $type = (int) $question->type;
            $answered = (int) ($answerCountsByQuestion[$question->id]['answered'] ?? 0);
            $skipped = max(0, $totalRespondents - $answered);

            $row = [
                'question_id' => (int) $question->id,
                'question_number' => $index,
                'title' => $title,
                'type' => $type,
                'answered' => $answered,
                'skipped' => $skipped,
                'total_respondents' => $totalRespondents,
            ];

            $choiceOptions = $question->choiceOptions();

            if ($type === 2 && $choiceOptions) {
                $counts = array_fill_keys($choiceOptions, 0);
                foreach ($answerCountsByQuestion[$question->id]['by_value'] ?? [] as $value => $count) {
                    if (array_key_exists($value, $counts)) {
                        $counts[$value] = (int) $count;
                    }
                }
                $totalChoiceAnswers = array_sum($counts);
                $optionStats = [];
                $chartSeries = [];
                $chartLabels = [];
                foreach ($choiceOptions as $label) {
                    $count = $counts[$label] ?? 0;
                    $optionStats[] = [
                        'answer' => $label,
                        'count' => $count,
                        'percent' => $totalChoiceAnswers > 0 ? round(($count * 100) / $totalChoiceAnswers, 2) : 0.0,
                    ];
                    $chartLabels[] = $label;
                    $chartSeries[] = $count;
                }
                $row['option_stats'] = $optionStats;
                $row['chart_labels'] = $chartLabels;
                $row['chart_series'] = $chartSeries;
            } elseif ($type === 2) {
                $byValue = $answerCountsByQuestion[$question->id]['by_value'] ?? [];
                $nYes = (int) ($byValue['yes'] ?? 0);
                $nNo = (int) ($byValue['no'] ?? 0);
                $nAnswered = $nYes + $nNo;
                $row['n_yes'] = $nAnswered > 0 ? round(($nYes * 100) / $nAnswered, 2) : 0;
                $row['n_no'] = $nAnswered > 0 ? round(($nNo * 100) / $nAnswered, 2) : 0;
                $row['chart_series'] = [$nYes, $nNo];
            } elseif ($type === 1) {
                $row['answers'] = $this->textAnswersForQuestion($question, $populationUserIds, $includeAnonymous && $anonymousInPopulation);
            }

            $result[] = $row;
        }

        return $result;
    }

    /**
     * @param  Collection<int, QuestionnaireQuestion>  $questions
     * @return array{data: array<int, array<string, mixed>>, total: int, per_page: int, current_page: int}
     */
    public function paginatedSubmissions(Collection $questions, int $perPage, int $page): array
    {
        $rows = $this->buildSubmissionRows($questions);
        $total = count($rows);
        $perPage = max(1, min(100, $perPage));
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        return [
            'data' => array_slice($rows, $offset, $perPage),
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
        ];
    }

    /**
     * @param  Collection<int, QuestionnaireQuestion>  $questions
     * @return array<int, array<string, mixed>>
     */
    public function allSubmissionRows(Collection $questions): array
    {
        return $this->buildSubmissionRows($questions);
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function searchRespondents(string $query, int $limit = 20): array
    {
        $limit = max(1, min(50, $limit));
        $keywords = trim($query);
        $userIds = $this->respondentOptionUserIds();

        if ($userIds->isEmpty()) {
            return [];
        }

        $users = User::query()
            ->select('id', 'name')
            ->whereIn('id', $userIds->all())
            ->when($keywords !== '', function (Builder $builder) use ($keywords) {
                $like = '%'.mb_ereg_replace(' ', '%', $keywords).'%';
                $builder->where(function ($inner) use ($like) {
                    $inner->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return $users->map(fn (User $user) => [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
        ])->all();
    }

    public function assertTaskAccessible(): bool
    {
        return (int) $this->task->center_id === (int) $this->centerId
            && Term::abortIfInaccessible($this->viewer, $this->centerId, $this->task->term_id) === null;
    }

    public function assertFilterTermAccessible(): ?\Illuminate\Http\JsonResponse
    {
        $termId = $this->audienceTermId();

        return Term::abortIfInaccessible($this->viewer, $this->centerId, $termId);
    }

    private function audienceTermId(): int
    {
        $termId = (int) ($this->input['term_id'] ?? 0);

        return $termId > 0 ? $termId : (int) $this->task->term_id;
    }

    private function completionStatus(): string
    {
        $status = (string) ($this->input['completion_status'] ?? self::COMPLETION_ALL);
        if (! in_array($status, [self::COMPLETION_ALL, self::COMPLETION_COMPLETED, self::COMPLETION_INCOMPLETE], true)) {
            return self::COMPLETION_ALL;
        }

        return $status;
    }

    private function respondentUserId(): ?int
    {
        $id = (int) ($this->input['respondent_user_id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    private function dateFrom(): ?string
    {
        return $this->normalizeFilterDate($this->input['date_from'] ?? null);
    }

    private function dateTo(): ?string
    {
        return $this->normalizeFilterDate($this->input['date_to'] ?? null);
    }

    private function normalizeFilterDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value, QuestionnaireTask::AVAILABILITY_TIMEZONE)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function eligibleUserIds(): Collection
    {
        if ($this->eligibleUserIdsCache !== null) {
            return $this->eligibleUserIdsCache;
        }

        if ($this->task->isRoleAudience()) {
            $this->eligibleUserIdsCache = QuestionnaireTaskAudienceAccess::eligibleRoleUserIdsForTask($this->task);
        } else {
            $this->eligibleUserIdsCache = QuestionnaireParentTermAccess::eligibleParentUserIdsForTerm(
                $this->audienceTermId(),
                (int) $this->task->center_id,
            );
        }

        return $this->eligibleUserIdsCache;
    }

    private function submissionsTableExists(): bool
    {
        return Schema::hasTable('questionnaire_task_submissions');
    }

    private function completedUserIdsQuery(): Builder
    {
        if (! $this->submissionsTableExists()) {
            return QuestionnaireTaskSubmission::query()->whereRaw('1 = 0');
        }

        $query = QuestionnaireTaskSubmission::query()
            ->where('task_id', $this->task->id)
            ->where('user_id', '>', 0);

        $this->applySubmissionDateFilter($query, 'submitted_at');

        return $query;
    }

    private function matchingCompletedUserIds(): Collection
    {
        if ($this->completedUserIdsCache !== null) {
            return $this->completedUserIdsCache;
        }

        $ids = $this->completedUserIdsQuery()->pluck('user_id')->map(fn ($id) => (int) $id);
        $ids = $this->intersectScopeFilters($ids);

        if ($this->completionStatus() === self::COMPLETION_INCOMPLETE) {
            $ids = collect();
        }

        $this->completedUserIdsCache = $ids->values();

        return $this->completedUserIdsCache;
    }

    private function matchingIncompleteUserIds(): Collection
    {
        if ($this->incompleteUserIdsCache !== null) {
            return $this->incompleteUserIdsCache;
        }

        if ($this->completionStatus() === self::COMPLETION_COMPLETED) {
            $this->incompleteUserIdsCache = collect();

            return $this->incompleteUserIdsCache;
        }

        $completedIds = $this->submissionsTableExists()
            ? QuestionnaireTaskSubmission::query()
                ->where('task_id', $this->task->id)
                ->where('user_id', '>', 0)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
            : collect();

        $startedIds = collect();

        if (Schema::hasTable('questionnaire_task_starts')) {
            $startQuery = QuestionnaireTaskStart::query()
                ->where('task_id', $this->task->id);
            $this->applySubmissionDateFilter($startQuery, 'started_at');
            $startedIds = $startQuery->pluck('user_id')->map(fn ($id) => (int) $id);
        }

        if ($startedIds->isEmpty()) {
            $answerQuery = QuestionnaireAnswer::query()
                ->select('user_id', DB::raw('MAX(COALESCE(updated_at, created_at)) as last_answer_at'))
                ->where('task_id', $this->task->id)
                ->where('user_id', '>', 0)
                ->groupBy('user_id');
            $startedIds = collect($answerQuery->get())
                ->filter(function ($row) {
                    if (! $this->dateFrom() && ! $this->dateTo()) {
                        return true;
                    }
                    $moment = Carbon::parse($row->last_answer_at)->timezone(QuestionnaireTask::AVAILABILITY_TIMEZONE);
                    $date = $moment->format('Y-m-d');
                    if ($this->dateFrom() && $date < $this->dateFrom()) {
                        return false;
                    }
                    if ($this->dateTo() && $date > $this->dateTo()) {
                        return false;
                    }

                    return true;
                })
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id);
        }

        $ids = $startedIds->diff($completedIds)->values();
        $ids = $this->intersectScopeFilters($ids);

        $this->incompleteUserIdsCache = $ids->values();

        return $this->incompleteUserIdsCache;
    }

    private function analyticsPopulationUserIds(): Collection
    {
        $status = $this->completionStatus();
        if ($status === self::COMPLETION_COMPLETED) {
            return $this->matchingCompletedUserIds();
        }
        if ($status === self::COMPLETION_INCOMPLETE) {
            return $this->matchingIncompleteUserIds();
        }

        return $this->matchingCompletedUserIds()
            ->merge($this->matchingIncompleteUserIds())
            ->unique()
            ->values();
    }

    private function intersectScopeFilters(Collection $userIds): Collection
    {
        $eligible = $this->eligibleUserIds();
        $scoped = $userIds->intersect($eligible)->values();

        $respondentId = $this->respondentUserId();
        if ($respondentId !== null) {
            if (! $this->userIdInRespondentScope($respondentId)) {
                return collect();
            }

            return $scoped->filter(fn ($id) => (int) $id === $respondentId)->values();
        }

        return $scoped;
    }

    private function userIdInRespondentScope(int $userId): bool
    {
        return $this->respondentOptionUserIds()->contains($userId);
    }

    private function respondentOptionUserIds(): Collection
    {
        $eligible = $this->eligibleUserIds();
        $fromAnswers = QuestionnaireAnswer::query()
            ->where('task_id', $this->task->id)
            ->where('user_id', '>', 0)
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id);

        $fromSubmissions = $this->submissionsTableExists()
            ? QuestionnaireTaskSubmission::query()
                ->where('task_id', $this->task->id)
                ->where('user_id', '>', 0)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
            : collect();

        return $eligible->merge($fromAnswers)->merge($fromSubmissions)->unique()->values();
    }

    private function shouldIncludeAnonymousRespondent(): bool
    {
        if ($this->respondentUserId() !== null) {
            return false;
        }

        return $this->completionStatus() !== self::COMPLETION_COMPLETED;
    }

    private function anonymousMatchesFilters(): bool
    {
        if (! QuestionnaireAnswer::where('task_id', $this->task->id)->where('user_id', 0)->exists()) {
            return false;
        }

        if ($this->dateFrom() || $this->dateTo()) {
            $last = QuestionnaireAnswer::query()
                ->where('task_id', $this->task->id)
                ->where('user_id', 0)
                ->max(DB::raw('COALESCE(updated_at, created_at)'));
            if (! $last) {
                return false;
            }
            $date = Carbon::parse($last)->timezone(QuestionnaireTask::AVAILABILITY_TIMEZONE)->format('Y-m-d');
            if ($this->dateFrom() && $date < $this->dateFrom()) {
                return false;
            }
            if ($this->dateTo() && $date > $this->dateTo()) {
                return false;
            }
        }

        if ($this->audienceTermId() !== (int) $this->task->term_id) {
            return false;
        }

        return true;
    }

    /**
     * @param  Builder|\Illuminate\Database\Eloquent\Relations\Relation  $query
     */
    private function applySubmissionDateFilter($query, string $column): void
    {
        $from = $this->dateFrom();
        $to = $this->dateTo();
        $tz = QuestionnaireTask::AVAILABILITY_TIMEZONE;

        if ($from) {
            $query->where($column, '>=', Carbon::parse($from, $tz)->startOfDay());
        }
        if ($to) {
            $query->where($column, '<=', Carbon::parse($to, $tz)->endOfDay());
        }
    }

    /**
     * @return array<int, array{answered: int, by_value: array<string, int>}>
     */
    private function aggregatedAnswerCountsByQuestion(Collection $userIds, bool $includeAnonymous): array
    {
        if ($userIds->isEmpty() && ! $includeAnonymous) {
            return [];
        }

        $query = QuestionnaireAnswer::query()
            ->select('question_id', 'answer', DB::raw('COUNT(*) as aggregate'))
            ->where('task_id', $this->task->id)
            ->where(function ($inner) use ($userIds, $includeAnonymous) {
                if ($userIds->isNotEmpty()) {
                    $inner->whereIn('user_id', $userIds->all());
                }
                if ($includeAnonymous) {
                    $method = $userIds->isNotEmpty() ? 'orWhere' : 'where';
                    $inner->{$method}('user_id', 0);
                }
            })
            ->whereNotNull('answer')
            ->where('answer', '!=', '')
            ->groupBy('question_id', 'answer');

        $result = [];
        foreach ($query->get() as $row) {
            $questionId = (int) $row->question_id;
            $value = (string) $row->answer;
            if (! isset($result[$questionId])) {
                $result[$questionId] = ['answered' => 0, 'by_value' => []];
            }
            $count = (int) $row->aggregate;
            $result[$questionId]['answered'] += $count;
            $result[$questionId]['by_value'][$value] = ($result[$questionId]['by_value'][$value] ?? 0) + $count;
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function textAnswersForQuestion(QuestionnaireQuestion $question, Collection $userIds, bool $includeAnonymous): array
    {
        if ($userIds->isEmpty() && ! $includeAnonymous) {
            return [];
        }

        $submissionTimestamps = $this->submissionsTableExists()
            ? QuestionnaireTaskSubmission::query()
                ->where('task_id', $this->task->id)
                ->pluck('submitted_at', 'user_id')
            : collect();

        $answers = QuestionnaireAnswer::query()
            ->where('task_id', $this->task->id)
            ->where('question_id', $question->id)
            ->where(function ($inner) use ($userIds, $includeAnonymous) {
                if ($userIds->isNotEmpty()) {
                    $inner->whereIn('user_id', $userIds->all());
                }
                if ($includeAnonymous) {
                    $method = $userIds->isNotEmpty() ? 'orWhere' : 'where';
                    $inner->{$method}('user_id', 0);
                }
            })
            ->whereNotNull('answer')
            ->where('answer', '!=', '')
            ->with('user:id,name,email')
            ->orderByDesc('updated_at')
            ->get();

        $rows = [];
        foreach ($answers as $index => $answerRow) {
            $userId = (int) $answerRow->user_id;
            $user = $answerRow->user;
            $submittedAt = $userId > 0 ? ($submissionTimestamps->get($userId) ?? null) : null;

            $rows[] = array_merge(
                [
                    'id' => $index + 1,
                    'answer' => $answerRow->answer,
                ],
                $this->formatSubmissionTimestamp($submittedAt),
                [
                    'user_id' => $userId > 0 ? $userId : null,
                    'account_name' => $user?->name,
                    'account_email' => $user?->email,
                    'account_identifier' => $user?->email ?: $user?->name,
                    'account_available' => $userId > 0 && $user !== null,
                ]
            );
        }

        return $rows;
    }

    /**
     * @param  Collection<int, QuestionnaireQuestion>  $questions
     * @return array<int, array<string, mixed>>
     */
    private function buildSubmissionRows(Collection $questions): array
    {
        $questionsById = $questions->keyBy('id');
        $completedIds = $this->matchingCompletedUserIds();
        $incompleteIds = $this->matchingIncompleteUserIds();
        $status = $this->completionStatus();

        $userIds = collect();
        if ($status === self::COMPLETION_ALL || $status === self::COMPLETION_COMPLETED) {
            $userIds = $userIds->merge($completedIds);
        }
        if ($status === self::COMPLETION_ALL || $status === self::COMPLETION_INCOMPLETE) {
            $userIds = $userIds->merge($incompleteIds);
        }
        $userIds = $userIds->unique()->values();

        $result = [];

        if ($userIds->isNotEmpty()) {
            $submissions = $this->submissionsTableExists()
                ? QuestionnaireTaskSubmission::query()
                    ->where('task_id', $this->task->id)
                    ->whereIn('user_id', $userIds->all())
                    ->with('user:id,name,email')
                    ->orderByDesc('submitted_at')
                    ->get()
                    ->keyBy('user_id')
                : collect();

            $users = User::query()
                ->select('id', 'name', 'email')
                ->whereIn('id', $userIds->all())
                ->get()
                ->keyBy('id');

            foreach ($userIds as $userId) {
                $submission = $submissions->get($userId);
                $isCompleted = $submission !== null;
                if ($status === self::COMPLETION_COMPLETED && ! $isCompleted) {
                    continue;
                }
                if ($status === self::COMPLETION_INCOMPLETE && $isCompleted) {
                    continue;
                }

                $result[] = $this->formatSubmissionRow(
                    (int) $userId,
                    $submission?->submitted_at,
                    $questionsById,
                    $users->get($userId),
                    $submission?->id,
                    $isCompleted ? 'completed' : 'incomplete'
                );
            }
        }

        if ($this->shouldIncludeAnonymousRespondent() && $this->anonymousMatchesFilters()) {
            $result[] = $this->formatSubmissionRow(
                0,
                null,
                $questionsById,
                null,
                null,
                'incomplete'
            );
        }

        usort($result, function ($a, $b) {
            $aTime = $a['submitted_at'] ?? '';
            $bTime = $b['submitted_at'] ?? '';
            if ($aTime === $bTime) {
                return strcmp((string) ($a['account_name'] ?? ''), (string) ($b['account_name'] ?? ''));
            }

            return $aTime < $bTime ? 1 : -1;
        });

        return $result;
    }

    /**
     * @param  Collection<int, QuestionnaireQuestion>  $questionsById
     * @return array<string, mixed>
     */
    private function formatSubmissionRow(
        int $userId,
        $submittedAt,
        Collection $questionsById,
        ?User $user,
        ?int $submissionId,
        string $completionStatus
    ): array {
        $answersByQuestion = QuestionnaireAnswer::query()
            ->where('task_id', $this->task->id)
            ->where('user_id', $userId)
            ->get()
            ->keyBy('question_id');

        $answers = [];
        foreach ($questionsById as $question) {
            $answerRow = $answersByQuestion->get($question->id);
            $answers[] = [
                'question_id' => $question->id,
                'question_title' => $question->getNameAttribute(),
                'question_type' => (int) $question->type,
                'answer' => $answerRow?->answer,
            ];
        }

        return array_merge(
            [
                'id' => $submissionId,
                'user_id' => $userId > 0 ? $userId : null,
                'account_name' => $user?->name,
                'account_email' => $user?->email,
                'account_identifier' => $user?->email ?: $user?->name,
                'account_available' => $userId > 0 && $user !== null,
                'completion_status' => $completionStatus,
                'answers' => $answers,
            ],
            $this->formatSubmissionTimestamp($submittedAt)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSubmissionTimestamp($submittedAt): array
    {
        if ($submittedAt === null || $submittedAt === '') {
            return [
                'submitted_at' => null,
                'submitted_date' => null,
                'submitted_time' => null,
                'submitted_at_available' => false,
            ];
        }

        $moment = Carbon::parse($submittedAt)->timezone(QuestionnaireTask::AVAILABILITY_TIMEZONE);

        return [
            'submitted_at' => $moment->toIso8601String(),
            'submitted_date' => $moment->format('Y-m-d'),
            'submitted_time' => QuestionnaireTask::formatSubmissionDisplayTime($moment),
            'submitted_at_available' => true,
        ];
    }
}
