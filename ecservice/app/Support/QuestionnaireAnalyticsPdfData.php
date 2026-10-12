<?php

namespace App\Support;

use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireTask;
use Illuminate\Support\Collection;

class QuestionnaireAnalyticsPdfData
{
    public const MAX_TEXT_ANSWERS_PER_QUESTION = 250;

    /** Keep individual answers short enough for mPDF / PCRE byte limits on large reports. */
    public const MAX_TEXT_ANSWER_BYTE_LENGTH = 1200;

    /**
     * @param  Collection<int, QuestionnaireQuestion>  $questions
     * @param  array<string, mixed>  $filterLabels
     * @param  array<string, mixed>  $kpis
     * @param  array<int, array<string, mixed>>  $questionAnalytics
     * @return array<string, mixed>
     */
    public static function build(
        QuestionnaireTask $task,
        array $filterLabels,
        array $kpis,
        array $questionAnalytics,
        string $generatedAt,
    ): array {
        return [
            'title' => self::cleanString(
                $task->questionnaire ? $task->questionnaire->getNameAttribute() : __('tr.questionnaires.analytics_report_title')
            ),
            'generated_at' => $generatedAt,
            'filter_labels' => [
                'term' => self::cleanNullableString($filterLabels['term'] ?? null),
                'period' => self::cleanNullableString($filterLabels['period'] ?? null),
                'completion' => self::cleanNullableString($filterLabels['completion'] ?? null),
                'respondent' => self::cleanNullableString($filterLabels['respondent'] ?? null),
            ],
            'kpis' => [
                'targeted_users' => (int) ($kpis['targeted_users'] ?? 0),
                'started_incomplete' => (int) ($kpis['started_incomplete'] ?? 0),
                'completed_submissions' => (int) ($kpis['completed_submissions'] ?? 0),
                'response_rate_percent' => (float) ($kpis['response_rate_percent'] ?? 0),
            ],
            'questions' => self::sanitizeQuestions($questionAnalytics),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $questions
     * @return array<int, array<string, mixed>>
     */
    private static function sanitizeQuestions(array $questions): array
    {
        $result = [];
        foreach ($questions as $question) {
            $row = $question;
            $row['title'] = self::cleanString($question['title'] ?? '');
            $row['question_number'] = (int) ($question['question_number'] ?? 0);
            $row['type'] = (int) ($question['type'] ?? 0);
            $row['answered'] = (int) ($question['answered'] ?? 0);
            $row['skipped'] = (int) ($question['skipped'] ?? 0);

            if (! empty($question['option_stats']) && is_array($question['option_stats'])) {
                $row['option_stats'] = array_map(function ($option) {
                    return [
                        'answer' => self::cleanString($option['answer'] ?? ''),
                        'count' => (int) ($option['count'] ?? 0),
                        'percent' => (float) ($option['percent'] ?? 0),
                    ];
                }, $question['option_stats']);
            }

            if (! empty($question['answers']) && is_array($question['answers'])) {
                $answers = array_values($question['answers']);
                $total = count($answers);
                if ($total > self::MAX_TEXT_ANSWERS_PER_QUESTION) {
                    $answers = array_slice($answers, 0, self::MAX_TEXT_ANSWERS_PER_QUESTION);
                    $row['answers_truncated'] = $total - self::MAX_TEXT_ANSWERS_PER_QUESTION;
                }
                $row['answers'] = array_map(function ($answerRow) {
                    [$answerText, $truncated] = self::truncateForPdf($answerRow['answer'] ?? '');

                    return [
                        'id' => (int) ($answerRow['id'] ?? 0),
                        'account_name' => self::cleanString($answerRow['account_name'] ?? __('tr.questionnaires.metadata_unavailable')),
                        'answer' => $answerText,
                        'answer_truncated' => $truncated,
                    ];
                }, $answers);
            }

            if (empty($row['option_stats']) && (int) ($row['type'] ?? 0) === 2 && ! empty($question['chart_series'])) {
                $series = array_map('intval', (array) $question['chart_series']);
                $labels = ['yes', 'no'];
                $row['legacy_choice_stats'] = [
                    ['answer' => __('Yes'), 'count' => $series[0] ?? 0],
                    ['answer' => __('No'), 'count' => $series[1] ?? 0],
                ];
            }

            $result[] = $row;
        }

        return $result;
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

    /**
     * @return array{0: string, 1: bool}
     */
    private static function truncateForPdf($value): array
    {
        $text = self::cleanString($value);
        if ($text === '') {
            return ['', false];
        }

        if (strlen($text) <= self::MAX_TEXT_ANSWER_BYTE_LENGTH) {
            return [$text, false];
        }

        return [mb_strcut($text, 0, self::MAX_TEXT_ANSWER_BYTE_LENGTH, 'UTF-8').'…', true];
    }
}
