<?php

namespace Tests\Unit;

use App\Models\Message;
use App\Support\GoalParentVisibility;
use Tests\TestCase;

class GoalParentVisibilityTest extends TestCase
{
    public function test_parent_message_constraint_does_not_require_attachments(): void
    {
        $query = Message::query();
        GoalParentVisibility::applyParentVisibleMessageConstraints($query);

        $sql = $query->toSql();
        $this->assertStringContainsString('parents_can_see', $sql);
        foreach (['image_path', 'file_path', 'video_path'] as $column) {
            $this->assertStringNotContainsString($column, $sql);
        }
        $this->assertContains(Message::SYS_STARTED_SESSION, $query->getBindings());
    }

    public function test_shared_text_message_is_parent_visible_and_staff_only_or_system_messages_are_not(): void
    {
        $text = new Message(['parents_can_see' => 1, 'content' => 'Session went well']);
        $text->image_path = $text->file_path = $text->video_path = '';
        $staffOnly = new Message(['parents_can_see' => 0, 'content' => 'Internal note']);
        $system = new Message(['parents_can_see' => 1, 'type' => Message::SYS_STARTED_SESSION]);

        $this->assertTrue(GoalParentVisibility::messageIsParentVisible($text));
        $this->assertFalse(GoalParentVisibility::messageIsParentVisible($staffOnly));
        $this->assertFalse(GoalParentVisibility::messageIsParentVisible($system));
    }
}
