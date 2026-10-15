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

    public function test_link_invite_parent_without_grant_is_denied(): void
    {
        $room = new MeetingRoom([
            'center_id' => 1,
            'audience_type' => MeetingRoom::AUDIENCE_LINK_INVITE,
        ]);

        $parentRole = new \Spatie\Permission\Models\Role(['default_name' => 'parent']);
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 7;
        $user->status = User::STATUS_CAN_LOGIN;
        $user->shouldReceive('isInCenter')->with(1)->andReturn(true);
        $user->shouldReceive('can')->andReturn(false);
        $user->setRelation('roles', collect([$parentRole]));

        $this->assertFalse(MeetingRoomAccess::userCanAccess($user, $room));
        $this->assertFalse(MeetingRoomAccess::parentCanListRoom($user, $room));
    }

    public function test_link_invite_room_hidden_from_parent_list(): void
    {
        $room = new MeetingRoom([
            'center_id' => 1,
            'audience_type' => MeetingRoom::AUDIENCE_LINK_INVITE,
        ]);

        $parentRole = new \Spatie\Permission\Models\Role(['default_name' => 'parent']);
        $user = Mockery::mock(User::class)->makePartial();
        $user->status = User::STATUS_CAN_LOGIN;
        $user->shouldReceive('isInCenter')->with(1)->andReturn(true);
        $user->setRelation('roles', collect([$parentRole]));

        $this->assertFalse(MeetingRoomAccess::parentCanListRoom($user, $room));
    }

    public function test_parent_cannot_list_soft_deleted_room(): void
    {
        $room = Mockery::mock(MeetingRoom::class)->makePartial();
        $room->center_id = 1;
        $room->audience_type = MeetingRoom::AUDIENCE_GENERAL;
        $room->type = MeetingRoom::TYPE_GENERAL;
        $room->created_by_id = 99;
        $room->shouldReceive('trashed')->andReturn(true);

        $parentRole = new \Spatie\Permission\Models\Role(['default_name' => 'parent']);
        $user = Mockery::mock(User::class)->makePartial();
        $user->status = User::STATUS_CAN_LOGIN;
        $user->shouldReceive('isInCenter')->with(1)->andReturn(true);
        $user->shouldReceive('can')->andReturn(false);
        $user->setRelation('roles', collect([$parentRole]));

        $this->assertFalse(MeetingRoomAccess::parentCanListRoom($user, $room));
        $this->assertFalse(MeetingRoomAccess::userCanAccess($user, $room));
    }

    public function test_parent_list_user_detection(): void
    {
        $parentRole = new \Spatie\Permission\Models\Role(['default_name' => 'parent']);
        $parentOnly = Mockery::mock(User::class)->makePartial();
        $parentOnly->shouldReceive('can')->andReturn(false);
        $parentOnly->setRelation('roles', collect([$parentRole]));
        $this->assertTrue(MeetingRoomAccess::isParentListUser($parentOnly));

        $parentManager = Mockery::mock(User::class)->makePartial();
        $parentManager->shouldReceive('can')->with('admin_meetings')->andReturn(false);
        $parentManager->shouldReceive('can')->with('edit_meetings')->andReturn(true);
        $parentManager->setRelation('roles', collect([$parentRole]));
        $this->assertFalse(MeetingRoomAccess::isParentListUser($parentManager));
    }

    public function test_parent_can_list_public_and_assigned_rooms(): void
    {
        $publicRoom = new MeetingRoom([
            'center_id' => 1,
            'audience_type' => MeetingRoom::AUDIENCE_GENERAL,
        ]);

        $assignedRoom = Mockery::mock(MeetingRoom::class)->makePartial();
        $assignedRoom->center_id = 1;
        $assignedRoom->audience_type = MeetingRoom::AUDIENCE_PARENTS;
        $assignedRoom->deleted_at = null;
        $relation = Mockery::mock();
        $relation->shouldReceive('where')->with('users.id', 5)->andReturnSelf();
        $relation->shouldReceive('exists')->andReturn(true);
        $assignedRoom->shouldReceive('assignedParents')->andReturn($relation);

        $parentRole = new \Spatie\Permission\Models\Role(['default_name' => 'parent']);
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 5;
        $user->status = User::STATUS_CAN_LOGIN;
        $user->shouldReceive('isInCenter')->with(1)->andReturn(true);
        $user->shouldReceive('can')->andReturn(false);
        $user->setRelation('roles', collect([$parentRole]));

        $this->assertTrue(MeetingRoomAccess::parentCanListRoom($user, $publicRoom));
        $this->assertTrue(MeetingRoomAccess::parentCanListRoom($user, $assignedRoom));
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
