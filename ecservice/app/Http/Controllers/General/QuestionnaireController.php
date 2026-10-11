<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Questionnaire;
use App\Models\QuestionnaireTask;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireAnswer;
use App\Models\QuestionnaireTaskSubmission;
use App\Models\User;
use App\Exports\ExportQuestionnaireSubmissions;
use App\Models\System\ExportExcel;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\QuestionnaireResource;
use App\Http\Resources\QuestionnaireTaskResource;
use App\Http\Resources\QuestionnaireQuestionResource;
use App\Http\Resources\QuestionnaireAnswerResource;
use App\Models\Term;
use App\Http\Resources\TermResource;
use App\Models\QuestionnaireTaskStart;
use App\Models\System\PDF;
use App\Support\QuestionnaireAnalyticsPdfData;
use App\Support\QuestionnaireParentTermAccess;
use App\Support\QuestionnaireSectionStructure;
use App\Support\QuestionnaireTaskAudienceAccess;
use App\Support\QuestionnaireTaskNotifier;
use Spatie\Permission\Models\Role;
use App\Support\QuestionnaireResponseAnalytics;
use Carbon\Carbon;
use App\Models\Log as ActivityLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QuestionnaireController extends Controller
{

    public function index(Request $request){

        $perPage = resolvePerPage($request);

        $keywords = mb_ereg_replace(" ", "%", getFTS($request->q));

        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);

        $tasks = QuestionnaireTask::select('questionnaires_tasks.*')
        ->where('questionnaires_tasks.center_id', $center)
        ->when(
            $request->q,
            fn ($q) => $q->where('title', 'like',"%{$keywords}%")
        )
        ->where(function ($query) use ($request) {
            if($request->from_date && $request->to_date) {
                $query->orWhereBetween("starts_at", [$request->from_date, $request->to_date]);
                $query->orWhereBetween("ends_at", [$request->from_date, $request->to_date]);
            }
            else if($request->from_date) {
                $query->whereDate("starts_at", "<=", $request->from_date);
            }
            else if($request->to_date) {
                $query->whereDate("ends_at", ">=", $request->to_date);
            }
        })
        ->when(
            $request->status && $request->status == 'all',
            fn ($q) => $q->withTrashed()
        )
        ->when(
            $request->status && $request->status == 'inactive',
            fn ($q) => $q->onlyTrashed()
        );

        if (!Term::applyVisibleTerm($tasks, $user, $center, $request->term_id)) {
            return Term::forbiddenResponse();
        }

        $tasks = $tasks->paginate($perPage);
        return apiPaginateResponse($tasks, QuestionnaireTaskResource::collection($tasks));
    }

    public function questionnaireTask(Request $request){
            
        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);
        if (! $user || ! $center) {
            return apiResponse(false);
        }

        $available = QuestionnaireTaskAudienceAccess::availableOpenTasksForUser($user, (int) $center);
        $first = $available->first();

        return apiResponse($first ?: false);
    }

    public function respondentAvailableTasks(Request $request)
    {
        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);
        if (! $user || ! $center) {
            return apiResponse([]);
        }

        $tasks = QuestionnaireTaskAudienceAccess::availableOpenTasksForUser($user, (int) $center);

        return apiResponse(QuestionnaireTaskResource::collection($tasks));
    }

    public function targetAudienceRoles(Request $request)
    {
        $user = auth()->user();
        if (! $user) {
            return error(403);
        }

        $roles = Role::query()
            ->select('id', 'name', 'default_name')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => (int) $role->id,
                'name' => (string) $role->name,
                'default_name' => (string) $role->default_name,
            ])
            ->values()
            ->all();

        return apiResponse($roles);
    }

    public function show(Request $request, $task){

        $task = QuestionnaireTask::find($task);

        if($task) {

            $user = auth()->user();
            $center = Term::resolveCenterId($user, $request->center_id);

            $task = $task->load('questionnaire');
            if ($task->center_id == $center) {
                $payload = (new QuestionnaireTaskResource($task))->resolve();
                $answerCounts = QuestionnaireAnswer::where('task_id', $task->id)
                    ->selectRaw('question_id, COUNT(*) as aggregate')
                    ->groupBy('question_id')
                    ->pluck('aggregate', 'question_id');

                $sections = QuestionnaireSectionStructure::buildAdminSectionsPayload(
                    (int) $task->questionnaire_id,
                    $answerCounts
                );

                $flatQuestions = [];
                foreach ($sections as $sectionRow) {
                    foreach ($sectionRow['questions'] ?? [] as $questionRow) {
                        $flatQuestions[] = $questionRow;
                    }
                }

                $payload['sections'] = $sections;
                $payload['questions'] = $flatQuestions;
                $task = $payload;
            } else {
                $task = null;
            }
        }

        return apiResponse($task);
    }

    public function put(Request $request){

        $startsAt = $this->normalizeTaskBoundaryDate($request->starts_at);
        $endsAt = $this->normalizeTaskBoundaryDate($request->ends_at);
        if (! $startsAt || ! $endsAt || $startsAt > $endsAt) {
            return response()->json(['errors' => ['error' => [__('validation.Start Date after End Date')]]], 422);
        }
        
        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);
        if (! $center) {
            return response()->json(['errors' => ['error' => [__("validation.You don't belong to any center")]]], 422);
        }

        $audienceType = QuestionnaireTaskAudienceAccess::normalizeAudienceType(
            $request->input('target_audience_type', QuestionnaireTask::AUDIENCE_PARENT)
        );
        [$audienceOk, $audienceError] = QuestionnaireTaskAudienceAccess::validateAudienceInput(
            $audienceType,
            $request->term_id,
            $request->target_role_id,
            $user,
            (int) $center
        );
        if (! $audienceOk) {
            return $audienceError;
        }

        if($request->id>0) {

            $message = 'Updated successfully.';
            $task = QuestionnaireTask::withTrashed()->find($request->id);
        }
        else {

            $questionnaire = Questionnaire::first();

            if(!$questionnaire) {
                return response()->json(['errors' => ['error' => [__("validation.Questionnaire does not exist")]]], 422);
            }

            if ($audienceType === QuestionnaireTask::AUDIENCE_PARENT) {
                $taskExists = QuestionnaireTask::where('questionnaire_id', $questionnaire->id)
                    ->where('center_id', $center)
                    ->where('term_id', $request->term_id)
                    ->exists();
                if ($taskExists) {
                    return response()->json(['errors' => ['error' => [__("validation.questionnaires.Questionnaire already exists")]]], 422);
                }
            }

            $message = 'Added successfully.';
            $task = new QuestionnaireTask();
            $task->questionnaire_id = $questionnaire->id;
            $task->center_id = $center;
        }

        $this->applyTaskAudienceFromRequest($task, $request, $audienceType);

        $task->starts_at = $startsAt;
        $task->ends_at = $endsAt;
        $task->save();

        $this->syncQuestionnaireParentEditSetting($task, $request);

        $type = 'add_questionnaire';
        if($request->id>0)
            $type = 'edit_questionnaire';

        ActivityLog::add($type, $task, $task->id, '');

        QuestionnaireTaskNotifier::syncForTask($task->fresh(['questionnaire']));

        return response()->json([
            'message' => $message,
            'status' => true,
            'data' => [
                'id' => $task->id,
                'is_open' => $task->isOpen(),
                'availability' => $task->availabilityWindow(),
            ],
        ], 200);
    }

    /**
     * Create a new questionnaire template with custom questions and a term task.
     */
    public function putCustom(Request $request)
    {
        $startsAt = $this->normalizeTaskBoundaryDate($request->starts_at);
        $endsAt = $this->normalizeTaskBoundaryDate($request->ends_at);
        if (! $startsAt || ! $endsAt || $startsAt > $endsAt) {
            return response()->json(['errors' => ['error' => [__('validation.Start Date after End Date')]]], 422);
        }

        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);
        if (! $center) {
            return response()->json(['errors' => ['error' => [__("validation.You don't belong to any center")]]], 422);
        }

        $audienceType = QuestionnaireTaskAudienceAccess::normalizeAudienceType(
            $request->input('target_audience_type', QuestionnaireTask::AUDIENCE_PARENT)
        );
        [$audienceOk, $audienceError] = QuestionnaireTaskAudienceAccess::validateAudienceInput(
            $audienceType,
            $request->term_id,
            $request->target_role_id,
            $user,
            (int) $center
        );
        if (! $audienceOk) {
            return $audienceError;
        }

        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            return response()->json(['errors' => ['error' => [__('validation.required', ['attribute' => 'title'])]]], 422);
        }

        $sectionsInput = $request->input('sections');
        $legacyQuestions = $request->has('sections') ? null : $request->input('questions', []);
        $normalizedSections = QuestionnaireSectionStructure::normalizeSectionsInput($sectionsInput, $legacyQuestions);
        if ($normalizedSections instanceof \Illuminate\Http\JsonResponse) {
            return $normalizedSections;
        }

        $taskId = (int) $request->input('id', 0);

        if ($taskId > 0) {
            $task = QuestionnaireTask::withTrashed()->with('questionnaire')->find($taskId);
            if (! $task || $task->center_id != $center) {
                return response()->json(['errors' => ['error' => [__('validation.Questionnaire does not exist')]]], 422);
            }

            if ($audienceType === QuestionnaireTask::AUDIENCE_PARENT) {
                $duplicate = QuestionnaireTask::where('center_id', $center)
                    ->where('questionnaire_id', $task->questionnaire_id)
                    ->where('term_id', $request->term_id)
                    ->where('id', '!=', $task->id)
                    ->exists();
                if ($duplicate) {
                    return response()->json(['errors' => ['error' => [__('validation.questionnaires.Questionnaire already exists')]]], 422);
                }
            }

            DB::beginTransaction();
            try {
                $questionnaire = $task->questionnaire;
                if (! $questionnaire) {
                    DB::rollBack();

                    return response()->json(['errors' => ['error' => [__('validation.Questionnaire does not exist')]]], 422);
                }

                $questionnaire->title = $title;
                $questionnaire->title_local = $title;
                $questionnaire->allow_parent_edit_after_submit = $this->requestAllowsParentEditAfterSubmit($request);
                $questionnaire->save();

                $this->applyTaskAudienceFromRequest($task, $request, $audienceType);
                $task->starts_at = $startsAt;
                $task->ends_at = $endsAt;
                $task->save();

                $syncError = QuestionnaireSectionStructure::sync($task, $questionnaire, $normalizedSections);
                if ($syncError instanceof \Illuminate\Http\JsonResponse) {
                    DB::rollBack();

                    return $syncError;
                }

                DB::commit();
            } catch (\Throwable $exception) {
                DB::rollBack();
                throw $exception;
            }

            ActivityLog::add('edit_questionnaire', $task, $task->id, 'custom');

            $task->refresh();
            QuestionnaireTaskNotifier::syncForTask($task->load('questionnaire'));

            return response()->json([
                'message' => 'Updated successfully.',
                'status' => true,
                'data' => [
                    'id' => $task->id,
                    'is_open' => $task->isOpen(),
                    'availability' => $task->availabilityWindow(),
                ],
            ], 200);
        }

        $task = DB::transaction(function () use ($request, $center, $title, $normalizedSections, $startsAt, $endsAt, $audienceType) {
            $questionnaire = Questionnaire::create([
                'title' => $title,
                'title_local' => $title,
                'allow_parent_edit_after_submit' => $this->requestAllowsParentEditAfterSubmit($request),
            ]);

            $task = new QuestionnaireTask();
            $task->questionnaire_id = $questionnaire->id;
            $task->center_id = $center;
            $task->starts_at = $startsAt;
            $task->ends_at = $endsAt;
            if ($audienceType === QuestionnaireTask::AUDIENCE_PARENT) {
                $task->target_audience_type = QuestionnaireTask::AUDIENCE_PARENT;
                $task->term_id = (int) $request->term_id;
                $task->target_role_id = null;
            } else {
                $task->target_audience_type = QuestionnaireTask::AUDIENCE_ROLE;
                $task->term_id = null;
                $task->target_role_id = (int) $request->target_role_id;
            }
            $task->save();

            $syncError = QuestionnaireSectionStructure::sync($task, $questionnaire, $normalizedSections);
            if ($syncError instanceof \Illuminate\Http\JsonResponse) {
                throw new \Illuminate\Http\Exceptions\HttpResponseException($syncError);
            }

            return $task;
        });

        ActivityLog::add('add_questionnaire', $task, $task->id, 'custom');
        QuestionnaireTaskNotifier::syncForTask($task->load('questionnaire'));

        return response()->json([
            'message' => 'Added successfully.',
            'status' => true,
            'data' => [
                'id' => $task->id,
                'is_open' => $task->isOpen(),
                'availability' => $task->availabilityWindow(),
            ],
        ], 200);
    }

    public function delete($id) {

        $record = QuestionnaireTask::find($id);
        $denied = Term::abortIfInaccessible(auth()->user(), $record?->center_id, $record?->term_id);
        if ($denied) {
            return $denied;
        }
        $record->delete();
        ActivityLog::add('delete_questionnaire', $record, $record->id, '');
        return response()->json(['message' => 'Deleted successfully.', 'status' => true]);
    }

    public function restore($id) {

        $record = QuestionnaireTask::with('questionnaire')->withTrashed()->find($id);
        $denied = Term::abortIfInaccessible(auth()->user(), $record?->center_id, $record?->term_id);
        if ($denied) {
            return $denied;
        }

        $task = QuestionnaireTask::where('center_id', $record->center_id)
        ->where('questionnaire_id', $record->questionnaire_id)
        ->where('term_id', $record->term_id)
        ->exists();
        if($task) {
            return response()->json(['errors' => ['error' => [__('validation.questionnaires.Questionnaire already exists')]]], 422);
        }

        $record->restore();
        ActivityLog::add('restore_questionnaire', $record, $record->id, '');
        return response()->json(['message' => 'Restored successfully.', 'status' => true]);
    }

    public function fetchQuestions(Request $request, QuestionnaireTask $task){

        $task->loadMissing('questionnaire');

        if (! $task->isOpen()) {
            return response()->json([
                'errors' => ['error' => [__('validation.questionnaires.Questionnaire closed')]],
                'availability' => $task->availabilityWindow(),
            ], 422);
        }

        $user = auth()->user();
        $denied = QuestionnaireTaskAudienceAccess::denyResponseIfBlocked($user, $task);
        if ($denied) {
            return $denied;
        }

        $questions = QuestionnaireQuestion::where('questionnaires_questions.questionnaire_id', $task->questionnaire_id);

        if ($user) {

            $questions->leftJoin('questionnaires_answers', function($join) use($user, $task) {
                $join->on('questionnaires_answers.question_id', '=', 'questionnaires_questions.id');
                $join->where('questionnaires_answers.task_id', $task->id);
                $join->where('questionnaires_answers.user_id', $user->id);
            })
            ->select(
                'questionnaires_questions.*', 
                'questionnaires_answers.id as answer_id', 
                'questionnaires_answers.answer as answer'
            );
        }

        $questions = $questions
            ->orderBy('questionnaires_questions.sort_order')
            ->orderBy('questionnaires_questions.id', 'ASC')
            ->get();

        $userHasSubmitted = $user
            ? QuestionnaireTaskSubmission::where('task_id', $task->id)->where('user_id', $user->id)->exists()
            : false;
        $allowEditAfterSubmit = $task->allowsParentEditAfterSubmit();
        $canEditSubmission = $userHasSubmitted && $allowEditAfterSubmit;

        if ($user && ! $userHasSubmitted && Schema::hasTable('questionnaire_task_starts')) {
            QuestionnaireTaskStart::firstOrCreate(
                ['task_id' => $task->id, 'user_id' => $user->id],
                ['started_at' => now()]
            );
        }

        $sections = QuestionnaireSectionStructure::buildRespondentSectionsPayload($questions);

        return apiResponse([
            'taskID' => $task->id,
            'questions' => $questions,
            'sections' => $sections,
            'questionnaire_title' => $task->questionnaire->getNameAttribute(),
            'user_has_submitted' => $userHasSubmitted,
            'allow_edit_after_submit' => $allowEditAfterSubmit,
            'can_edit_submission' => $canEditSubmission,
            'read_only' => $userHasSubmitted && ! $allowEditAfterSubmit,
        ]);
    }

    public function fetchQuestionsWithAnswers(Request $request)
    {
        $payload = $this->buildQuestionnaireAnalyticsPayload($request, (int) $request->task_id);
        if ($payload instanceof \Illuminate\Http\JsonResponse) {
            return $payload;
        }

        return apiResponse($payload);
    }

    public function questionnaireAnalytics(Request $request, $task)
    {
        $payload = $this->buildQuestionnaireAnalyticsPayload($request, (int) $task);
        if ($payload instanceof \Illuminate\Http\JsonResponse) {
            return $payload;
        }

        return apiResponse($payload);
    }

    public function searchQuestionnaireRespondents(Request $request, $task)
    {
        $analytics = $this->makeQuestionnaireAnalytics($request, (int) $task);
        if ($analytics instanceof \Illuminate\Http\JsonResponse) {
            return $analytics;
        }

        $denied = $analytics->assertFilterTermAccessible();
        if ($denied) {
            return $denied;
        }

        $q = trim((string) ($request->q ?? ''));

        return apiResponse($analytics->searchRespondents($q, (int) ($request->limit ?? 20)));
    }

    /**
     * @return array<string, mixed>|\Illuminate\Http\JsonResponse
     */
    private function buildQuestionnaireAnalyticsPayload(Request $request, int $taskId)
    {
        $analytics = $this->makeQuestionnaireAnalytics($request, $taskId);
        if ($analytics instanceof \Illuminate\Http\JsonResponse) {
            return $analytics;
        }

        $denied = $analytics->assertFilterTermAccessible();
        if ($denied) {
            return $denied;
        }

        $task = QuestionnaireTask::find($taskId);
        $questions = QuestionnaireQuestion::where('questionnaire_id', $task->questionnaire_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $perPage = resolvePerPage($request);
        $page = max(1, (int) ($request->page ?? 1));

        return [
            'kpis' => $analytics->kpis(),
            'filters' => $analytics->appliedFilters(),
            'questions' => $analytics->questionAnalytics($questions),
            'submissions' => $analytics->paginatedSubmissions($questions, $perPage, $page),
        ];
    }

    /**
     * @return QuestionnaireResponseAnalytics|\Illuminate\Http\JsonResponse
     */
    private function makeQuestionnaireAnalytics(Request $request, int $taskId)
    {
        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);
        $task = QuestionnaireTask::find($taskId);

        if (! $task || (int) $task->center_id !== (int) $center) {
            return response()->json(['errors' => ['error' => [__('validation.Questionnaire does not exist')]]], 422);
        }

        $denied = Term::abortIfInaccessible($user, $center, $task->term_id);
        if ($denied) {
            return $denied;
        }

        return new QuestionnaireResponseAnalytics($task, $user, (int) $center, $request->all());
    }

    public function exportQuestionnaireResponses(Request $request, $task)
    {
        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);
        $task = QuestionnaireTask::with('questionnaire')->find($task);

        if (! $task || $task->center_id != $center) {
            return response()->json(['errors' => ['error' => [__('validation.Questionnaire does not exist')]]], 422);
        }

        if (! Schema::hasTable('questionnaire_task_submissions')) {
            return response()->json([
                'errors' => ['error' => [__('validation.questionnaires.Submissions table missing run migrate')]],
            ], 422);
        }

        try {
            $analytics = new QuestionnaireResponseAnalytics($task, $user, (int) $center, $request->all());
            $denied = $analytics->assertFilterTermAccessible();
            if ($denied) {
                return $denied;
            }

            $questions = QuestionnaireQuestion::where('questionnaire_id', $task->questionnaire_id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            $submissions = $analytics->allSubmissionRows($questions);
            [$headings, $rows] = $this->buildQuestionnaireExportRows($questions, $submissions);

            $questionnaireTitle = $task->questionnaire ? $task->questionnaire->getNameAttribute() : 'Questionnaire';
            $fileName = ExportExcel::defaultFileName($questionnaireTitle);
            $token = ExportExcel::saveExcel(
                new ExportQuestionnaireSubmissions($headings, $rows, $questionnaireTitle),
                $fileName
            );

            return success(['url' => exportDownloadUrl($token)]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'errors' => ['error' => [__('validation.questionnaires.Export failed')]],
            ], 422);
        }
    }

    public function exportQuestionnaireAnalyticsPdf(Request $request, $task)
    {
        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);
        $task = QuestionnaireTask::with(['questionnaire', 'term'])->find($task);

        if (! $task || (int) $task->center_id !== (int) $center) {
            return response()->json(['errors' => ['error' => [__('validation.Questionnaire does not exist')]]], 422);
        }

        $denied = Term::abortIfInaccessible($user, $center, $task->term_id);
        if ($denied) {
            return $denied;
        }

        try {
            $analytics = new QuestionnaireResponseAnalytics($task, $user, (int) $center, $request->all());
            $denied = $analytics->assertFilterTermAccessible();
            if ($denied) {
                return $denied;
            }

            $questions = QuestionnaireQuestion::where('questionnaire_id', $task->questionnaire_id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            $generatedAt = Carbon::now(QuestionnaireTask::AVAILABILITY_TIMEZONE);
            $filters = $analytics->appliedFilters();
            $filterLabels = $this->questionnaireAnalyticsFilterLabels($filters);
            $data = QuestionnaireAnalyticsPdfData::build(
                $task,
                $filterLabels,
                $analytics->kpis(),
                $analytics->questionAnalytics($questions),
                $generatedAt->format('Y-m-d H:i:s'),
            );

            $fileName = PDF::defaultFileName(($data['title'] ?? 'questionnaire').'-analytics');
            $token = PDF::savePdf('questionnaires.analytics_report', $data, $fileName);

            return success(['url' => exportDownloadUrl($token)]);
        } catch (\Throwable $exception) {
            Log::error('Questionnaire analytics PDF export failed', [
                'task_id' => $task->id,
                'center_id' => $center,
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);
            report($exception);

            return response()->json([
                'errors' => ['error' => [__('validation.questionnaires.Export failed')]],
            ], 422);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string|null>
     */
    private function questionnaireAnalyticsFilterLabels(array $filters): array
    {
        $completion = (string) ($filters['completion_status'] ?? QuestionnaireResponseAnalytics::COMPLETION_ALL);
        $completionLabel = match ($completion) {
            QuestionnaireResponseAnalytics::COMPLETION_COMPLETED => __('tr.questionnaires.filter_completion_completed'),
            QuestionnaireResponseAnalytics::COMPLETION_INCOMPLETE => __('tr.questionnaires.filter_completion_incomplete'),
            default => __('tr.questionnaires.filter_completion_all'),
        };

        $period = null;
        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $period = trim(($filters['date_from'] ?? '').' — '.($filters['date_to'] ?? ''), ' —');
        }

        return [
            'term' => $filters['term_title'] ?? null,
            'period' => $period,
            'completion' => $completionLabel,
            'respondent' => isset($filters['respondent_user_id']) && $filters['respondent_user_id']
                ? (User::query()->where('id', $filters['respondent_user_id'])->value('name') ?: null)
                : null,
        ];
    }

    public function addAnswers(Request $request, QuestionnaireTask $task){

        if (! $task->isOpen()) {
            return response()->json([
                'errors' => ['error' => [__('validation.questionnaires.Questionnaire closed')]],
                'availability' => $task->availabilityWindow(),
            ], 422);
        }

        $user = auth()->user();
        $userID = $user ? (int) $user->id : 0;

        if ($userID > 0) {
            $denied = QuestionnaireTaskAudienceAccess::denyResponseIfBlocked($user, $task);
            if ($denied) {
                return $denied;
            }

            return $this->addAnswersForAuthenticatedUser($request, $task, $userID);
        }

        if ($task->isRoleAudience()) {
            return QuestionnaireTaskAudienceAccess::accessDeniedResponse();
        }

        return $this->addAnswersForAnonymousUser($request, $task);
    }

    private function addAnswersForAuthenticatedUser(Request $request, QuestionnaireTask $task, int $userID)
    {
        $task->loadMissing('questionnaire');

        try {
            $result = DB::transaction(function () use ($request, $task, $userID) {
                $existingSubmission = QuestionnaireTaskSubmission::where('task_id', $task->id)
                    ->where('user_id', $userID)
                    ->lockForUpdate()
                    ->first();

                if ($existingSubmission && ! $task->allowsParentEditAfterSubmit()) {
                    return 'already_submitted';
                }

                foreach ($request->all() as $key => $value) {
                    if (! is_numeric($key)) {
                        continue;
                    }

                    $questionId = (int) $key;
                    $answer = QuestionnaireAnswer::where('task_id', $task->id)
                        ->where('user_id', $userID)
                        ->where('question_id', $questionId)
                        ->first();

                    if ($value !== null && $value !== '') {
                        if (! $answer) {
                            $answer = new QuestionnaireAnswer();
                            $answer->task_id = $task->id;
                            $answer->user_id = $userID;
                            $answer->question_id = $questionId;
                        }

                        $answer->answer = $value;
                        $answer->save();
                    } elseif ($answer) {
                        $answer->delete();
                    }
                }

                if ($existingSubmission) {
                    $existingSubmission->last_edited_at = now();
                    $existingSubmission->save();

                    return 'updated';
                }

                QuestionnaireTaskSubmission::create([
                    'task_id' => $task->id,
                    'user_id' => $userID,
                    'submitted_at' => now(),
                ]);

                $task->increment('count');

                return 'created';
            });
        } catch (\Illuminate\Database\QueryException $exception) {
            if ($this->isDuplicateSubmissionException($exception)) {
                return response()->json([
                    'errors' => ['error' => [__('validation.questionnaires.Questionnaire already submitted')]],
                ], 422);
            }

            throw $exception;
        }

        if ($result === 'already_submitted') {
            return response()->json([
                'errors' => ['error' => [__('validation.questionnaires.Questionnaire already submitted')]],
            ], 422);
        }

        $message = $result === 'updated' ? 'Updated successfully.' : 'Added successfully.';

        return response()->json(['message' => $message, 'status' => true], 200);
    }

    private function addAnswersForAnonymousUser(Request $request, QuestionnaireTask $task)
    {
        $isNew = false;

        foreach ($request->all() as $key => $value) {
            if (! is_numeric($key)) {
                continue;
            }

            $answer = QuestionnaireAnswer::where('task_id', $task->id)
                ->where('user_id', 0)
                ->where('question_id', (int) $key)
                ->first();

            if ($value !== null && $value !== '') {
                if (! $answer) {
                    $isNew = true;
                    $answer = new QuestionnaireAnswer();
                    $answer->task_id = $task->id;
                    $answer->user_id = 0;
                    $answer->question_id = (int) $key;
                }

                $answer->answer = $value;
                $answer->save();
            } elseif ($answer) {
                $answer->delete();
            }
        }

        if ($isNew) {
            $task->increment('count');
        }

        return response()->json([
            'message' => $isNew ? 'Added successfully.' : 'Updated successfully.',
            'status' => true,
        ], 200);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\QuestionnaireQuestion>  $questions
     * @return array<int, array<string, mixed>>
     */
    private function buildQuestionnaireTaskSubmissions(QuestionnaireTask $task, $questions): array
    {
        $questionsById = $questions->keyBy('id');
        $submissions = QuestionnaireTaskSubmission::where('task_id', $task->id)
            ->with('user:id,name,email')
            ->orderByDesc('submitted_at')
            ->get();

        $result = [];
        $submissionUserIds = [];

        foreach ($submissions as $submission) {
            $submissionUserIds[] = (int) $submission->user_id;
            $result[] = $this->formatQuestionnaireSubmissionRow(
                $task,
                (int) $submission->user_id,
                $submission->submitted_at,
                $questionsById,
                $submission->user,
                (int) $submission->id
            );
        }

        $legacyUserIds = QuestionnaireAnswer::where('task_id', $task->id)
            ->where('user_id', '>', 0)
            ->whereNotIn('user_id', $submissionUserIds)
            ->distinct()
            ->pluck('user_id');

        foreach ($legacyUserIds as $userId) {
            $result[] = $this->formatQuestionnaireSubmissionRow(
                $task,
                (int) $userId,
                null,
                $questionsById,
                User::query()->select('id', 'name', 'email')->find($userId),
                null
            );
        }

        if (QuestionnaireAnswer::where('task_id', $task->id)->where('user_id', 0)->exists()) {
            $result[] = $this->formatQuestionnaireSubmissionRow(
                $task,
                0,
                null,
                $questionsById,
                null,
                null
            );
        }

        return $result;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\QuestionnaireQuestion>  $questionsById
     * @return array<string, mixed>
     */
    private function formatQuestionnaireSubmissionRow(
        QuestionnaireTask $task,
        int $userId,
        $submittedAt,
        $questionsById,
        ?User $user,
        ?int $submissionId
    ): array {
        $answersByQuestion = QuestionnaireAnswer::where('task_id', $task->id)
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
                'answers' => $answers,
            ],
            $this->formatQuestionnaireSubmissionTimestamp($submittedAt)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formatQuestionnaireSubmissionTimestamp($submittedAt): array
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

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $submissionTimestampsByUser
     * @return array<string, mixed>
     */
    private function respondentMetadataForAnswerRow(QuestionnaireAnswer $answerRow, $submissionTimestampsByUser): array
    {
        $userId = (int) $answerRow->user_id;
        $user = $answerRow->user;
        $submittedAt = $userId > 0 ? ($submissionTimestampsByUser->get($userId) ?? null) : null;

        return array_merge(
            [
                'user_id' => $userId > 0 ? $userId : null,
                'account_name' => $user?->name,
                'account_email' => $user?->email,
                'account_identifier' => $user?->email ?: $user?->name,
                'account_available' => $userId > 0 && $user !== null,
            ],
            $this->formatQuestionnaireSubmissionTimestamp($submittedAt)
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\QuestionnaireQuestion>  $questions
     * @param  array<int, array<string, mixed>>  $submissions
     * @return array{0: array<int, string>, 1: array<int, array<int, string|null>>}
     */
    private function buildQuestionnaireExportRows($questions, array $submissions): array
    {
        $unavailable = __('tr.questionnaires.metadata_unavailable');
        $headings = [
            __('tr.questionnaires.export_account_name'),
            __('tr.questionnaires.export_submission_date'),
            __('tr.questionnaires.export_submission_time'),
        ];

        $questionColumns = [];
        $usedTitles = [];
        foreach ($questions as $question) {
            $title = trim((string) $question->getNameAttribute());
            if ($title === '') {
                $title = 'Question '.$question->id;
            }

            $heading = $title;
            $suffix = 2;
            while (isset($usedTitles[$heading])) {
                $heading = $title.' ('.$suffix.')';
                $suffix++;
            }
            $usedTitles[$heading] = true;
            $questionColumns[] = [
                'question_id' => (int) $question->id,
                'heading' => $heading,
            ];
            $headings[] = $heading;
        }

        $rows = [];
        foreach ($submissions as $submission) {
            $answersByQuestionId = collect($submission['answers'] ?? [])->keyBy('question_id');

            $row = [
                ! empty($submission['account_available'])
                    ? (string) ($submission['account_name'] ?? $unavailable)
                    : $unavailable,
                ! empty($submission['submitted_at_available'])
                    ? (string) ($submission['submitted_date'] ?? $unavailable)
                    : $unavailable,
                ! empty($submission['submitted_at_available'])
                    ? (string) ($submission['submitted_time'] ?? $unavailable)
                    : $unavailable,
            ];

            foreach ($questionColumns as $column) {
                $answer = $answersByQuestionId->get($column['question_id']);
                $value = is_array($answer) ? ($answer['answer'] ?? null) : null;
                $row[] = ($value === null || $value === '') ? '' : (string) $value;
            }

            $rows[] = $row;
        }

        return [$headings, $rows];
    }

    private function isDuplicateSubmissionException(\Illuminate\Database\QueryException $exception): bool
    {
        $errorCode = (int) ($exception->errorInfo[1] ?? 0);

        return $errorCode === 1062;
    }

    private function normalizeTaskBoundaryDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value, QuestionnaireTask::AVAILABILITY_TIMEZONE)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function applyTaskAudienceFromRequest(QuestionnaireTask $task, Request $request, string $audienceType): void
    {
        $task->target_audience_type = $audienceType;
        if ($audienceType === QuestionnaireTask::AUDIENCE_PARENT) {
            $task->term_id = (int) $request->term_id;
            $task->target_role_id = null;
        } else {
            $task->term_id = null;
            $task->target_role_id = (int) $request->target_role_id;
        }
    }

    private function requestAllowsParentEditAfterSubmit(Request $request): bool
    {
        $value = $request->input('allow_parent_edit_after_submit', false);
        if ($value === '1' || $value === 1 || $value === true || $value === 'true' || $value === 'on') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function syncQuestionnaireParentEditSetting(QuestionnaireTask $task, Request $request): void
    {
        $task->loadMissing('questionnaire');
        $questionnaire = $task->questionnaire;
        if (! $questionnaire) {
            return;
        }

        $questionnaire->allow_parent_edit_after_submit = $this->requestAllowsParentEditAfterSubmit($request);
        $questionnaire->save();
    }

    /**
     * @return array<int, array<string, mixed>>|\Illuminate\Http\JsonResponse
     */
    private function normalizeQuestionnaireQuestionsInput($questionsInput)
    {
        if (is_string($questionsInput)) {
            $questionsInput = json_decode($questionsInput, true) ?? [];
        }
        if (! is_array($questionsInput) || count($questionsInput) < 1) {
            return response()->json(['errors' => ['error' => [__('validation.questionnaires.At least one question is required')]]], 422);
        }

        $normalizedQuestions = [];
        foreach ($questionsInput as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $questionTitle = trim((string) ($row['title'] ?? ''));
            if ($questionTitle === '') {
                continue;
            }
            $type = (int) ($row['type'] ?? 1);
            if (! in_array($type, [1, 2], true)) {
                $type = 1;
            }

            $storedOptions = null;
            if ($type === 2) {
                $rawOptions = $row['options'] ?? [];
                if (is_string($rawOptions)) {
                    $rawOptions = json_decode($rawOptions, true) ?? [];
                }
                $storedOptions = [];
                if (is_array($rawOptions)) {
                    foreach ($rawOptions as $optionRow) {
                        $label = trim(is_array($optionRow)
                            ? (string) ($optionRow['title'] ?? $optionRow['text'] ?? $optionRow['value'] ?? '')
                            : (string) $optionRow);
                        if ($label !== '') {
                            $storedOptions[] = $label;
                        }
                    }
                }
                if (count($storedOptions) < 1) {
                    return response()->json(['errors' => ['error' => [__('validation.questionnaires.Multiple choice question requires options')]]], 422);
                }
            }

            $normalizedQuestions[] = [
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'title' => $questionTitle,
                'type' => $type,
                'required' => ! empty($row['required']) ? 1 : 0,
                'options' => $storedOptions,
                'sort_order' => $index,
            ];
        }

        if (count($normalizedQuestions) < 1) {
            return response()->json(['errors' => ['error' => [__('validation.questionnaires.At least one question is required')]]], 422);
        }

        return $normalizedQuestions;
    }

    /**
     * @param  array<int, array<string, mixed>>  $normalizedQuestions
     * @return null|\Illuminate\Http\JsonResponse
     */
    private function syncQuestionnaireQuestions(QuestionnaireTask $task, Questionnaire $questionnaire, array $normalizedQuestions)
    {
        $existingQuestions = QuestionnaireQuestion::where('questionnaire_id', $questionnaire->id)
            ->get()
            ->keyBy('id');

        $payloadIds = [];
        foreach ($normalizedQuestions as $questionRow) {
            if (! empty($questionRow['id'])) {
                $payloadIds[] = (int) $questionRow['id'];
            }
        }

        foreach ($existingQuestions as $questionId => $existingQuestion) {
            if (in_array($questionId, $payloadIds, true)) {
                continue;
            }

            $hasAnswers = QuestionnaireAnswer::where('task_id', $task->id)
                ->where('question_id', $questionId)
                ->exists();
            if ($hasAnswers) {
                return response()->json(['errors' => ['error' => [__('validation.questionnaires.Cannot delete question with responses')]]], 422);
            }

            $existingQuestion->delete();
        }

        foreach ($normalizedQuestions as $questionRow) {
            $questionId = (int) ($questionRow['id'] ?? 0);
            $answerQuery = QuestionnaireAnswer::where('task_id', $task->id);
            if ($questionId > 0) {
                $answerQuery->where('question_id', $questionId);
            }
            $hasAnswers = $questionId > 0 ? $answerQuery->exists() : false;

            if ($questionId > 0 && ! $existingQuestions->has($questionId)) {
                return response()->json(['errors' => ['error' => [__('validation.Questionnaire does not exist')]]], 422);
            }

            if ($questionId > 0) {
                /** @var QuestionnaireQuestion $question */
                $question = $existingQuestions->get($questionId);

                if ($hasAnswers && (int) $question->type !== (int) $questionRow['type']) {
                    return response()->json(['errors' => ['error' => [__('validation.questionnaires.Cannot change question type with responses')]]], 422);
                }

                if ($hasAnswers && (int) $questionRow['type'] === 2) {
                    $oldOptions = $question->choiceOptions() ?? [];
                    $newOptions = $questionRow['options'] ?? [];
                    $removedOptions = array_diff($oldOptions, $newOptions);
                    if ($removedOptions) {
                        $usedRemoved = QuestionnaireAnswer::where('task_id', $task->id)
                            ->where('question_id', $questionId)
                            ->whereIn('answer', array_values($removedOptions))
                            ->exists();
                        if ($usedRemoved) {
                            return response()->json(['errors' => ['error' => [__('validation.questionnaires.Cannot remove option with responses')]]], 422);
                        }
                    }
                }

                $question->title = $questionRow['title'];
                $question->title_local = $questionRow['title'];
                $question->type = $questionRow['type'];
                $question->required = $questionRow['required'];
                $question->sort_order = $questionRow['sort_order'];
                $question->options = $questionRow['options'];
                $question->save();
            } else {
                QuestionnaireQuestion::create([
                    'questionnaire_id' => $questionnaire->id,
                    'title' => $questionRow['title'],
                    'title_local' => $questionRow['title'],
                    'type' => $questionRow['type'],
                    'required' => $questionRow['required'],
                    'sort_order' => $questionRow['sort_order'],
                    'options' => $questionRow['options'],
                ]);
            }
        }

        return null;
    }
}
