<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;

/**
 * Parent Individual Plan (educational goals): the parent receives every goal staff see for the
 * linked beneficiary in the current term, whatever its sessions contain.
 */
class ParentIndividualPlanTest extends ParentGoalsTestCase
{
    private const LIST = ['status' => 'active', 'options' => ['itemsPerPage' => 100], 'page' => 1];

    public function test_parent_sees_the_same_individual_plan_goals_as_admin(): void
    {
        $plan = $this->faisalBeneficiary();

        foreach ([$this->admin, $this->staff] as $staffUser) {
            $staff = $this->staffGoalsResponse($plan['id'], 'educational', ['term_id' => $this->currentTerm] + self::LIST, $staffUser);
            $this->assertSame(30, $staff->json('total'), $staffUser->email);
            $this->assertSame($plan['current_term'], $this->sortedIds($staff->json('data')), $staffUser->email);
        }

        $parent = $this->parentGoalsResponse($plan['id'], 'educational', ['term_id' => $this->currentTerm] + self::LIST);
        $this->assertSame(30, $parent->json('total'));
        $this->assertSame($plan['current_term'], $this->sortedIds($parent->json('data')));
    }

    public function test_every_session_content_group_is_listed(): void
    {
        $plan = $this->faisalBeneficiary();

        $ids = $this->parentGoals($plan['id'], 'educational', self::LIST);

        foreach ($plan['groups'] as $group => $goalIds) {
            $this->assertSame([], array_values(array_diff($goalIds, $ids)), $group);
        }
        $this->assertNotContains($plan['past_term'], $ids);
        $this->assertNotContains($plan['deleted'], $ids);
    }

    public function test_goal_details_open_for_goals_without_attachments_or_sessions(): void
    {
        $plan = $this->faisalBeneficiary();

        foreach (['text_only_sessions', 'attachment_file_missing', 'no_sessions', 'not_started', 'staff_only_sessions'] as $group) {
            $this->parentGoalDetails($plan['id'], $plan['groups'][$group][0])->assertOk();
        }

        $text = $this->parentGoalDetails($plan['id'], $plan['groups']['text_only_sessions'][0])->json('data.media');
        $this->assertCount(1, $text);
        $this->assertSame('Session update', $text[0]['content']);

        $missing = $this->parentGoalDetails($plan['id'], $plan['groups']['attachment_file_missing'][0])->json('data.media');
        $this->assertCount(1, $missing);
        $this->assertNull($missing[0]['image'] ?? $missing[0]['video'] ?? $missing[0]['document']);

        $this->assertSame([], $this->parentGoalDetails($plan['id'], $plan['groups']['no_sessions'][0])->json('data.media'));
        $this->assertSame([], $this->parentGoalDetails($plan['id'], $plan['groups']['staff_only_sessions'][0])->json('data.media'));
    }

    public function test_mixed_sessions_return_every_shared_session(): void
    {
        $case = $this->makeCase('Mixed', self::CENTER, linkParent: true);
        $goal = $this->makeGoal($case, 'educational', media: 'video');
        $this->addMessage($goal, 'text_only', content: 'Practised counting');
        $this->addMessage($goal, 'legacy_image');
        $this->addMessage($goal, 'text_only', content: 'Asked for help twice');
        $this->addMessage($goal, 'image', published: false);

        $media = collect($this->parentGoalDetails($case, $goal)->assertOk()->json('data.media'));

        $this->assertCount(4, $media);
        $this->assertSame(1, $media->whereNotNull('video')->count());
        $this->assertSame(1, $media->whereNotNull('image')->count());
        $this->assertEqualsCanonicalizing(
            ['Practised counting', 'Asked for help twice'],
            $media->where('image', null)->where('video', null)->where('document', null)->pluck('content')->all()
        );
    }

    public function test_total_and_pages_include_every_goal(): void
    {
        $case = $this->makeCase('Large plan', self::CENTER, linkParent: true);
        $all = [];
        for ($i = 0; $i < 130; $i++) {
            $all[] = $this->makeGoal($case, 'educational', media: ['image', 'text_only', 'none'][$i % 3]);
        }

        $first = $this->parentGoalsResponse($case, 'educational', self::LIST);
        $second = $this->parentGoalsResponse($case, 'educational', ['page' => 2] + self::LIST);

        $this->assertSame(130, $first->json('total'));
        $this->assertSame(2, $first->json('lastPage'));
        $this->assertCount(100, $first->json('data'));
        $this->assertCount(30, $second->json('data'));
        $this->assertSame($all, $this->sortedIds(array_merge($first->json('data'), $second->json('data'))));
    }

    public function test_domain_filter_returns_every_goal_of_that_domain(): void
    {
        $case = $this->makeCase('Domains', self::CENTER, linkParent: true);
        DB::table('assessments')->insert([
            ['id' => 20, 'parent_id' => null, 'parents_ids' => null, 'title' => 'Scale', 'title_local' => 'Scale'],
            ['id' => 21, 'parent_id' => 20, 'parents_ids' => '#20_', 'title' => 'Communication', 'title_local' => 'Communication'],
            ['id' => 22, 'parent_id' => 20, 'parents_ids' => '#20_', 'title' => 'Motor', 'title_local' => 'Motor'],
            ['id' => 23, 'parent_id' => 21, 'parents_ids' => '#20_#21_', 'title' => 'Ask for help', 'title_local' => 'Ask for help'],
            ['id' => 24, 'parent_id' => 22, 'parents_ids' => '#20_#22_', 'title' => 'Hold a pen', 'title_local' => 'Hold a pen'],
        ]);
        $communication = [
            $this->makeGoal($case, 'educational', media: 'image', assessmentId: 23),
            $this->makeGoal($case, 'educational', media: 'text_only', assessmentId: 23),
            $this->makeGoal($case, 'educational', media: 'none', started: false, assessmentId: 23),
        ];
        $this->makeGoal($case, 'educational', media: 'image', assessmentId: 24);

        $this->assertSame($communication, $this->sortedIds(
            $this->parentGoalsResponse($case, 'educational', ['feild_id' => 21] + self::LIST)->json('data')
        ));
    }

    public function test_deleted_goals_stay_hidden_even_when_parent_requests_all_statuses(): void
    {
        $plan = $this->faisalBeneficiary();

        foreach (['all', 'inactive'] as $status) {
            $ids = $this->parentGoals($plan['id'], 'educational', ['status' => $status] + self::LIST);
            $this->assertNotContains($plan['deleted'], $ids, $status);
        }
        $this->parentGoalDetails($plan['id'], $plan['deleted'])->assertNotFound();
    }

    public function test_each_child_lists_only_its_own_goals(): void
    {
        $plan = $this->faisalBeneficiary();
        $sibling = $this->makeCase('Sibling', self::CENTER, linkParent: true);
        $siblingGoals = [
            $this->makeGoal($sibling, 'educational', media: 'file'),
            $this->makeGoal($sibling, 'educational', media: 'none'),
        ];

        $this->assertSame($siblingGoals, $this->sortedIds(
            $this->parentGoalsResponse($sibling, 'educational', self::LIST)->json('data')
        ));
        $this->assertSame($plan['current_term'], $this->sortedIds(
            $this->parentGoalsResponse($plan['id'], 'educational', self::LIST)->json('data')
        ));
        $this->parentGoalDetails($sibling, $plan['current_term'][0])->assertForbidden();
    }

    public function test_parent_gets_nothing_for_unlinked_or_other_center_beneficiaries(): void
    {
        $unlinked = $this->makeCase('Not my child', self::CENTER, linkParent: false);
        $unlinkedGoal = $this->makeGoal($unlinked, 'educational', media: 'none');
        $otherCenter = $this->makeCase('Other center child', self::OTHER_CENTER, linkParent: true);
        $otherCenterGoal = $this->makeGoal($otherCenter, 'educational', media: 'text_only');

        foreach ([$unlinked => $unlinkedGoal, $otherCenter => $otherCenterGoal] as $caseId => $goalId) {
            $response = $this->parentGoalsResponse($caseId, 'educational', self::LIST);
            $this->assertSame([], $response->json('data'));
            $this->assertSame(0, $response->json('total'));
            $this->parentGoalDetails($caseId, $goalId)->assertForbidden();
        }
    }
}
