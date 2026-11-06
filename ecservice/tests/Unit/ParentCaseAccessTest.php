<?php

namespace Tests\Unit;

use App\Support\ParentCaseAccess;
use Tests\TestCase;

class ParentCaseAccessTest extends TestCase
{
    public function test_parent_linked_case_is_allowed(): void
    {
        $this->assertFalse(ParentCaseAccess::shouldDeny(true, true, true));
    }

    public function test_parent_unlinked_case_is_denied(): void
    {
        $this->assertTrue(ParentCaseAccess::shouldDeny(true, true, false));
    }

    public function test_parent_missing_case_is_denied(): void
    {
        $this->assertTrue(ParentCaseAccess::shouldDeny(true, false, false));
    }

    public function test_staff_is_never_denied_by_parent_guard(): void
    {
        $this->assertFalse(ParentCaseAccess::shouldDeny(false, true, false));
        $this->assertFalse(ParentCaseAccess::shouldDeny(false, false, false));
    }

    public function test_guest_or_staff_user_short_circuits_without_database(): void
    {
        $this->assertNull(ParentCaseAccess::denyIfParentCannotAccessCase(123, null));
    }
}
