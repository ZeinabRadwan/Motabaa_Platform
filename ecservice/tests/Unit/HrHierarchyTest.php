<?php

namespace Tests\Unit;

use App\Support\Hr\HrHierarchy;
use Tests\TestCase;

class HrHierarchyTest extends TestCase
{
    /** child => parent */
    private const TREE = [1 => null, 2 => 1, 3 => 2, 4 => 1, 5 => '4'];

    private function parentOf(): callable
    {
        return fn (int $id) => self::TREE[$id] ?? null;
    }

    public function test_moving_under_an_unrelated_branch_is_allowed(): void
    {
        $this->assertFalse(HrHierarchy::createsCycle(3, 4, $this->parentOf()));
        $this->assertFalse(HrHierarchy::createsCycle(2, null, $this->parentOf()));
    }

    public function test_moving_under_itself_or_a_descendant_is_a_cycle(): void
    {
        $this->assertTrue(HrHierarchy::createsCycle(2, 2, $this->parentOf()));
        $this->assertTrue(HrHierarchy::createsCycle(1, 3, $this->parentOf()));
        $this->assertTrue(HrHierarchy::createsCycle(4, 5, $this->parentOf()), 'string ids from the database are normalized');
    }

    public function test_existing_corrupt_loops_are_reported_instead_of_spinning(): void
    {
        $loop = [10 => 11, 11 => 10];

        $this->assertTrue(HrHierarchy::createsCycle(99, 10, fn (int $id) => $loop[$id] ?? null));
    }
}
