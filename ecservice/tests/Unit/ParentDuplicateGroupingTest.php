<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\ParentAccountMergePlanner;
use App\Support\ParentIdentity;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ParentDuplicateGroupingTest extends TestCase
{
    private function parent(int $id, string $name, ?string $email, ?string $phone, array $attributes = []): User
    {
        $user = new User(array_merge([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'status' => User::STATUS_CAN_LOGIN,
        ], $attributes));
        $user->id = $id;
        $user->password = 'hash';
        $user->setRelation('roles', collect([(object) ['default_name' => 'parent']]));

        return $user;
    }

    /**
     * @param  array<int, User>  $users
     * @param  array<int, array<int, int>>  $students
     * @return array<int, array<string, mixed>>
     */
    private function planGroups(array $users, array $students = [], array $approved = [], ?callable $checks = null): array
    {
        return ParentAccountMergePlanner::buildGroups(
            collect($users),
            $students,
            [],
            $approved,
            $checks ?? fn (Collection $group, array $ids) => []
        );
    }

    public function test_fares_case_same_email_different_phones_different_students_is_merged(): void
    {
        $a = $this->parent(301, 'فارس عبد الرحمن الخضيري', 'fares.k@example.com', '0501111111');
        $b = $this->parent(302, 'فارس عبدالرحمن الخضيري', "Fares.K@example.com\u{200F}", '966502222222');

        $groups = $this->planGroups([$a, $b], [301 => [501], 302 => [502]]);

        $this->assertCount(1, $groups);
        $group = $groups[0];
        $this->assertSame('301-302', $group['group_key']);
        $this->assertSame(ParentAccountMergePlanner::MATCH_EMAIL_ONLY, $group['match_type']);
        $this->assertSame(ParentAccountMergePlanner::STATUS_WOULD_MERGE, $group['merge_status']);
        $this->assertSame('fares.k@example.com', $group['normalized_email']);
        $this->assertTrue($group['phone_differs']);
        $this->assertTrue($group['linked_students_differ']);
        $this->assertSame([501, 502], $group['linked_students_after_primary']);
        $this->assertSame(['966501111111', '966502222222'], array_column($group['phone_details'], 'normalized_phone'));
        $this->assertSame(['0501111111', '966502222222'], array_column($group['phone_details'], 'phone'));
        $this->assertContains('phone_difference_flagged_for_review', $group['review_notes']);
        $this->assertContains('invisible_characters_in_email:user_302', $group['review_notes']);
        $this->assertSame(301, $group['primary_account_id']);
        $this->assertSame([302], $group['merged_account_ids']);
    }

    public function test_same_phone_different_email_matching_name_is_merged_and_flagged(): void
    {
        $a = $this->parent(253, 'سليمان المرداس', 'mrdassulaiman@gmail.com', '0506538118');
        $b = $this->parent(268, 'سليمان  المرداس', 'Mrdassulaiman1@gmail.com', '966506538118');

        $group = $this->planGroups([$a, $b], [268 => [171]])[0];

        $this->assertSame(ParentAccountMergePlanner::MATCH_PHONE_ONLY, $group['match_type']);
        $this->assertSame(ParentAccountMergePlanner::STATUS_WOULD_MERGE, $group['merge_status']);
        $this->assertTrue($group['email_differs']);
        $this->assertSame(268, $group['primary_account_id']);
        $this->assertSame([171], $group['linked_students_after_primary']);
    }

    public function test_same_email_and_phone_is_strong_match(): void
    {
        $a = $this->parent(1, 'Ali Saleh', 'ali@example.com', '0500000001');
        $b = $this->parent(2, 'ali saleh', 'ALI@example.com ', '+966500000001');

        $group = $this->planGroups([$a, $b])[0];

        $this->assertSame(ParentAccountMergePlanner::MATCH_EMAIL_AND_PHONE, $group['match_type']);
        $this->assertSame('high_strong', $group['confidence']);
    }

    public function test_family_shared_phone_with_different_names_is_not_merged(): void
    {
        $father = $this->parent(10, 'خالد المقرن', 'khaled@example.com', '0503333333');
        $mother = $this->parent(11, 'نورة العتيبي', 'noura@example.com', '966503333333');

        $group = $this->planGroups([$father, $mother])[0];

        $this->assertSame(ParentAccountMergePlanner::STATUS_SKIPPED, $group['merge_status']);
        $this->assertContains('name_mismatch', $group['skip_reasons']);
    }

    public function test_family_member_sharing_a_contact_does_not_block_real_duplicate(): void
    {
        $father1 = $this->parent(20, 'Fahad Omar', 'fahad@example.com', '0504444444');
        $father2 = $this->parent(21, 'Fahad Omar', 'fahad@example.com', '0505555555');
        $mother = $this->parent(22, 'Sara Omar', 'sara@example.com', '0504444444');

        $groups = $this->planGroups([$father1, $father2, $mother]);

        $this->assertCount(1, $groups);
        $this->assertSame('20-21', $groups[0]['group_key']);
        $this->assertSame(ParentAccountMergePlanner::STATUS_WOULD_MERGE, $groups[0]['merge_status']);
        $this->assertContains('shares_contact_with_different_name:user_22', $groups[0]['review_notes']);
    }

    public function test_conflicting_national_ids_block_merge(): void
    {
        $a = $this->parent(30, 'Omar Ali', 'omar@example.com', '0506666666', ['id_or_residence_number' => '1000000001']);
        $b = $this->parent(31, 'Omar Ali', 'omar@example.com', '0507777777', ['id_or_residence_number' => '1000000002']);

        $group = $this->planGroups([$a, $b])[0];

        $this->assertSame(ParentAccountMergePlanner::STATUS_SKIPPED, $group['merge_status']);
        $this->assertContains('conflicting_id_or_residence_number', $group['skip_reasons']);
    }

    public function test_staff_collision_from_contact_checks_blocks_merge(): void
    {
        $a = $this->parent(40, 'Huda Saad', 'huda@example.com', '0508888888');
        $b = $this->parent(41, 'Huda Saad', 'huda@example.com', '0509999999');

        $group = $this->planGroups([$a, $b], [], [], fn () => ['staff_collision:user_40'])[0];

        $this->assertSame(ParentAccountMergePlanner::STATUS_SKIPPED, $group['merge_status']);
    }

    public function test_non_parent_role_blocks_merge(): void
    {
        $a = $this->parent(50, 'Majed Fahd', 'majed@example.com', '0501212121');
        $b = $this->parent(51, 'Majed Fahd', 'majed@example.com', '0503434343');
        $b->setRelation('roles', collect([(object) ['default_name' => 'parent'], (object) ['default_name' => 'specialist']]));

        $group = $this->planGroups([$a, $b])[0];

        $this->assertContains('non_parent_role:user_51', $group['skip_reasons']);
    }

    public function test_admin_approval_overrides_only_name_mismatch(): void
    {
        $a = $this->parent(60, 'Name One', 'x@example.com', '0505656565');
        $b = $this->parent(61, 'Name Two', 'y@example.com', '966505656565');

        $this->assertSame(ParentAccountMergePlanner::STATUS_SKIPPED, $this->planGroups([$a, $b])[0]['merge_status']);

        $approved = $this->planGroups([$a, $b], [], ['61-60'])[0];
        $this->assertSame(ParentAccountMergePlanner::STATUS_WOULD_MERGE, $approved['merge_status']);
        $this->assertContains('admin_approved_override:name_mismatch', $approved['confidence_reasons']);

        $blocked = $this->planGroups([$a, $b], [], ['60-61'], fn () => ['staff_collision:user_60'])[0];
        $this->assertSame(ParentAccountMergePlanner::STATUS_SKIPPED, $blocked['merge_status']);
    }

    public function test_unrelated_accounts_form_no_group(): void
    {
        $a = $this->parent(70, 'A', 'a@example.com', '0501010101');
        $b = $this->parent(71, 'B', 'b@example.com', '0502020202');

        $this->assertSame([], $this->planGroups([$a, $b]));
    }

    public function test_identical_malformed_emails_still_group_and_are_flagged(): void
    {
        $a = $this->parent(80, 'فارس عبد الرحمن الخضيري', 'fares@gmail', '0501313131');
        $b = $this->parent(81, 'فارس عبد الرحمن الخضيري', "FARES@gmail\u{200F}", '0502424242');

        $group = $this->planGroups([$a, $b], [80 => [601], 81 => [602]])[0];

        $this->assertSame(ParentAccountMergePlanner::MATCH_EMAIL_ONLY, $group['match_type']);
        $this->assertSame(ParentAccountMergePlanner::STATUS_WOULD_MERGE, $group['merge_status']);
        $this->assertSame([601, 602], $group['linked_students_after_primary']);
        $this->assertContains('email_fails_validation:user_80', $group['review_notes']);
    }

    public function test_malformed_emails_differing_by_one_character_do_not_group(): void
    {
        $a = $this->parent(90, 'Same Name', 'fares@gmail', '0501414141');
        $b = $this->parent(91, 'Same Name', 'fares1@gmail', '0502525252');

        $this->assertSame([], $this->planGroups([$a, $b]));
    }

    public function test_253_268_names_are_flagged_as_likely_same_person_but_not_auto_merged(): void
    {
        $a = $this->parent(253, 'مرداس سليمان مد الله المرداسي', 'mrdassulaiman@gmail.com', '0506538118');
        $b = $this->parent(268, 'مرداس سليمان المرداسي', 'Mrdassulaiman1@gmail.com', '966506538118');

        $group = $this->planGroups([$a, $b], [268 => [171]])[0];

        $this->assertSame(ParentAccountMergePlanner::STATUS_SKIPPED, $group['merge_status']);
        $this->assertSame(['name_mismatch'], $group['skip_reasons']);
        $this->assertContains('names_differ_only_by_middle_parts_likely_same_person', $group['review_notes']);
        $this->assertSame(268, $group['primary_account_id']);
    }

    public function test_middle_part_hint_requires_same_first_and_family_name(): void
    {
        $this->assertTrue(ParentIdentity::namesDifferOnlyByMiddleParts('مرداس سليمان مد الله المرداسي', 'مرداس سليمان المرداسي'));
        $this->assertFalse(ParentIdentity::namesDifferOnlyByMiddleParts('مرداس سليمان المرداسي', 'مرداس سليمان العتيبي'));
        $this->assertFalse(ParentIdentity::namesDifferOnlyByMiddleParts('خالد سليمان المرداسي', 'مرداس سليمان المرداسي'));
    }

    public function test_real_fares_case_same_name_emails_one_char_apart_is_review_only(): void
    {
        $a = $this->parent(500, 'فارس عبدالرحمن الخضيري', 'Latyn5555@gmail.com', '966558008580', ['id_or_residence_number' => '1075010858']);
        $b = $this->parent(501, 'فارس عبدالرحمن الخضيري', 'Latyn555@gmail.com', '966505258653');

        $groups = $this->planGroups([$a, $b], [500 => [701], 501 => [702]]);

        $this->assertCount(1, $groups);
        $group = $groups[0];
        $this->assertSame(ParentAccountMergePlanner::MATCH_NAME_ONLY, $group['match_type']);
        $this->assertSame(ParentAccountMergePlanner::STATUS_SKIPPED, $group['merge_status']);
        $this->assertSame(['no_shared_email_or_phone'], $group['skip_reasons']);
        $this->assertContains('emails_differ_by_one_character:user_500,user_501', $group['review_notes']);
        $this->assertTrue($group['email_differs']);
        $this->assertTrue($group['phone_differs']);
        $this->assertSame([701, 702], $group['linked_students_after_primary']);
    }

    public function test_real_fares_case_merges_only_with_admin_approval(): void
    {
        $a = $this->parent(500, 'فارس عبدالرحمن الخضيري', 'Latyn5555@gmail.com', '966558008580', ['id_or_residence_number' => '1075010858']);
        $b = $this->parent(501, 'فارس عبدالرحمن الخضيري', 'Latyn555@gmail.com', '966505258653');

        $group = $this->planGroups([$a, $b], [500 => [701], 501 => [702]], ['500-501'])[0];

        $this->assertSame(ParentAccountMergePlanner::STATUS_WOULD_MERGE, $group['merge_status']);
        $this->assertSame('admin_approved', $group['confidence']);
        $this->assertSame([701, 702], $group['linked_students_after_primary']);
        $this->assertSame(['966558008580', '966505258653'], array_column($group['phone_details'], 'phone'));
    }

    public function test_name_only_approval_cannot_override_conflicting_national_ids(): void
    {
        $a = $this->parent(510, 'نايف سعد العتيبي', 'n1@example.com', '0501717171', ['id_or_residence_number' => '1000000011']);
        $b = $this->parent(511, 'نايف سعد العتيبي', 'n2@example.com', '0502828282', ['id_or_residence_number' => '1000000022']);

        $group = $this->planGroups([$a, $b], [], ['510-511'])[0];

        $this->assertSame(ParentAccountMergePlanner::STATUS_SKIPPED, $group['merge_status']);
        $this->assertContains('conflicting_id_or_residence_number', $group['skip_reasons']);
    }

    public function test_two_part_common_names_are_not_name_only_candidates(): void
    {
        $a = $this->parent(520, 'محمد علي', 'm1@example.com', '0501919191');
        $b = $this->parent(521, 'محمد علي', 'm2@example.com', '0502929292');

        $this->assertSame([], $this->planGroups([$a, $b]));
    }

    public function test_email_with_one_extra_character_is_a_different_email(): void
    {
        $this->assertNotSame(
            ParentIdentity::normalizeEmail('mrdassulaiman@gmail.com'),
            ParentIdentity::normalizeEmail('mrdassulaiman1@gmail.com')
        );
    }

    public function test_invisible_characters_are_stripped_and_reported(): void
    {
        $raw = "\u{200F}Fares@Example.com\u{00A0}";

        $this->assertSame('fares@example.com', ParentIdentity::normalizeEmail($raw));
        $this->assertContains('U+200F', ParentIdentity::invisibleCharacters($raw));
        $this->assertContains('U+00A0', ParentIdentity::invisibleCharacters($raw));
    }

    public function test_arabic_name_variants_normalize_equally(): void
    {
        $this->assertSame(
            ParentIdentity::normalizeFullName('فارس عبد الرحمن الخضيري'),
            ParentIdentity::normalizeFullName('فارس  عبدالرحمن  الخضيري')
        );
        $this->assertSame(
            ParentIdentity::normalizeFullName('أحمد عبدالله'),
            ParentIdentity::normalizeFullName('احمد عبدالله')
        );
        $this->assertSame(
            ParentIdentity::normalizeFullName('فاطمة'),
            ParentIdentity::normalizeFullName('فاطمه')
        );
        $this->assertNotSame(
            ParentIdentity::normalizeFullName('فارس الخضيري'),
            ParentIdentity::normalizeFullName('فارس الخضير')
        );
    }
}
