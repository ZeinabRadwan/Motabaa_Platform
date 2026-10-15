<?php

namespace Tests\Unit;

use App\Models\MeetingRoom;
use App\Models\MeetingRoomInvitationToken;
use App\Models\User;
use App\Support\MeetingRoomInvitationService;
use Illuminate\Support\Facades\Crypt;
use Mockery;
use Tests\TestCase;

class MeetingRoomInvitationServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_admin_in_center_can_bypass_invitation_redeem(): void
    {
        $room = new MeetingRoom([
            'id' => 5,
            'center_id' => 1,
            'audience_type' => MeetingRoom::AUDIENCE_LINK_INVITE,
        ]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->status = User::STATUS_CAN_LOGIN;
        $user->shouldReceive('isInCenter')->with(1)->andReturn(true);
        $user->shouldReceive('can')->with('admin_meetings')->andReturn(true);
        $user->shouldReceive('can')->with('edit_meetings')->andReturn(false);

        $this->assertTrue(MeetingRoomInvitationService::staffCanBypassInvitationRedeem($user, $room));
    }

    public function test_admin_outside_center_cannot_bypass_invitation_redeem(): void
    {
        $room = new MeetingRoom([
            'center_id' => 2,
            'audience_type' => MeetingRoom::AUDIENCE_LINK_INVITE,
        ]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isInCenter')->with(2)->andReturn(false);

        $this->assertFalse(MeetingRoomInvitationService::staffCanBypassInvitationRedeem($user, $room));
    }

    public function test_parent_cannot_bypass_invitation_redeem(): void
    {
        $room = new MeetingRoom([
            'center_id' => 1,
            'audience_type' => MeetingRoom::AUDIENCE_LINK_INVITE,
        ]);

        $parentRole = new \Spatie\Permission\Models\Role(['default_name' => 'parent']);
        $user = Mockery::mock(User::class)->makePartial();
        $user->status = User::STATUS_CAN_LOGIN;
        $user->shouldReceive('isInCenter')->with(1)->andReturn(true);
        $user->shouldReceive('can')->with('admin_meetings')->andReturn(false);
        $user->shouldReceive('can')->with('edit_meetings')->andReturn(false);
        $user->setRelation('roles', collect([$parentRole]));

        $this->assertFalse(MeetingRoomInvitationService::staffCanBypassInvitationRedeem($user, $room));
    }

    public function test_non_link_room_cannot_bypass(): void
    {
        $room = new MeetingRoom([
            'center_id' => 1,
            'audience_type' => MeetingRoom::AUDIENCE_GENERAL,
        ]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isInCenter')->with(1)->andReturn(true);
        $user->shouldReceive('can')->with('admin_meetings')->andReturn(true);

        $this->assertFalse(MeetingRoomInvitationService::staffCanBypassInvitationRedeem($user, $room));
    }

    public function test_plain_from_token_row_decrypts_encrypted_value(): void
    {
        $plain = 'test-plain-invitation-token-value-1234567890';
        $token = new MeetingRoomInvitationToken([
            'token_hash' => MeetingRoomInvitationService::hashToken($plain),
            'token_encrypted' => Crypt::encryptString($plain),
        ]);

        $this->assertSame($plain, MeetingRoomInvitationService::plainFromTokenRow($token));
    }

    public function test_legacy_token_row_without_encryption_is_not_retrievable(): void
    {
        $token = new MeetingRoomInvitationToken([
            'token_hash' => hash('sha256', 'legacy-plain'),
            'token_encrypted' => null,
        ]);

        $this->assertNull(MeetingRoomInvitationService::plainFromTokenRow($token));
    }

    public function test_build_invitation_url_includes_token_query(): void
    {
        config(['app.front_url' => 'https://app.example.test']);

        $url = MeetingRoomInvitationService::buildInvitationUrl('abc+def');

        $this->assertStringContainsString('/meeting-rooms/invite?token=', $url);
        $this->assertStringContainsString(urlencode('abc+def'), $url);
    }
}
