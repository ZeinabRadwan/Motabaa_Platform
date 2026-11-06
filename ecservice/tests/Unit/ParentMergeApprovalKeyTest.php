<?php

namespace Tests\Unit;

use App\Support\ParentAccountMergePlanner;
use Tests\TestCase;

class ParentMergeApprovalKeyTest extends TestCase
{
    public function test_group_key_is_sorted_and_separator_agnostic(): void
    {
        $this->assertSame('253-268', ParentAccountMergePlanner::normalizeGroupKey('268-253'));
        $this->assertSame('253-268', ParentAccountMergePlanner::normalizeGroupKey('253,268'));
        $this->assertSame('253-268', ParentAccountMergePlanner::normalizeGroupKey(' 253 268 '));
    }
}
