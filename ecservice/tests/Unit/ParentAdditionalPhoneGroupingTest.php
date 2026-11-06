<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\ParentAccountMergePlanner;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ParentAdditionalPhoneGroupingTest extends TestCase
{
    /**
     * @param  array<int, array{0: string, 1: bool}>  $numbers  [phone, verified]
     */
    private function parent(int $id, string $name, ?string $email, ?string $phone, array $numbers = []): User
    {
        $user = new User(['name' => $name, 'email' => $email, 'phone' => $phone, 'status' => User::STATUS_CAN_LOGIN]);
        $user->id = $id;
        $user->password = 'hash';
        $user->setRelation('roles', collect([(object) ['default_name' => 'parent']]));
        $user->setRelation('phoneNumbers', collect(array_map(fn ($n) => (object) [
            'phone' => $n[0],
            'verified_at' => $n[1] ? '2026-10-01 10:00:00' : null,
            'is_primary' => false,
        ], $numbers)));

        return $user;
    }

    private function buildPlan(array $users, array $approved = []): array
    {
        return ParentAccountMergePlanner::buildGroups(collect($users), [], [], $approved, fn (Collection $g, array $ids) => []);
    }

    public function test_phone_keys_include_only_verified_additional_numbers(): void
    {
        $user = $this->parent(1, 'Ali Saleh', null, '0501111111', [['0502222222', true], ['0503333333', false]]);

        $this->assertSame(
            ['966501111111' => 'primary_phone', '966502222222' => 'additional_phone'],
            array_combine(array_map('strval', array_keys(ParentAccountMergePlanner::phoneKeysOf($user))), ParentAccountMergePlanner::phoneKeysOf($user))
        );
    }

    public function test_verified_additional_number_links_accounts_but_requires_approval(): void
    {
        $a = $this->parent(10, 'Fahad Omar', 'f1@example.com', '0501111111', [['0502222222', true]]);
        $b = $this->parent(11, 'Fahad Omar', 'f2@example.com', '0502222222');

        $groups = $this->buildPlan([$a, $b]);

        $this->assertCount(1, $groups);
        $group = $groups[0];
        $this->assertSame('10-11', $group['group_key']);
        $this->assertSame(ParentAccountMergePlanner::MATCH_SOURCE_ADDITIONAL_PHONE, $group['match_source']);
        $this->assertSame(ParentAccountMergePlanner::PHONE_SOURCE_ADDITIONAL, $group['phone_match_source']);
        $this->assertSame('966502222222', $group['normalized_phone']);
        $this->assertSame(ParentAccountMergePlanner::MATCH_PHONE_ONLY, $group['match_type']);
        $this->assertSame(ParentAccountMergePlanner::STATUS_SKIPPED, $group['merge_status']);
        $this->assertSame(['additional_phone_match_requires_approval'], $group['skip_reasons']);
        $this->assertContains('linked_via_verified_additional_phone', $group['review_notes']);
        $this->assertSame(['966502222222'], $group['phone_details'][0]['verified_additional_phones']);
    }

    public function test_admin_approval_allows_merge_linked_by_additional_number(): void
    {
        $a = $this->parent(10, 'Fahad Omar', 'f1@example.com', '0501111111', [['0502222222', true]]);
        $b = $this->parent(11, 'Fahad Omar', 'f2@example.com', '0502222222');

        $group = $this->buildPlan([$a, $b], ['10-11'])[0];

        $this->assertSame(ParentAccountMergePlanner::STATUS_WOULD_MERGE, $group['merge_status']);
        $this->assertSame('admin_approved', $group['confidence']);
        $this->assertSame(ParentAccountMergePlanner::MATCH_SOURCE_ADDITIONAL_PHONE, $group['match_source']);
    }

    public function test_unverified_additional_number_is_ignored(): void
    {
        $a = $this->parent(20, 'Fahad Omar', 'f1@example.com', '0501111111', [['0502222222', false]]);
        $b = $this->parent(21, 'Fahad Omar', 'f2@example.com', '0502222222');

        $groups = $this->buildPlan([$a, $b]);

        $this->assertSame([], $groups);
    }

    public function test_additional_number_match_does_not_bypass_name_mismatch(): void
    {
        $father = $this->parent(30, 'خالد المقرن', 'k@example.com', '0501111111', [['0502222222', true]]);
        $mother = $this->parent(31, 'نورة العتيبي', 'n@example.com', '0502222222');

        $group = $this->buildPlan([$father, $mother])[0];

        $this->assertSame(ParentAccountMergePlanner::STATUS_SKIPPED, $group['merge_status']);
        $this->assertContains('name_mismatch', $group['skip_reasons']);
        $this->assertContains('additional_phone_match_requires_approval', $group['skip_reasons']);
    }

    public function test_additional_number_match_does_not_bypass_conflicting_ids_even_when_approved(): void
    {
        $a = $this->parent(40, 'Omar Ali', 'o1@example.com', '0501111111', [['0502222222', true]]);
        $a->id_or_residence_number = '1000000001';
        $b = $this->parent(41, 'Omar Ali', 'o2@example.com', '0502222222');
        $b->id_or_residence_number = '1000000002';

        $group = $this->buildPlan([$a, $b], ['40-41'])[0];

        $this->assertSame(ParentAccountMergePlanner::STATUS_SKIPPED, $group['merge_status']);
        $this->assertContains('conflicting_id_or_residence_number', $group['skip_reasons']);
    }

    public function test_group_linked_by_login_contact_is_not_flagged_as_additional(): void
    {
        $a = $this->parent(50, 'Huda Saad', 'huda@example.com', '0501111111', [['0503333333', true]]);
        $b = $this->parent(51, 'Huda Saad', 'huda@example.com', '0502222222', [['0503333333', true]]);

        $group = $this->buildPlan([$a, $b])[0];

        $this->assertSame(ParentAccountMergePlanner::MATCH_SOURCE_PRIMARY, $group['match_source']);
        $this->assertSame(ParentAccountMergePlanner::STATUS_WOULD_MERGE, $group['merge_status']);
        $this->assertSame(ParentAccountMergePlanner::MATCH_EMAIL_AND_PHONE, $group['match_type']);
        $this->assertSame(ParentAccountMergePlanner::PHONE_SOURCE_ADDITIONAL, $group['phone_match_source']);
        $this->assertTrue($group['phone_differs']);
    }

    public function test_accounts_without_loaded_numbers_behave_as_before(): void
    {
        $a = $this->parent(60, 'Ali Saleh', 'ali@example.com', '0500000001');
        $b = $this->parent(61, 'ali saleh', 'ALI@example.com', '+966500000001');
        $a->unsetRelation('phoneNumbers');
        $b->unsetRelation('phoneNumbers');

        $group = $this->buildPlan([$a, $b])[0];

        $this->assertSame(ParentAccountMergePlanner::MATCH_SOURCE_PRIMARY, $group['match_source']);
        $this->assertSame(ParentAccountMergePlanner::PHONE_SOURCE_PRIMARY, $group['phone_match_source']);
        $this->assertSame('high_strong', $group['confidence']);
    }
}
