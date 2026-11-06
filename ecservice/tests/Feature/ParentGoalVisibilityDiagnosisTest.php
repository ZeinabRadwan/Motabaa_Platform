<?php

namespace Tests\Feature;

use App\Console\Commands\DiagnoseParentGoalVisibilityCommand as Diagnose;
use App\Models\SCase;
use App\Support\GoalParentVisibility;

/**
 * `parents:diagnose-goal-visibility` must agree with what `GET /api/goals` returns to the parent,
 * so its per-goal output can be trusted when run against real data.
 */
class ParentGoalVisibilityDiagnosisTest extends ParentGoalsTestCase
{
    private const LIST = ['status' => 'active', 'options' => ['itemsPerPage' => 100], 'page' => 1];

    public function test_individual_plan_matches_the_parent_list(): void
    {
        $plan = $this->faisalBeneficiary();

        $goals = collect(Diagnose::diagnose(SCase::find($plan['id']), $this->parent, 'educational')['goals'])->keyBy('id');

        $visible = $goals->where('reason', Diagnose::VISIBLE)->keys()->sort()->values()->all();
        $this->assertSame($this->sortedIds($this->parentGoalsResponse($plan['id'], 'educational', self::LIST)->json('data')), $visible);
        $this->assertSame($plan['current_term'], $visible);
        $this->assertSame(Diagnose::OTHER_TERM, $goals[$plan['past_term']]['reason']);
        $this->assertSame(Diagnose::DELETED, $goals[$plan['deleted']]['reason']);

        $underAttachmentRule = $goals->where('reason', Diagnose::VISIBLE)->where('visible_under_attachment_rule', true)->keys()->sort()->values()->all();
        $expected = array_merge($plan['groups']['attachment_recorded'], $plan['groups']['attachment_before_path_columns']);
        sort($expected);
        $this->assertSame($expected, $underAttachmentRule);

        $this->assertSame(0, $goals[$plan['groups']['staff_only_sessions'][0]]['shared_messages']);
        $this->assertSame(1, $goals[$plan['groups']['staff_only_sessions'][0]]['staff_only_messages']);
        $this->assertSame(1, $goals[$plan['groups']['text_only_sessions'][0]]['shared_messages']);
        $this->assertSame(0, $goals[$plan['groups']['text_only_sessions'][0]]['shared_with_attachment']);
    }

    public function test_support_services_match_the_parent_lists(): void
    {
        $case = $this->hajarBeneficiary();

        $goals = collect(Diagnose::diagnose(SCase::find($case['id']), $this->parent, 'treatment')['goals'])->keyBy('id');

        $apiVisible = [];
        foreach (GoalParentVisibility::TREATMENT_CATEGORIES as $category) {
            $apiVisible = array_merge($apiVisible, $this->parentGoals($case['id'], $category, self::LIST));
        }
        sort($apiVisible);

        $this->assertSame($apiVisible, $goals->where('reason', Diagnose::VISIBLE)->keys()->sort()->values()->all());
        $this->assertSame(Diagnose::OTHER_TERM, $goals[$case['past_ot_goal']]['reason']);
        $this->assertFalse($goals[$case['speech_text_goal']]['visible_under_attachment_rule']);
    }

    public function test_command_prints_the_breakdown_for_linked_parents(): void
    {
        $case = $this->hajarBeneficiary();

        $this->artisan('parents:diagnose-goal-visibility', ['case' => $case['id']])
            ->expectsOutputToContain('هاجر محمد العقيدي')
            ->expectsOutputToContain(Diagnose::OTHER_TERM)
            ->assertSuccessful();
    }

    public function test_unlinked_beneficiary_is_reported_without_listing_anything_as_visible(): void
    {
        $other = $this->makeCase('Other center child', self::OTHER_CENTER, linkParent: true);
        $this->makeGoal($other, 'educational', media: 'image');

        $result = Diagnose::diagnose(SCase::find($other), $this->parent);
        $this->assertFalse($result['linked']);
        $this->assertSame([Diagnose::NOT_LINKED], array_values(array_unique(array_column($result['goals'], 'reason'))));
        $this->artisan('parents:diagnose-goal-visibility', ['case' => $other])
            ->expectsOutputToContain('not linked to this parent')
            ->assertSuccessful();

        $unlinked = $this->makeCase('Not my child', self::CENTER, linkParent: false);
        $this->artisan('parents:diagnose-goal-visibility', ['case' => $unlinked])->assertFailed();
    }
}
