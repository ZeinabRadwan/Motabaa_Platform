<?php

namespace Tests\Unit;

use App\Models\Goal;
use App\Support\ParentGoalDetails;
use Tests\TestCase;

class ParentGoalDetailsTest extends TestCase
{
    private function goal(int $caseId, string $category): Goal
    {
        $goal = new Goal();
        $goal->id = 501;
        $goal->case_id = $caseId;
        $goal->category = $category;

        return $goal;
    }

    public function test_sections_cover_program_and_treatment_only(): void
    {
        $this->assertSame(ParentGoalDetails::SECTION_PROGRAM, ParentGoalDetails::sectionForCategory('educational'));
        $this->assertSame(ParentGoalDetails::SECTION_TREATMENT, ParentGoalDetails::sectionForCategory('occupational therapy'));
        $this->assertSame(ParentGoalDetails::SECTION_TREATMENT, ParentGoalDetails::sectionForCategory('pronouncement'));
        $this->assertNull(ParentGoalDetails::sectionForCategory('independent'));
        $this->assertNull(ParentGoalDetails::sectionForCategory(null));
    }

    public function test_missing_goal_is_not_found(): void
    {
        $this->assertSame(ParentGoalDetails::DENY_NOT_FOUND, ParentGoalDetails::structuralDenyReason(null, 7, 'program'));
    }

    public function test_goal_of_another_beneficiary_is_denied(): void
    {
        $goal = $this->goal(12, 'educational');

        $this->assertSame(
            ParentGoalDetails::DENY_BENEFICIARY_MISMATCH,
            ParentGoalDetails::structuralDenyReason($goal, 7, ParentGoalDetails::SECTION_PROGRAM)
        );
    }

    public function test_treatment_goal_cannot_be_opened_through_program_section(): void
    {
        $goal = $this->goal(7, 'physical therapy');

        $this->assertSame(
            ParentGoalDetails::DENY_SECTION_MISMATCH,
            ParentGoalDetails::structuralDenyReason($goal, 7, ParentGoalDetails::SECTION_PROGRAM)
        );
    }

    public function test_independent_goal_is_not_served_by_this_view(): void
    {
        $this->assertSame(
            ParentGoalDetails::DENY_UNSUPPORTED_SECTION,
            ParentGoalDetails::structuralDenyReason($this->goal(7, 'independent'), 7, null)
        );
    }

    public function test_matching_beneficiary_and_section_pass_structural_checks(): void
    {
        $this->assertNull(ParentGoalDetails::structuralDenyReason(
            $this->goal(7, 'psychiatric treatment'),
            7,
            ParentGoalDetails::SECTION_TREATMENT
        ));
    }

    public function test_document_kinds(): void
    {
        $this->assertSame('pdf', ParentGoalDetails::documentKind('PDF'));
        $this->assertSame('image', ParentGoalDetails::documentKind('jpeg'));
        $this->assertSame('video', ParentGoalDetails::documentKind('mp4'));
        $this->assertSame('audio', ParentGoalDetails::documentKind('mp3'));
        $this->assertSame('office', ParentGoalDetails::documentKind('docx'));
        $this->assertSame('other', ParentGoalDetails::documentKind('zip'));
        $this->assertSame('other', ParentGoalDetails::documentKind(null));
    }

    public function test_denied_response_does_not_leak_goal_data(): void
    {
        $response = ParentGoalDetails::deniedResponse(ParentGoalDetails::DENY_BENEFICIARY_MISMATCH);
        $payload = $response->getData(true);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('beneficiary_mismatch', $payload['reason']);
        $this->assertArrayNotHasKey('data', $payload);
        $this->assertSame(404, ParentGoalDetails::deniedResponse(ParentGoalDetails::DENY_NOT_FOUND)->getStatusCode());
    }
}
