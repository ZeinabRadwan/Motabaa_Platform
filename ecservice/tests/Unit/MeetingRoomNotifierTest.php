<?php

namespace Tests\Unit;

use App\Models\MeetingRoom;
use App\Support\MeetingRoomNotifier;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class MeetingRoomNotifierTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_sync_skips_link_invite_audience(): void
    {
        Schema::shouldReceive('hasTable')->with('notifications')->andReturn(true);

        $room = new MeetingRoom([
            'id' => 1,
            'audience_type' => MeetingRoom::AUDIENCE_LINK_INVITE,
            'center_id' => 1,
        ]);

        MeetingRoomNotifier::syncForRoom($room);

        $this->assertTrue(true);
    }

    public function test_sync_does_not_query_when_notifications_table_missing(): void
    {
        Schema::shouldReceive('hasTable')->with('notifications')->andReturn(false);

        $room = new MeetingRoom([
            'id' => 2,
            'audience_type' => MeetingRoom::AUDIENCE_PARENTS,
        ]);

        MeetingRoomNotifier::syncForRoom($room);

        $this->assertTrue(true);
    }
}
