<?php

namespace Tests\Unit;

use App\Models\QuestionnaireTask;
use App\Support\QuestionnaireParentTermAccess;
use PHPUnit\Framework\TestCase;

class QuestionnaireParentTermAccessTest extends TestCase
{
    public function test_task_without_term_id_is_always_accessible_for_gate_logic(): void
    {
        $task = new QuestionnaireTask();
        $task->term_id = null;
        $task->center_id = 1;

        $this->assertSame(0, (int) $task->term_id);
        $this->assertTrue(QuestionnaireParentTermAccess::eligibleParentUserIdsForTerm(0, 1)->isEmpty());
    }
}
