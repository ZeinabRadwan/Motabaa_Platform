<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Support\Collection;
use Tests\TestCase;

class EmbeddedUserReferenceTest extends TestCase
{
    public function test_minimal_user_reference_from_model(): void
    {
        $user = new User(['name' => 'Staff Member', 'email' => 'secret@example.com']);
        $user->id = 5;

        $this->assertSame(['id' => 5, 'name' => 'Staff Member'], minimalUserReference($user));
    }

    public function test_minimal_user_reference_from_relation_collection(): void
    {
        $teacher = new User(['name' => 'Teacher', 'phone' => '0500000000']);
        $teacher->id = 2;
        $users = new Collection([$teacher]);

        $this->assertSame(['id' => 2, 'name' => 'Teacher'], minimalUserReferenceFromRelation($users));
    }
}
