<?php

namespace Tests\Unit;

use App\Support\QuestionnaireQuestionCopier;
use PHPUnit\Framework\TestCase;

class QuestionnaireQuestionCopierTest extends TestCase
{
    public function test_normalize_title_ignores_arabic_diacritics_and_letter_variants(): void
    {
        $this->assertSame(
            QuestionnaireQuestionCopier::normalizeTitle('هل يتواصل الطفل بصريا'),
            QuestionnaireQuestionCopier::normalizeTitle('هَلْ يتواصـل الطفل بصرياً؟')
        );
        $this->assertSame(
            QuestionnaireQuestionCopier::normalizeTitle('ما هي اللغة المستخدمة'),
            QuestionnaireQuestionCopier::normalizeTitle('ما هى اللغة المستخدمه؟')
        );
        $this->assertSame(
            QuestionnaireQuestionCopier::normalizeTitle('اسم الطفل'),
            QuestionnaireQuestionCopier::normalizeTitle('إسم الطفل')
        );
    }

    public function test_normalize_title_ignores_case_whitespace_and_punctuation(): void
    {
        $this->assertSame('does the child speak', QuestionnaireQuestionCopier::normalizeTitle("  Does the   CHILD speak?\n"));
        $this->assertSame('age 5', QuestionnaireQuestionCopier::normalizeTitle('Age: ٥'));
    }

    public function test_normalize_title_keeps_distinct_questions_distinct(): void
    {
        $this->assertNotSame(
            QuestionnaireQuestionCopier::normalizeTitle('Does the child speak?'),
            QuestionnaireQuestionCopier::normalizeTitle('Does the child read?')
        );
        $this->assertSame('', QuestionnaireQuestionCopier::normalizeTitle(null));
        $this->assertSame('', QuestionnaireQuestionCopier::normalizeTitle(' ?! '));
    }
}
