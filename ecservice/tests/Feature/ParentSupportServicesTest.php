<?php

namespace Tests\Feature;

use App\Support\GoalParentVisibility;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Parent Support Services (treatment plans): service types, domains, goal lists and goal details
 * for a parent, compared with what authorized staff see for the same beneficiary.
 */
class ParentSupportServicesTest extends ParentGoalsTestCase
{
    public function test_speech_therapy_and_its_goals_are_listed_for_parent(): void
    {
        $case = $this->hajarBeneficiary();

        $this->assertContains('pronouncement', $this->parentServiceTypes($case['id']));

        $speech = $this->parentGoals($case['id'], 'pronouncement');
        sort($speech);
        $this->assertSame([$case['speech_goal'], $case['speech_text_goal']], $speech);

        $details = $this->parentGoalDetails($case['id'], $case['speech_goal'])->assertOk();
        $this->assertSame('pronouncement', $details->json('data.goal.category'));
        $this->assertNotNull($details->json('data.media.0.video'));
    }

    public function test_parent_service_types_and_goals_match_staff_for_the_current_term(): void
    {
        $case = $this->hajarBeneficiary();

        foreach ([$this->admin, $this->staff] as $staffUser) {
            $staffGoals = collect(GoalParentVisibility::TREATMENT_CATEGORIES)
                ->mapWithKeys(fn ($category) => [
                    $category => $this->staffGoalsResponse($case['id'], $category, ['term_id' => $this->currentTerm], $staffUser)->json('total'),
                ])
                ->filter()
                ->all();

            $this->assertSame(
                ['physical therapy' => 1, 'pronouncement' => 2, 'psychiatric treatment' => 1],
                $staffGoals,
                $staffUser->email
            );
        }

        $this->assertSame(['physical therapy', 'pronouncement', 'psychiatric treatment'], $this->parentServiceTypes($case['id']));
        foreach (['physical therapy' => 1, 'pronouncement' => 2, 'psychiatric treatment' => 1] as $category => $count) {
            $this->assertSame($count, $this->parentGoalsResponse($case['id'], $category)->json('total'), $category);
        }
    }

    public function test_text_only_session_is_visible_in_goal_details(): void
    {
        $case = $this->hajarBeneficiary();

        $media = $this->parentGoalDetails($case['id'], $case['speech_text_goal'])->assertOk()->json('data.media');

        $this->assertCount(1, $media);
        $this->assertSame('Session update', $media[0]['content']);
        $this->assertNull($media[0]['image']);
        $this->assertNull($media[0]['video']);
        $this->assertNull($media[0]['document']);
    }

    public function test_staff_only_sessions_stay_hidden_while_the_goal_is_listed(): void
    {
        $case = $this->hajarBeneficiary();

        $this->assertSame([$case['psych_goal']], $this->parentGoals($case['id'], 'psychiatric treatment'));
        $this->assertSame([], $this->parentGoalDetails($case['id'], $case['psych_goal'])->assertOk()->json('data.media'));
    }

    public function test_service_type_from_a_past_term_is_not_offered(): void
    {
        $case = $this->hajarBeneficiary();

        $this->assertNotContains('occupational therapy', $this->parentServiceTypes($case['id']));
        $this->assertSame([], $this->parentGoals($case['id'], 'occupational therapy'));
        $this->parentGoalDetails($case['id'], $case['past_ot_goal'])->assertForbidden();
    }

    public function test_goals_without_sessions_or_with_mixed_sessions_are_listed(): void
    {
        $case = $this->makeCase('Mixed sessions', self::CENTER, linkParent: true);
        $noSessions = $this->makeGoal($case, 'occupational therapy', media: 'none', started: false);
        $mixed = $this->makeGoal($case, 'physical therapy', media: 'image');
        $this->addMessage($mixed, 'text_only', content: 'Worked on balance');
        $this->addMessage($mixed, 'file');
        $this->addMessage($mixed, 'text_only', published: false, content: 'Staff note');

        $this->assertSame(['occupational therapy', 'physical therapy'], $this->parentServiceTypes($case));
        $this->assertSame([$noSessions], $this->parentGoals($case, 'occupational therapy'));
        $this->assertSame([], $this->parentGoalDetails($case, $noSessions)->assertOk()->json('data.media'));

        $media = collect($this->parentGoalDetails($case, $mixed)->assertOk()->json('data.media'));
        $this->assertCount(3, $media);
        $this->assertSame(1, $media->whereNotNull('image')->count());
        $this->assertSame(1, $media->whereNotNull('document')->count());
        $this->assertSame(['Worked on balance'], $media->where('image', null)->where('document', null)->pluck('content')->all());
        $this->assertNotContains('Staff note', $media->pluck('content'));
    }

    public function test_each_child_returns_only_its_own_services(): void
    {
        $reported = $this->hajarBeneficiary();
        $sibling = $this->makeCase('Sibling', self::CENTER, linkParent: true);
        $this->makeGoal($sibling, 'occupational therapy', media: 'text_only');
        $empty = $this->makeCase('No services yet', self::CENTER, linkParent: true);

        $this->assertSame(['occupational therapy'], $this->parentServiceTypes($sibling));
        $this->assertSame([], $this->parentServiceTypes($empty));
        $this->assertSame(['physical therapy', 'pronouncement', 'psychiatric treatment'], $this->parentServiceTypes($reported['id']));
        $this->parentGoalDetails($sibling, $reported['speech_goal'])->assertForbidden();
    }

    public function test_parent_cannot_read_services_of_unlinked_or_other_center_beneficiaries(): void
    {
        $unlinked = $this->makeCase('Not my child', self::CENTER, linkParent: false);
        $unlinkedGoal = $this->makeGoal($unlinked, 'pronouncement', media: 'text_only');
        $otherCenter = $this->makeCase('Other center child', self::OTHER_CENTER, linkParent: true);
        $otherCenterGoal = $this->makeGoal($otherCenter, 'pronouncement', media: 'text_only');

        Sanctum::actingAs($this->parent);
        foreach ([$unlinked => $unlinkedGoal, $otherCenter => $otherCenterGoal] as $caseId => $goalId) {
            $this->getJson('/api/goals/parent-treatment-categories?case_id='.$caseId)->assertForbidden();
            $this->getJson('/api/goals?'.http_build_query(['case_id' => $caseId, 'category' => 'pronouncement']))->assertForbidden();
            $this->parentGoalDetails($caseId, $goalId)->assertForbidden();
        }
    }

    public function test_categories_query_selects_distinct_category_column_only(): void
    {
        $query = GoalParentVisibility::treatmentCategoriesQuery($this->parent, self::CENTER, 1)->getQuery();

        $this->assertTrue($query->distinct);
        $this->assertSame(['goals.category'], $query->columns);
    }

    public function test_staff_cannot_use_parent_categories_endpoint(): void
    {
        $case = $this->hajarBeneficiary();

        Sanctum::actingAs($this->staff);
        $this->getJson('/api/goals/parent-treatment-categories?case_id='.$case['id'])->assertForbidden();
    }

    public function test_domain_filter_lists_domain_of_goal_without_attachments(): void
    {
        $case = $this->makeCase('Domains', self::CENTER, linkParent: true);
        DB::table('assessments')->insert([
            ['id' => 10, 'parent_id' => null, 'parents_ids' => null, 'title' => 'Speech scale', 'title_local' => 'Speech scale'],
            ['id' => 11, 'parent_id' => 10, 'parents_ids' => '#10_', 'title' => 'Articulation', 'title_local' => 'Articulation'],
            ['id' => 12, 'parent_id' => 11, 'parents_ids' => '#10_#11_', 'title' => 'Say /s/', 'title_local' => 'Say /s/'],
        ]);
        $this->makeGoal($case, 'pronouncement', media: 'text_only', assessmentId: 12);

        Sanctum::actingAs($this->parent);
        $domains = $this->getJson('/api/goals/filter?'.http_build_query(['case_id' => $case, 'category' => 'pronouncement']))
            ->assertOk()
            ->json('data');

        $this->assertSame([11], array_column($domains, 'value'));
    }

    /**
     * Option values sorted alphabetically (the API sorts by the translated label).
     *
     * @return array<int, string>
     */
    private function parentServiceTypes(int $caseId): array
    {
        Sanctum::actingAs($this->parent);

        $values = array_column(
            $this->getJson('/api/goals/parent-treatment-categories?case_id='.$caseId)->assertOk()->json('data'),
            'value'
        );
        sort($values);

        return $values;
    }
}
