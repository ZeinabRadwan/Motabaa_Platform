<?php

namespace App\Support;

use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireSection;
use App\Models\QuestionnaireTask;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Copies questionnaire questions (and the sections they live in) between questionnaire templates.
 * Answers and submissions are keyed by task and are never read or written here.
 */
class QuestionnaireQuestionCopier
{
    /**
     * Source questions in the order they are displayed (section order, then question order).
     *
     * @param  array<int, int>|null  $questionIds
     * @return Collection<int, QuestionnaireQuestion>
     */
    public static function orderedSourceQuestions(Questionnaire $source, ?array $questionIds = null): Collection
    {
        $questions = QuestionnaireQuestion::query()
            ->where('questionnaire_id', $source->id)
            ->when($questionIds !== null, fn ($query) => $query->whereIn('id', $questionIds))
            ->get();

        $sectionPositions = [];
        if (QuestionnaireSectionStructure::tableReady()) {
            $sectionPositions = QuestionnaireSection::query()
                ->where('questionnaire_id', $source->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('id')
                ->values()
                ->flip()
                ->all();
        }

        return $questions->sort(function (QuestionnaireQuestion $left, QuestionnaireQuestion $right) use ($sectionPositions) {
            $leftSection = $sectionPositions[(int) $left->section_id] ?? PHP_INT_MAX;
            $rightSection = $sectionPositions[(int) $right->section_id] ?? PHP_INT_MAX;

            return [$leftSection, (int) $left->sort_order, (int) $left->id]
                <=> [$rightSection, (int) $right->sort_order, (int) $right->id];
        })->values();
    }

    /**
     * Normalized form used to detect likely duplicate question text (Arabic & English).
     */
    public static function normalizeTitle(?string $title): string
    {
        $text = (string) $title;
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text) ?? $text;
        $text = strtr($text, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * Source questions whose normalized text matches a question already in the destination.
     *
     * @param  Collection<int, QuestionnaireQuestion>  $sourceQuestions
     * @return array<int, array<string, mixed>>  keyed by source question id
     */
    public static function findDuplicates(Questionnaire $destination, Collection $sourceQuestions): array
    {
        $index = [];
        $destinationQuestions = QuestionnaireQuestion::query()
            ->where('questionnaire_id', $destination->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($destinationQuestions as $question) {
            foreach ([$question->title, $question->title_local] as $text) {
                $key = self::normalizeTitle($text);
                if ($key !== '' && ! isset($index[$key])) {
                    $index[$key] = $question;
                }
            }
        }

        $duplicates = [];
        foreach ($sourceQuestions as $question) {
            foreach ([$question->title, $question->title_local] as $text) {
                $key = self::normalizeTitle($text);
                if ($key === '' || ! isset($index[$key])) {
                    continue;
                }

                $match = $index[$key];
                $duplicates[(int) $question->id] = [
                    'source_question_id' => (int) $question->id,
                    'source_title' => $question->getNameAttribute(),
                    'destination_question_id' => (int) $match->id,
                    'destination_title' => $match->getNameAttribute(),
                ];
                break;
            }
        }

        return $duplicates;
    }

    /**
     * Copy the given source questions into the destination questionnaire.
     * Callers are expected to wrap this in a DB transaction.
     *
     * @param  Collection<int, QuestionnaireQuestion>  $questions  ordered source questions
     * @return array{copied_question_ids: array<int, int>, skipped_question_ids: array<int, int>, id_map: array<int, int>}
     */
    public static function copy(Questionnaire $destination, Collection $questions, bool $skipDuplicates): array
    {
        $duplicates = $skipDuplicates ? self::findDuplicates($destination, $questions) : [];
        $toCopy = $questions->reject(fn (QuestionnaireQuestion $q) => isset($duplicates[(int) $q->id]))->values();

        $result = [
            'copied_question_ids' => [],
            'skipped_question_ids' => array_map('intval', array_keys($duplicates)),
            'id_map' => [],
        ];

        if ($toCopy->isEmpty()) {
            return $result;
        }

        $hasSectionColumn = Schema::hasColumn('questionnaires_questions', 'section_id');
        $sectionMap = $hasSectionColumn ? self::resolveSectionMap($destination, $toCopy) : [];

        $existingCount = QuestionnaireQuestion::query()->where('questionnaire_id', $destination->id)->count();
        $nextSort = $existingCount > 0
            ? ((int) QuestionnaireQuestion::query()->where('questionnaire_id', $destination->id)->max('sort_order')) + 1
            : 0;

        foreach ($toCopy as $question) {
            // Explicit columns, not replicate(): LIVE tables may carry extra/generated columns that reject copied values.
            // Raw attributes keep the stored options JSON byte-for-byte (the array cast would re-escape Arabic text).
            $attributes = [
                'questionnaire_id' => $destination->id,
                'title' => $question->getRawOriginal('title'),
                'title_local' => $question->getRawOriginal('title_local'),
                'type' => $question->getRawOriginal('type'),
                'required' => (int) $question->getRawOriginal('required'),
                'options' => $question->getRawOriginal('options'),
                'sort_order' => $nextSort++,
            ];
            if ($hasSectionColumn) {
                $attributes['section_id'] = $sectionMap[(int) $question->section_id] ?? null;
            }
            $copy = new QuestionnaireQuestion();
            $copy->setRawAttributes($attributes);
            $copy->save();

            $result['copied_question_ids'][] = (int) $copy->id;
            $result['id_map'][(int) $question->id] = (int) $copy->id;
        }

        return $result;
    }

    /**
     * Tasks (any center, including trashed) that share the questionnaire template.
     *
     * @return array{total: int, other_centers: int}
     */
    public static function templateUsage(int $questionnaireId, int $centerId): array
    {
        $tasks = QuestionnaireTask::withTrashed()->where('questionnaire_id', $questionnaireId);

        return [
            'total' => (clone $tasks)->count(),
            'other_centers' => (clone $tasks)
                ->where(fn ($query) => $query->whereNull('center_id')->orWhere('center_id', '!=', $centerId))
                ->count(),
        ];
    }

    /**
     * Maps source section id (0 = no section) to destination section id (null = flat).
     *
     * @param  Collection<int, QuestionnaireQuestion>  $toCopy
     * @return array<int, int|null>
     */
    private static function resolveSectionMap(Questionnaire $destination, Collection $toCopy): array
    {
        if (! QuestionnaireSectionStructure::tableReady()) {
            return [];
        }

        $usedSectionIds = $toCopy->pluck('section_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $sourceSections = QuestionnaireSection::query()
            ->whereIn('id', $usedSectionIds->all())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $destinationSections = QuestionnaireSection::query()
            ->where('questionnaire_id', $destination->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $map = [];

        if ($destinationSections->isEmpty()) {
            $destinationHasQuestions = QuestionnaireQuestion::query()
                ->where('questionnaire_id', $destination->id)
                ->exists();

            // A flat questionnaire with questions stays flat; adding sections would orphan its questions.
            if ($destinationHasQuestions) {
                return [];
            }

            foreach ($sourceSections->values() as $position => $section) {
                $map[(int) $section->id] = (int) self::replicateSection($section, $destination, $position)->id;
            }

            return $map;
        }

        $byTitle = [];
        foreach ($destinationSections as $section) {
            foreach ([$section->title, $section->title_local] as $text) {
                $key = self::normalizeTitle($text);
                if ($key !== '' && ! isset($byTitle[$key])) {
                    $byTitle[$key] = (int) $section->id;
                }
            }
        }

        $nextSectionSort = ((int) $destinationSections->max('sort_order')) + 1;
        foreach ($sourceSections as $section) {
            $key = self::normalizeTitle($section->title);
            if ($key !== '' && isset($byTitle[$key])) {
                $map[(int) $section->id] = $byTitle[$key];

                continue;
            }

            $created = self::replicateSection($section, $destination, $nextSectionSort++);
            $map[(int) $section->id] = (int) $created->id;
            if ($key !== '') {
                $byTitle[$key] = (int) $created->id;
            }
        }

        // Unsectioned source questions join the last destination section so the editor keeps titled sections only.
        $map[0] = (int) $destinationSections->last()->id;

        return $map;
    }

    private static function replicateSection(QuestionnaireSection $section, Questionnaire $destination, int $sortOrder): QuestionnaireSection
    {
        return QuestionnaireSection::create([
            'questionnaire_id' => $destination->id,
            'title' => (string) $section->title,
            'title_local' => $section->title_local,
            'sort_order' => $sortOrder,
        ]);
    }
}
