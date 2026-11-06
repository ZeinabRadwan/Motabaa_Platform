<?php

namespace Tests\Unit;

use App\Support\ParentIdentity;
use Tests\TestCase;

class ParentIdentityTest extends TestCase
{
    public function test_email_normalization_is_trimmed_and_lowercase(): void
    {
        $this->assertSame('parent@example.com', ParentIdentity::normalizeEmail('  Parent@Example.COM  '));
        $this->assertNull(ParentIdentity::normalizeEmail('not-an-email'));
        $this->assertNull(ParentIdentity::normalizeEmail(null));
    }

    public function test_canonical_phone_for_saudi_mobile(): void
    {
        $this->assertSame('966505226389', ParentIdentity::canonicalPhone('0505226389'));
        $this->assertSame('966505226389', ParentIdentity::canonicalPhone('+966 50 522 6389'));
        $this->assertSame('966506538118', ParentIdentity::canonicalPhone('966506538118'));
        $this->assertSame('966506538118', ParentIdentity::canonicalPhone('0506538118'));
    }
}
