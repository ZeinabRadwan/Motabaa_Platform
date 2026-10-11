<?php

namespace App\Support;

use App\Http\Resources\QuestionnaireQuestionResource;
use App\Models\Questionnaire;
use App\Models\QuestionnaireAnswer;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireSection;
use App\Models\QuestionnaireTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class QuestionnaireSectionStructure
{
    public static function tableReady(): bool
    {
        return Schema::hasTable('questionnaires_sections')
            && Schema::hasColumn('questionnaires_questions', 'section_id');
    }

    /**
     * @param  Collection<int, int>  $answerCounts  question_id => count
     * @return array<int, array<string, mixed>>
     */
    public static function buildAdminSectionsPayload(int $questionnaireId, Collection $answerCounts): array
    {
        $questions = QuestionnaireQuestion::where('questionnaire_id', $questionnaireId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if (! self::tableReady()) {
            return self::legacyFlatAsSections($questions, $answerCounts);
        }

        $sections = QuestionnaireSection::where('questionnaire_id', $questionnaireId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($sections->isEmpty()) {
            return self::legacyFlatAsSections($questions, $answerCounts);
        }

        $questionsBySection = $questions->groupBy(fn (QuestionnaireQuestion $q) => (int) ($q->section_id ?? 0));

        $payload = [];
        foreach ($sections as $section) {
            $sectionQuestions = $questionsBySection->get((int) $section->id, collect());
            $payload[] = self::sectionRow($section, $sectionQuestions, $answerCounts);
        }

        $orphanQuestions = $questionsBySection->get(0, collect());
        if ($orphanQuestions->isNotEmpty()) {
            $payload[] = [
                'id' => null,
                'title' => '',
                'sort_order' => count($payload),
                'question_count' => $orphanQuestions->count(),
                'questions' => self::mapQuestions($orphanQuestions, $answerCounts),
            ];
        }

        return $payload;
    }

    /**
     * @param  Collection<int, QuestionnaireQuestion>  $questions
     * @return array<int, array<string, mixed>>
     */
    public static function buildRespondentSectionsPayload(Collection $questions): array
    {
        if ($questions->isEmpty()) {
            return [];
        }

        $questionnaireId = (int) $questions->first()->questionnaire_id;

        if (! self::tableReady()) {
            return [[
                'id' => null,
                'title' => '',
                'sort_order' => 0,
                'questions' => $questions->values()->all(),
            ]];
        }

        $sections = QuestionnaireSection::where('questionnaire_id', $questionnaireId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($sections->isEmpty()) {
            return [[
                'id' => null,
                'title' => '',
                'sort_order' => 0,
                'questions' => $questions->sortBy([['sort_order', 'asc'], ['id', 'asc']])->values()->all(),
            ]];
        }

        $bySection = $questions->groupBy(fn ($q) => (int) ($q->section_id ?? 0));
        $payload = [];

        foreach ($sections as $section) {
            $sectionQuestions = $bySection->get((int) $section->id, collect())
                ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
                ->values()
                ->all();

            $payload[] = [
                'id' => (int) $section->id,
                'title' => $section->getNameAttribute(),
                'sort_order' => (int) $section->sort_order,
                'questions' => $sectionQuestions,
            ];
        }

        $orphans = $bySection->get(0, collect())
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->values()
            ->all();

        if ($orphans !== []) {
            $payload[] = [
                'id' => null,
                'title' => '',
                'sort_order' => count($payload),
                'questions' => $orphans,
            ];
        }

        return $payload;
    }

    /**
     * @return array<int, array<string, mixed>>|JsonResponse
     */
    public static function normalizeSectionsInput($sectionsInput, $legacyQuestionsInput = null)
    {
        if (is_string($sectionsInput)) {
            $sectionsInput = json_decode($sectionsInput, true) ?? [];
        }

        if ((! is_array($sectionsInput) || count($sectionsInput) < 1) && $legacyQuestionsInput !== null) {
            return self::normalizeLegacyFlatQuestions($legacyQuestionsInput);
        }

        if (! is_array($sectionsInput) || count($sectionsInput) < 1) {
            return response()->json(['errors' => ['error' => [__('validation.questionnaires.At least one section is required')]]], 422);
        }

        $normalizedSections = [];
        $globalQuestionCount = 0;
        $globalSortBase = 0;

        foreach ($sectionsInput as $sectionIndex => $sectionRow) {
            if (! is_array($sectionRow)) {
                continue;
            }

            $sectionTitle = trim((string) ($sectionRow['title'] ?? ''));

            $questionsInput = $sectionRow['questions'] ?? [];
            if (is_string($questionsInput)) {
                $questionsInput = json_decode($questionsInput, true) ?? [];
            }

            $normalizedQuestions = self::normalizeQuestionsArray($questionsInput, $globalSortBase);
            if ($normalizedQuestions instanceof JsonResponse) {
                return $normalizedQuestions;
            }

            if (count($normalizedQuestions) < 1) {
                continue;
            }

            $globalQuestionCount += count($normalizedQuestions);
            $globalSortBase += count($normalizedQuestions);

            $normalizedSections[] = [
                'id' => isset($sectionRow['id']) ? (int) $sectionRow['id'] : 0,
                'title' => $sectionTitle,
                'sort_order' => $sectionIndex,
                'questions' => $normalizedQuestions,
            ];
        }

        if ($globalQuestionCount < 1 || count($normalizedSections) < 1) {
            return response()->json(['errors' => ['error' => [__('validation.questionnaires.At least one question is required')]]], 422);
        }

        $titledSectionCount = collect($normalizedSections)->filter(fn ($row) => ($row['title'] ?? '') !== '')->count();
        if (count($normalizedSections) > 1 && $titledSectionCount < count($normalizedSections)) {
            return response()->json(['errors' => ['error' => [__('validation.questionnaires.Section title required for multiple sections')]]], 422);
        }

        if (count($normalizedSections) === 1 && ($normalizedSections[0]['title'] ?? '') === '') {
            $normalizedSections[0]['flat_only'] = true;
        }

        return $normalizedSections;
    }

    /**
     * @param  array<int, array<string, mixed>>  $normalizedSections
     */
    public static function sync(QuestionnaireTask $task, Questionnaire $questionnaire, array $normalizedSections): ?JsonResponse
    {
        $flatOnly = count($normalizedSections) === 1 && ! empty($normalizedSections[0]['flat_only']);

        if (! self::tableReady() || $flatOnly) {
            $flat = [];
            foreach ($normalizedSections as $sectionRow) {
                foreach ($sectionRow['questions'] as $questionRow) {
                    $flat[] = $questionRow;
                }
            }

            if (self::tableReady() && $flatOnly) {
                self::removeAllSectionsForQuestionnaire($task, $questionnaire);
            }

            return self::syncFlatQuestionsLegacy($task, $questionnaire, $flat);
        }

        $existingSections = QuestionnaireSection::where('questionnaire_id', $questionnaire->id)
            ->get()
            ->keyBy('id');

        $existingQuestions = QuestionnaireQuestion::where('questionnaire_id', $questionnaire->id)
            ->get()
            ->keyBy('id');

        $payloadSectionIds = [];
        $payloadQuestionIds = [];

        foreach ($normalizedSections as $sectionRow) {
            if (! empty($sectionRow['id'])) {
                $payloadSectionIds[] = (int) $sectionRow['id'];
            }
            foreach ($sectionRow['questions'] as $questionRow) {
                if (! empty($questionRow['id'])) {
                    $payloadQuestionIds[] = (int) $questionRow['id'];
                }
            }
        }

        foreach ($existingSections as $sectionId => $existingSection) {
            if (in_array($sectionId, $payloadSectionIds, true)) {
                continue;
            }

            $sectionQuestionIds = $existingQuestions
                ->filter(fn (QuestionnaireQuestion $q) => (int) $q->section_id === (int) $sectionId)
                ->keys()
                ->all();

            foreach ($sectionQuestionIds as $questionId) {
                $hasAnswers = QuestionnaireAnswer::where('task_id', $task->id)
                    ->where('question_id', $questionId)
                    ->exists();
                if ($hasAnswers) {
                    return response()->json(['errors' => ['error' => [__('validation.questionnaires.Cannot delete question with responses')]]], 422);
                }
            }

            foreach ($sectionQuestionIds as $questionId) {
                $existingQuestions->get($questionId)?->delete();
            }

            $existingSection->delete();
        }

        foreach ($existingQuestions as $questionId => $existingQuestion) {
            if (in_array($questionId, $payloadQuestionIds, true)) {
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

        foreach ($normalizedSections as $sectionRow) {
            $sectionId = (int) ($sectionRow['id'] ?? 0);

            if ($sectionId > 0 && ! $existingSections->has($sectionId)) {
                return response()->json(['errors' => ['error' => [__('validation.Questionnaire does not exist')]]], 422);
            }

            if ($sectionId > 0) {
                /** @var QuestionnaireSection $section */
                $section = $existingSections->get($sectionId);
                $section->title = $sectionRow['title'];
                $section->title_local = $sectionRow['title'];
                $section->sort_order = $sectionRow['sort_order'];
                $section->save();
            } else {
                $section = QuestionnaireSection::create([
                    'questionnaire_id' => $questionnaire->id,
                    'title' => $sectionRow['title'],
                    'title_local' => $sectionRow['title'],
                    'sort_order' => $sectionRow['sort_order'],
                ]);
                $existingSections->put($section->id, $section);
            }

            $syncError = self::syncQuestionsInSection(
                $task,
                $questionnaire,
                $section,
                $sectionRow['questions'],
                $existingQuestions
            );

            if ($syncError instanceof JsonResponse) {
                return $syncError;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $normalizedQuestions
     * @param  Collection<int, QuestionnaireQuestion>  $existingQuestions
     */
    private static function syncQuestionsInSection(
        QuestionnaireTask $task,
        Questionnaire $questionnaire,
        QuestionnaireSection $section,
        array $normalizedQuestions,
        Collection $existingQuestions
    ): ?JsonResponse {
        foreach ($normalizedQuestions as $questionRow) {
            $questionId = (int) ($questionRow['id'] ?? 0);
            $hasAnswers = $questionId > 0
                ? QuestionnaireAnswer::where('task_id', $task->id)->where('question_id', $questionId)->exists()
                : false;

            if ($questionId > 0 && ! $existingQuestions->has($questionId)) {
                return response()->json(['errors' => ['error' => [__('validation.Questionnaire does not exist')]]], 422);
            }

            if ($questionId > 0) {
                /** @var QuestionnaireQuestion $question */
                $question = $existingQuestions->get($questionId);

                if ((int) $question->questionnaire_id !== (int) $questionnaire->id) {
                    return response()->json(['errors' => ['error' => [__('validation.Questionnaire does not exist')]]], 422);
                }

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

                $question->section_id = $section->id;
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
                    'section_id' => $section->id,
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

    /**
     * @return array<int, array<string, mixed>>|JsonResponse
     */
    private static function normalizeLegacyFlatQuestions($questionsInput)
    {
        $normalized = self::normalizeQuestionsArray($questionsInput, 0);
        if ($normalized instanceof JsonResponse) {
            return $normalized;
        }

        if (count($normalized) < 1) {
            return response()->json(['errors' => ['error' => [__('validation.questionnaires.At least one question is required')]]], 422);
        }

        return [[
            'id' => 0,
            'title' => __('tr.questionnaires.default_section_title'),
            'sort_order' => 0,
            'questions' => $normalized,
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>|JsonResponse
     */
    private static function normalizeQuestionsArray($questionsInput, int $sortOffset = 0)
    {
        if (is_string($questionsInput)) {
            $questionsInput = json_decode($questionsInput, true) ?? [];
        }
        if (! is_array($questionsInput)) {
            return [];
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
                'sort_order' => $sortOffset + $index,
            ];
        }

        return $normalizedQuestions;
    }

    /**
     * @param  Collection<int, QuestionnaireQuestion>  $questions
     * @param  Collection<int, int>  $answerCounts
     * @return array<int, array<string, mixed>>
     */
    private static function legacyFlatAsSections(Collection $questions, Collection $answerCounts): array
    {
        if ($questions->isEmpty()) {
            return [];
        }

        return [[
            'id' => null,
            'title' => '',
            'sort_order' => 0,
            'question_count' => $questions->count(),
            'questions' => self::mapQuestions($questions, $answerCounts),
        ]];
    }

    /**
     * @param  Collection<int, QuestionnaireQuestion>  $questions
     * @param  Collection<int, int>  $answerCounts
     * @return array<int, array<string, mixed>>
     */
    private static function mapQuestions(Collection $questions, Collection $answerCounts): array
    {
        return $questions->map(function (QuestionnaireQuestion $question) use ($answerCounts) {
            $row = (new QuestionnaireQuestionResource($question))->resolve();
            $row['answer_count'] = (int) ($answerCounts[$question->id] ?? 0);
            $row['section_id'] = $question->section_id ? (int) $question->section_id : null;

            return $row;
        })->values()->all();
    }

    /**
     * @param  Collection<int, QuestionnaireSection>  $sectionQuestions
     * @param  Collection<int, int>  $answerCounts
     * @return array<string, mixed>
     */
    private static function sectionRow(QuestionnaireSection $section, Collection $sectionQuestions, Collection $answerCounts): array
    {
        return [
            'id' => (int) $section->id,
            'title' => $section->getNameAttribute(),
            'sort_order' => (int) $section->sort_order,
            'question_count' => $sectionQuestions->count(),
            'questions' => self::mapQuestions($sectionQuestions->sortBy([['sort_order', 'asc'], ['id', 'asc']]), $answerCounts),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $flatQuestions
     */
    private static function syncFlatQuestionsLegacy(QuestionnaireTask $task, Questionnaire $questionnaire, array $flatQuestions): ?JsonResponse
    {
        $existingQuestions = QuestionnaireQuestion::where('questionnaire_id', $questionnaire->id)
            ->get()
            ->keyBy('id');

        $payloadIds = [];
        foreach ($flatQuestions as $questionRow) {
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

        foreach ($flatQuestions as $questionRow) {
            $questionId = (int) ($questionRow['id'] ?? 0);
            $hasAnswers = $questionId > 0
                ? QuestionnaireAnswer::where('task_id', $task->id)->where('question_id', $questionId)->exists()
                : false;

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
                if (Schema::hasColumn('questionnaires_questions', 'section_id')) {
                    $question->section_id = null;
                }
                $question->save();
            } else {
                $createPayload = [
                    'questionnaire_id' => $questionnaire->id,
                    'title' => $questionRow['title'],
                    'title_local' => $questionRow['title'],
                    'type' => $questionRow['type'],
                    'required' => $questionRow['required'],
                    'sort_order' => $questionRow['sort_order'],
                    'options' => $questionRow['options'],
                ];
                if (Schema::hasColumn('questionnaires_questions', 'section_id')) {
                    $createPayload['section_id'] = null;
                }
                QuestionnaireQuestion::create($createPayload);
            }
        }

        return null;
    }

    private static function removeAllSectionsForQuestionnaire(QuestionnaireTask $task, Questionnaire $questionnaire): void
    {
        if (! self::tableReady()) {
            return;
        }

        $sections = QuestionnaireSection::where('questionnaire_id', $questionnaire->id)->get();
        if ($sections->isEmpty()) {
            return;
        }

        if (Schema::hasColumn('questionnaires_questions', 'section_id')) {
            QuestionnaireQuestion::where('questionnaire_id', $questionnaire->id)->update(['section_id' => null]);
        }

        foreach ($sections as $section) {
            $section->delete();
        }
    }
}
