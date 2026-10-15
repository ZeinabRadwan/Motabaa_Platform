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
        $this->assertContains('966505226389', $international);
        $this->assertContains('0505226389', $international);

        $plus = LoginIdentifier::phoneMatchCandidates('+966505226389');
        $this->assertContains('966505226389', $plus);
        $this->assertContains('0505226389', $plus);
        $this->assertContains('+966505226389', $plus);

        $spaces = LoginIdentifier::phoneMatchCandidates(' +966 50 522 6389 ');
        $this->assertContains('966505226389', $spaces);
        $this->assertContains('0505226389', $spaces);

        $nationalNine = LoginIdentifier::phoneMatchCandidates('505226389');
        $this->assertContains('966505226389', $nationalNine);
        $this->assertContains('0505226389', $nationalNine);
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

    public function test_valid_saudi_mobile_formats(): void
    {
        $this->assertTrue(LoginIdentifier::isValidSaudiMobileInput('0505226389'));
        $this->assertTrue(LoginIdentifier::isValidSaudiMobileInput('966505226389'));
        $this->assertTrue(LoginIdentifier::isValidSaudiMobileInput('+966 50 522 6389'));
        $this->assertTrue(LoginIdentifier::isValidSaudiMobileInput('505226389'));
        $this->assertFalse(LoginIdentifier::isValidSaudiMobileInput('0412345678'));
        $this->assertFalse(LoginIdentifier::isValidSaudiMobileInput('12345678901'));
    }
}
