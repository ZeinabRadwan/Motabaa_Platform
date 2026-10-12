<?php

namespace Tests\Unit;

use App\Support\LoginIdentifier;
use Tests\TestCase;

class LoginIdentifierTest extends TestCase
{
    public function test_phone_match_candidates_for_saudi_formats(): void
    {
        $local = LoginIdentifier::phoneMatchCandidates('0505226389');
        $this->assertContains('0505226389', $local);
        $this->assertContains('966505226389', $local);

        $international = LoginIdentifier::phoneMatchCandidates('966505226389');
        $this->assertSame(['966505226389'], $international);

        $plus = LoginIdentifier::phoneMatchCandidates('+966505226389');
        $this->assertSame(['966505226389'], $plus);

        $spaces = LoginIdentifier::phoneMatchCandidates(' +966 50 522 6389 ');
        $this->assertContains('966505226389', $spaces);
    }

    public function test_email_is_not_treated_as_phone(): void
    {
        $this->assertSame('parent@example.com', LoginIdentifier::normalizedEmailOrNull('parent@example.com'));
        $this->assertFalse(LoginIdentifier::isPlausiblePhoneInput('parent@example.com'));
    }

    public function test_implausible_phone_input_rejected(): void
    {
        $this->assertFalse(LoginIdentifier::isPlausiblePhoneInput('not-a-login'));
        $this->assertFalse(LoginIdentifier::isPlausiblePhoneInput('12345'));
    }
}
