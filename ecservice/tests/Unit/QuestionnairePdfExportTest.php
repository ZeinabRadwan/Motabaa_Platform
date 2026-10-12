<?php

namespace Tests\Unit;

use App\Models\QuestionnaireTask;
use App\Models\System\PDF;
use App\Support\QuestionnaireAnalyticsPdfData;
use Tests\TestCase;

class QuestionnairePdfExportTest extends TestCase
{
    public function test_analytics_pdf_survives_large_text_answer_payload(): void
    {
        $answers = [];
        for ($i = 0; $i < 250; $i++) {
            $answers[] = [
                'id' => $i + 1,
                'account_name' => 'Respondent '.$i,
                'answer' => str_repeat('نص ', 4000),
            ];
        }

        $task = new QuestionnaireTask(['center_id' => 1]);
        $task->setRelation('questionnaire', null);
        $data = QuestionnaireAnalyticsPdfData::build(
            $task,
            ['term' => 'Term', 'period' => null, 'completion' => 'All', 'respondent' => null],
            [
                'targeted_users' => 250,
                'started_incomplete' => 0,
                'completed_submissions' => 250,
                'response_rate_percent' => 100,
            ],
            [[
                'question_number' => 1,
                'title' => 'Long text question',
                'type' => 1,
                'answered' => 250,
                'skipped' => 0,
                'answers' => $answers,
            ]],
            '2026-10-12 12:00:00',
        );

        $binary = PDF::renderBinary('questionnaires.analytics_report', $data);

        $this->assertStringStartsWith('%PDF-', $binary);
        $this->assertNotEmpty(PDF::savePdf('questionnaires.analytics_report', $data, 'analytics-test.pdf'));
    }

    public function test_blank_template_pdf_renders_without_answers(): void
    {
        $data = [
            'title' => 'استبيان تجريبي',
            'generated_at' => '2026-10-12 12:00:00',
            'sections' => [
                [
                    'title' => 'قسم أ',
                    'questions' => [
                        [
                            'number' => 1,
                            'title' => 'سؤال نصي',
                            'type' => 1,
                            'required' => true,
                            'options' => [],
                        ],
                        [
                            'number' => 2,
                            'title' => 'سؤال اختيار',
                            'type' => 2,
                            'required' => false,
                            'options' => ['نعم', 'لا'],
                        ],
                    ],
                ],
            ],
        ];

        $binary = PDF::renderBinary('questionnaires.blank_template', $data);

        $this->assertStringStartsWith('%PDF-', $binary);
        $response = PDF::downloadResponse($binary, 'template-test.pdf');
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('.pdf', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_download_response_includes_pdf_signature_in_body(): void
    {
        $data = [
            'title' => 'عنوان عربي',
            'generated_at' => '2026-10-12 12:00:00',
            'sections' => [],
        ];
        $binary = PDF::renderBinary('questionnaires.blank_template', $data);
        $response = PDF::downloadResponse($binary, 'arabic-title.pdf');

        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

}
