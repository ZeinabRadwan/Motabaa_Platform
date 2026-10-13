<?php

namespace Tests\Unit;

use App\Support\LoginIdentifier;
use Tests\TestCase;

class PasswordResetServiceTest extends TestCase
{
    public function test_phone_match_candidates_include_international_and_local(): void
    {
        $candidates = LoginIdentifier::phoneMatchCandidates('0501234567');

        $this->assertContains('0501234567', $candidates);
        $this->assertContains('966501234567', $candidates);
    }
}
