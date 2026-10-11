<?php

namespace Tests\Unit;

use App\Models\Message;
use Tests\TestCase;

class MessageAttachmentPathsTest extends TestCase
{
    public function test_each_message_has_isolated_storage_path(): void
    {
        $first = new Message(['goal_id' => 5]);
        $first->id = 10;
        $second = new Message(['goal_id' => 5]);
        $second->id = 11;

        $this->assertSame($first->storagePath(), $first->storagePath());
        $this->assertStringContainsString('/messages/10', $first->storagePath());
        $this->assertStringContainsString('/messages/11', $second->storagePath());
        $this->assertNotSame($first->storagePath(), $second->storagePath());
    }

    public function test_skips_attachment_lookup_only_when_all_paths_confirmed_empty(): void
    {
        $empty = new Message(['type' => null]);
        $empty->id = 1;
        $empty->setAttribute('image_path', '');
        $empty->setAttribute('file_path', '');
        $empty->setAttribute('video_path', '');

        $this->assertTrue($empty->skipsAttachmentLookup());

        $legacy = new Message([
            'image_path' => null,
            'file_path' => null,
            'video_path' => null,
            'type' => null,
        ]);
        $legacy->id = 2;

        $this->assertFalse($legacy->skipsAttachmentLookup());
    }

}
