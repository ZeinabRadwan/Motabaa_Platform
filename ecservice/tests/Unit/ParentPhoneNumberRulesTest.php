<?php

namespace Tests\Unit;

use App\Support\ParentPhoneNumberRules as Rules;
use Tests\TestCase;

class ParentPhoneNumberRulesTest extends TestCase
{
    private function owner(int $id, bool $isParent = true, string $source = 'account_phone'): array
    {
        return ['user_id' => $id, 'is_parent' => $isParent, 'source' => $source];
    }

    public function test_normalize_accepts_saudi_mobile_forms_and_rejects_others(): void
    {
        $this->assertSame('966501234567', Rules::normalize('0501234567'));
        $this->assertSame('966501234567', Rules::normalize('+966 50 123 4567'));
        $this->assertSame('966501234567', Rules::normalize('966501234567'));
        $this->assertSame('966501234567', Rules::normalize('501234567'));
        $this->assertNull(Rules::normalize('0111234567'));
        $this->assertNull(Rules::normalize('999000000123'));
        $this->assertNull(Rules::normalize('abc'));
        $this->assertNull(Rules::normalize(''));
        $this->assertNull(Rules::normalize(null));
    }

    public function test_merged_placeholder_detection(): void
    {
        $this->assertTrue(Rules::isMergedPlaceholder('999000000268'));
        $this->assertFalse(Rules::isMergedPlaceholder('966501234567'));
        $this->assertFalse(Rules::isMergedPlaceholder(null));
    }

    public function test_add_rejects_invalid_and_same_account_duplicates(): void
    {
        $this->assertSame(Rules::ERR_INVALID, Rules::addError(null, [], []));
        $this->assertSame(Rules::ERR_DUPLICATE, Rules::addError('966501234567', ['966501234567'], []));
        $this->assertNull(Rules::addError('966509999999', ['966501234567'], []));
    }

    public function test_add_rejects_numbers_owned_by_other_accounts(): void
    {
        $this->assertSame(Rules::ERR_CONFLICT, Rules::addError('966501234567', [], [$this->owner(7)]));
        $this->assertSame(Rules::ERR_CONFLICT, Rules::addError('966501234567', [], [$this->owner(7, true, 'verified_number')]));
        $this->assertSame(Rules::ERR_STAFF_COLLISION, Rules::addError('966501234567', [], [$this->owner(7), $this->owner(8, false)]));
    }

    public function test_primary_requires_verification_unless_admin_verifies_in_same_action(): void
    {
        $this->assertSame(Rules::ERR_PRIMARY_REQUIRES_VERIFICATION, Rules::setPrimaryError(['is_primary' => false, 'verified' => false], false));
        $this->assertNull(Rules::setPrimaryError(['is_primary' => false, 'verified' => false], true));
        $this->assertNull(Rules::setPrimaryError(['is_primary' => false, 'verified' => true], false));
    }

    public function test_primary_cannot_be_removed_unverified_or_edited(): void
    {
        $this->assertSame(Rules::ERR_CANNOT_REMOVE_PRIMARY, Rules::deleteError(['is_primary' => true]));
        $this->assertNull(Rules::deleteError(['is_primary' => false]));
        $this->assertSame(Rules::ERR_CANNOT_UNVERIFY_PRIMARY, Rules::unverifyError(['is_primary' => true]));
        $this->assertNull(Rules::unverifyError(['is_primary' => false]));

        $primary = ['phone' => '966501234567', 'is_primary' => true];
        $this->assertSame(Rules::ERR_CANNOT_EDIT_PRIMARY_PHONE, Rules::updateError($primary, '966509999999', ['966501234567'], []));
        $this->assertNull(Rules::updateError($primary, '966501234567', ['966501234567'], []));
    }

    public function test_update_of_additional_number_checks_duplicates_except_itself(): void
    {
        $entry = ['phone' => '966502222222', 'is_primary' => false];
        $account = ['966501111111', '966502222222'];

        $this->assertSame(Rules::ERR_DUPLICATE, Rules::updateError($entry, '966501111111', $account, []));
        $this->assertNull(Rules::updateError($entry, '966503333333', $account, []));
        $this->assertSame(Rules::ERR_CONFLICT, Rules::updateError($entry, '966503333333', $account, [$this->owner(9)]));
        $this->assertSame(Rules::ERR_INVALID, Rules::updateError($entry, null, $account, []));
    }

    public function test_with_primary_leaves_exactly_one_primary(): void
    {
        $entries = [
            ['id' => 1, 'phone' => '966501111111', 'is_primary' => true, 'verified' => true],
            ['id' => 2, 'phone' => '966502222222', 'is_primary' => false, 'verified' => true],
            ['id' => 3, 'phone' => '966503333333', 'is_primary' => false, 'verified' => false],
        ];

        $result = Rules::withPrimary($entries, 2);

        $this->assertSame(1, Rules::primaryCount($result));
        $this->assertTrue($result[1]['is_primary']);
        $this->assertFalse($result[0]['is_primary']);
    }

    public function test_with_primary_rejects_number_of_another_account(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Rules::withPrimary([['id' => 1, 'phone' => '966501111111', 'is_primary' => true, 'verified' => true]], 99);
    }

    public function test_merge_transfer_plan_moves_new_numbers_and_skips_others(): void
    {
        $plan = Rules::mergeTransferPlan(
            [
                ['phone' => '0502222222', 'label' => 'work'],
                ['phone' => '966501111111'],
                ['phone' => '966503333333'],
                ['phone' => '999000000042'],
                ['phone' => '0111234567'],
                ['phone' => '966502222222'],
            ],
            ['966501111111'],
            ['966503333333' => true]
        );

        $this->assertSame([['phone' => '966502222222', 'label' => 'work']], $plan['transfer']);
        $this->assertSame(
            ['already_on_primary', 'owned_by_other_account', 'merged_placeholder', 'invalid_phone', 'duplicate_in_batch'],
            array_column($plan['skipped'], 'reason')
        );
    }

    public function test_backfill_decisions(): void
    {
        $this->assertSame(Rules::BACKFILL_CREATE, Rules::backfillDecision('0501234567', [], false));
        $this->assertSame(Rules::BACKFILL_SKIP_EMPTY, Rules::backfillDecision(null, [], false));
        $this->assertSame(Rules::BACKFILL_SKIP_EMPTY, Rules::backfillDecision('  ', [], false));
        $this->assertSame(Rules::BACKFILL_SKIP_PLACEHOLDER, Rules::backfillDecision('999000000268', [], false));
        $this->assertSame(Rules::BACKFILL_SKIP_INVALID, Rules::backfillDecision('12345', [], false));
        $this->assertSame(Rules::BACKFILL_SKIP_CONFLICT, Rules::backfillDecision('0501234567', [], true));
        $this->assertSame(
            Rules::BACKFILL_SKIP_HAS_PRIMARY,
            Rules::backfillDecision('0501234567', [['phone' => '966501234567', 'is_primary' => true]], false)
        );
        $this->assertSame(
            Rules::BACKFILL_PROMOTE,
            Rules::backfillDecision('0501234567', [['phone' => '966501234567', 'is_primary' => false]], false)
        );
    }
}
