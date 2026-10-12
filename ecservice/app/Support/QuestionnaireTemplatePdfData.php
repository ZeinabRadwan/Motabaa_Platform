<?php

namespace App\Support;

use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireTask;
use Illuminate\Support\Collection;

class QuestionnaireTemplatePdfData
{
    /**
     * @return array<string, mixed>
     */
    public static function build(QuestionnaireTask $task, string $generatedAt): array
    {
        $task->loadMissing('questionnaire');

        $questionnaire = $task->questionnaire;
        $title = $questionnaire
            ? self::cleanString($questionnaire->getNameAttribute())
            : self::cleanString(__('tr.questionnaires.template_export_title'));

        $questions = QuestionnaireQuestion::query()
            ->where('questionnaire_id', $task->questionnaire_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $sections = self::mapSections($questions);

        return [
            'title' => $title,
            'generated_at' => $generatedAt,
            'sections' => $sections,
        ];
    }

    /**
     * @param  Collection<int, QuestionnaireQuestion>  $questions
     * @return array<int, array<string, mixed>>
     */
    private static function mapSections(Collection $questions): array
    {
        $rawSections = QuestionnaireSectionStructure::buildRespondentSectionsPayload($questions);
        $questionNumber = 0;
        $sections = [];

        foreach ($rawSections as $section) {
            $sectionQuestions = [];
            foreach ($section['questions'] ?? [] as $question) {
                if ($question instanceof QuestionnaireQuestion) {
                    $sectionQuestions[] = self::mapQuestion($question, ++$questionNumber);
                }
            }

            if ($sectionQuestions === []) {
                continue;
            }

            $sections[] = [
                'title' => self::cleanNullableString($section['title'] ?? null),
                'questions' => $sectionQuestions,
            ];
        }

        return $sections;
    }

    /**
     * @return array<string, mixed>
     */
    private static function mapQuestion(QuestionnaireQuestion $question, int $number): array
    {
        $type = (int) $question->type;
        $row = [
            'number' => $number,
            'title' => self::cleanString($question->getNameAttribute()),
            'type' => $type,
            'required' => (bool) $question->required,
            'options' => [],
        ];

        if ($type === 2) {
            $options = $question->choiceOptions();
            if ($options) {
                $row['options'] = array_map(fn ($label) => self::cleanString($label), $options);
            } else {
                $row['options'] = [
                    self::cleanString(__('Yes')),
                    self::cleanString(__('No')),
                ];
                $row['legacy_yes_no'] = true;
            }
        }

        return $row;
    }

    private static function cleanNullableString($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::cleanString($value);
    }

    private static function cleanString($value): string
    {
        $text = (string) $value;
        if ($text === '') {
            return '';
        }

        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        return trim(strip_tags($text));
    }
}
