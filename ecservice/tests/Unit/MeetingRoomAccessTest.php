<?php

namespace Tests\Unit;

use App\Models\MeetingRoom;
use App\Models\User;
use App\Support\MeetingRoomAccess;
use Mockery;
use Tests\TestCase;

class MeetingRoomAccessTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_parent_specific_audience_detection(): void
    {
        $parentsRoom = new MeetingRoom(['audience_type' => MeetingRoom::AUDIENCE_PARENTS]);
        $this->assertTrue(MeetingRoomAccess::isParentSpecificAudience($parentsRoom));

        $generalRoom = new MeetingRoom(['audience_type' => MeetingRoom::AUDIENCE_GENERAL]);
        $this->assertFalse(MeetingRoomAccess::isParentSpecificAudience($generalRoom));

        $legacyRoom = new MeetingRoom([]);
        $this->assertFalse(MeetingRoomAccess::isParentSpecificAudience($legacyRoom));
    }

    public function test_admin_meetings_can_access_parent_specific_room(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->status = User::STATUS_CAN_LOGIN;
        $user->shouldReceive('isInCenter')->with(1)->andReturn(true);
        $user->shouldReceive('can')->with('admin_meetings')->andReturn(true);

        $room = new MeetingRoom([
            'center_id' => 1,
            'audience_type' => MeetingRoom::AUDIENCE_PARENTS,
        ]);

        $this->assertTrue(MeetingRoomAccess::userCanAccess($user, $room));
        $this->assertTrue(MeetingRoomAccess::userCanViewMeetingUrl($user, $room));
    }

    public function test_user_outside_center_is_denied(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->status = User::STATUS_CAN_LOGIN;
        $user->shouldReceive('isInCenter')->with(2)->andReturn(false);

        $room = new MeetingRoom([
            'center_id' => 2,
            'audience_type' => MeetingRoom::AUDIENCE_GENERAL,
            'type' => MeetingRoom::TYPE_GENERAL,
        ]);

        $this->assertFalse(MeetingRoomAccess::userCanAccess($user, $room));
    }

    public function test_assigned_parent_can_access_parent_specific_room(): void
    {
        $room = Mockery::mock(MeetingRoom::class)->makePartial();
        $room->center_id = 1;
        $room->audience_type = MeetingRoom::AUDIENCE_PARENTS;
        $room->created_by_id = 99;
        $room->type = MeetingRoom::TYPE_GENERAL;

        $relation = Mockery::mock();
        $relation->shouldReceive('where')->with('users.id', 7)->andReturnSelf();
        $relation->shouldReceive('exists')->andReturn(true);
        $room->shouldReceive('assignedParents')->andReturn($relation);

        $parentRole = new \Spatie\Permission\Models\Role(['default_name' => 'parent']);
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 7;
        $user->status = User::STATUS_CAN_LOGIN;
        $user->shouldReceive('isInCenter')->with(1)->andReturn(true);
        $user->shouldReceive('can')->andReturn(false);
        $user->setRelation('roles', collect([$parentRole]));

        $this->assertTrue(MeetingRoomAccess::userCanAccess($user, $room));
        $this->assertTrue(MeetingRoomAccess::userCanViewMeetingUrl($user, $room));
    }

    public function test_unassigned_parent_cannot_access_parent_specific_room(): void
    {
        $room = Mockery::mock(MeetingRoom::class)->makePartial();
        $room->center_id = 1;
        $room->audience_type = MeetingRoom::AUDIENCE_PARENTS;
        $room->created_by_id = 99;
        $room->type = MeetingRoom::TYPE_GENERAL;

        $relation = Mockery::mock();
        $relation->shouldReceive('where')->with('users.id', 7)->andReturnSelf();
        $relation->shouldReceive('exists')->andReturn(false);
        $room->shouldReceive('assignedParents')->andReturn($relation);

        $parentRole = new \Spatie\Permission\Models\Role(['default_name' => 'parent']);
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 7;
        $user->status = User::STATUS_CAN_LOGIN;
        $user->shouldReceive('isInCenter')->with(1)->andReturn(true);
        $user->shouldReceive('can')->andReturn(false);
        $user->setRelation('roles', collect([$parentRole]));

        $this->assertFalse(MeetingRoomAccess::userCanAccess($user, $room));
        $this->assertFalse(MeetingRoomAccess::userCanViewMeetingUrl($user, $room));
    }

    public function test_general_type_room_accessible_to_any_parent_in_center(): void
    {
        $room = new MeetingRoom([
            'center_id' => 1,
            'audience_type' => MeetingRoom::AUDIENCE_GENERAL,
            'type' => MeetingRoom::TYPE_GENERAL,
            'created_by_id' => 50,
        ]);

        $parentRole = new \Spatie\Permission\Models\Role(['default_name' => 'parent']);
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 7;
        $user->status = User::STATUS_CAN_LOGIN;
        $user->shouldReceive('isInCenter')->with(1)->andReturn(true);
        $user->shouldReceive('can')->andReturn(false);
        $user->setRelation('roles', collect([$parentRole]));

        $this->assertTrue(MeetingRoomAccess::userCanAccess($user, $room));
    }

    public function test_assigned_parent_with_parent_role_can_access_via_has_role_check(): void
    {
        $room = Mockery::mock(MeetingRoom::class)->makePartial();
        $room->center_id = 1;
        $room->audience_type = MeetingRoom::AUDIENCE_PARENTS;

        $relation = Mockery::mock();
        $relation->shouldReceive('where')->with('users.id', 12)->andReturnSelf();
        $relation->shouldReceive('exists')->andReturn(true);
        $room->shouldReceive('assignedParents')->andReturn($relation);

        $parentRole = new \Spatie\Permission\Models\Role(['default_name' => 'parent']);
        $staffRole = new \Spatie\Permission\Models\Role(['default_name' => 'teacher']);
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 12;
        $user->status = User::STATUS_CAN_LOGIN;
        $user->shouldReceive('isInCenter')->with(1)->andReturn(true);
        $user->shouldReceive('can')->andReturn(false);
        $user->setRelation('roles', collect([$parentRole, $staffRole]));

        $this->assertTrue(MeetingRoomAccess::assignedParentCanAccess($user, $room));
        $this->assertTrue(MeetingRoomAccess::userCanAccess($user, $room));
    }
}
