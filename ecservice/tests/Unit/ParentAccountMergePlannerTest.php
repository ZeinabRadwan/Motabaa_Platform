<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\ParentAccountMergePlanner;
use App\Support\ParentIdentity;
use Tests\TestCase;

class ParentAccountMergePlannerTest extends TestCase
{
    public function test_saudi_phone_variants_normalize_to_same_canonical(): void
    {
        $this->assertSame(
            ParentIdentity::canonicalPhone('0506538118'),
            ParentIdentity::canonicalPhone('966506538118')
        );
        $this->assertSame(
            ParentIdentity::canonicalPhone('0506538118'),
            ParentIdentity::canonicalPhone('+966506538118')
        );
    }

    public function test_similar_emails_with_extra_digit_are_not_equal(): void
    {
        $a = ParentIdentity::normalizeEmail('mrdassulaiman@gmail.com');
        $b = ParentIdentity::normalizeEmail('Mrdassulaiman1@gmail.com');

        $this->assertNotSame($a, $b);
    }

    public function test_primary_prefers_account_with_linked_students(): void
    {
        $user253 = new User([
            'name' => 'Mr Dassulaiman',
            'status' => User::STATUS_CAN_LOGIN,
            'password' => 'hash',
        ]);
        $user253->id = 253;
        $user268 = new User([
            'name' => 'Mr Dassulaiman',
            'status' => User::STATUS_CAN_LOGIN,
            'password' => 'hash',
        ]);
        $user268->id = 268;

        $linked = [
            253 => [],
            268 => [171],
        ];

        $primary = ParentAccountMergePlanner::choosePrimaryUserId(
            collect([$user253, $user268]),
            $linked,
            []
        );

        $this->assertSame(268, $primary);
    }

    public function test_name_normalization_is_case_and_whitespace_insensitive(): void
    {
        $this->assertSame(
            ParentIdentity::normalizeFullName('  Mr   Dassulaiman '),
            ParentIdentity::normalizeFullName('mr dassulaiman')
        );
    }
}
