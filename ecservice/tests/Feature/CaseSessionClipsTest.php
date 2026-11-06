<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\System\System;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/**
 * Case profile tabs: "Session Clips" (`GET /api/messages?case_id=`) and "Attachments"
 * (`/api/cases/{case}/files`). Same isolated SQLite fixture as the parent goal tests.
 */
class CaseSessionClipsTest extends ParentGoalsTestCase
{
    private User $teacher;

    private User $otherCenterManager;

    /** @var array<string, int> */
    private array $case;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('permissions')->insert(['name' => 'edit_cases', 'guard_name' => 'web']);
        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('center_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('type')->nullable();
            $table->text('description')->nullable();
            $table->string('url')->nullable();
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->text('search_text')->nullable();
            $table->timestamps();
        });

        $this->teacher = $this->makeUser('teacher@example.test', 'teacher');
        $this->otherCenterManager = $this->makeUser('other-manager@example.test', 'manager');
        DB::table('centers_users')->where('user_id', $this->otherCenterManager->id)->update(['center_id' => self::OTHER_CENTER]);

        $this->case = $this->beneficiaryWithContributions();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_staff_see_teacher_and_parent_contributions_newest_first(): void
    {
        $rows = $this->clips($this->staff, $this->case['id'])->assertOk()->json('data');

        $this->assertSame([
            $this->case['parent_photo'],
            $this->case['teacher_comment'],
            $this->case['session_ended'],
            $this->case['teacher_hidden_video'],
            $this->case['teacher_session_image'],
            $this->case['session_started'],
        ], array_column($rows, 'id'));

        $byId = array_column($rows, null, 'id');
        $this->assertSame('parent', $byId[$this->case['parent_photo']]['sender_type']);
        $this->assertSame($this->parent->id, $byId[$this->case['parent_photo']]['user']['id']);
        $this->assertSame('staff', $byId[$this->case['teacher_session_image']]['sender_type']);
        $this->assertSame($this->teacher->id, $byId[$this->case['teacher_session_image']]['user']['id']);
        $this->assertNotNull($byId[$this->case['parent_photo']]['image']);
        $this->assertNotNull($byId[$this->case['teacher_session_image']]['image']);
    }

    public function test_each_contribution_is_listed_once_and_keeps_its_session(): void
    {
        $rows = $this->clips($this->staff, $this->case['id'], ['options' => ['itemsPerPage' => 50]])->json('data');

        $ids = array_column($rows, 'id');
        $this->assertSame(count($ids), count(array_unique($ids)));

        $byId = array_column($rows, null, 'id');
        $this->assertSame($this->case['program_goal'], $byId[$this->case['teacher_session_image']]['goal']['id']);
        $this->assertSame('educational', $byId[$this->case['teacher_session_image']]['goal']['category']);
        $this->assertSame($this->case['speech_goal'], $byId[$this->case['parent_photo']]['goal']['id']);
        $this->assertSame('pronouncement', $byId[$this->case['parent_photo']]['goal']['category']);
    }

    public function test_paging_returns_every_contribution_exactly_once(): void
    {
        $seen = [];
        foreach ([1, 2, 3] as $page) {
            $rows = $this->clips($this->staff, $this->case['id'], ['options' => ['itemsPerPage' => 2], 'page' => $page])->json('data');
            $seen = array_merge($seen, array_column($rows, 'id'));
        }

        $this->assertCount(6, $seen);
        $this->assertSame($seen, array_values(array_unique($seen)));
    }

    public function test_another_beneficiarys_contributions_are_not_included(): void
    {
        $ids = array_column($this->clips($this->staff, $this->case['id'])->json('data'), 'id');
        $this->assertNotContains($this->case['other_case_message'], $ids);

        $otherIds = array_column($this->clips($this->staff, $this->case['other_case'])->json('data'), 'id');
        $this->assertSame([$this->case['other_case_message']], $otherIds);
    }

    public function test_contribution_filters_separate_session_activity_staff_comments_and_parents(): void
    {
        $session = array_column($this->clips($this->staff, $this->case['id'], ['contribution' => 'session'])->json('data'), 'id');
        $this->assertSame([
            $this->case['session_ended'],
            $this->case['teacher_hidden_video'],
            $this->case['teacher_session_image'],
            $this->case['session_started'],
        ], $session);

        $comments = array_column($this->clips($this->staff, $this->case['id'], ['contribution' => 'staff_comment'])->json('data'), 'id');
        $this->assertSame([$this->case['teacher_comment']], $comments);

        $parents = array_column($this->clips($this->staff, $this->case['id'], ['contribution' => 'parent'])->json('data'), 'id');
        $this->assertSame([$this->case['parent_photo']], $parents);
    }

    public function test_parent_sees_shared_items_and_own_contribution_only(): void
    {
        $ids = array_column($this->clips($this->parent, $this->case['id'])->assertOk()->json('data'), 'id');

        $this->assertSame([
            $this->case['parent_photo'],
            $this->case['teacher_comment'],
            $this->case['teacher_session_image'],
        ], $ids);
    }

    public function test_parent_cannot_read_unlinked_beneficiary(): void
    {
        $this->clips($this->parent, $this->case['other_case'])->assertForbidden();
        $this->files($this->parent, $this->case['other_case'])->assertForbidden();
    }

    public function test_assigned_teacher_can_read_and_unassigned_teacher_cannot(): void
    {
        $this->clips($this->teacher, $this->case['id'])->assertOk();
        $this->clips($this->teacher, $this->case['other_case'])->assertForbidden();
    }

    public function test_staff_of_another_center_cannot_read_clips_or_documents(): void
    {
        $this->clips($this->otherCenterManager, $this->case['id'])->assertForbidden();
        $this->files($this->otherCenterManager, $this->case['id'])->assertForbidden();

        $outsideCase = $this->makeCase('Other center child', self::OTHER_CENTER, linkParent: false);
        $this->clips($this->staff, $outsideCase)->assertForbidden();
        $this->clips($this->otherCenterManager, $outsideCase)->assertOk();
    }

    public function test_platform_admin_keeps_access(): void
    {
        $this->clips($this->admin, $this->case['id'])->assertOk();
        $this->files($this->admin, $this->case['id'])->assertOk();
    }

    public function test_case_documents_are_listed_for_staff_and_linked_parent(): void
    {
        $this->storeCaseDocument($this->case['id'], 'Medical_report.pdf');

        $staffNames = array_column($this->files($this->staff, $this->case['id'])->assertOk()->json('data'), 'file_name');
        $this->assertSame(['Medical_report'], $staffNames);

        $parentNames = array_column($this->files($this->parent, $this->case['id'])->assertOk()->json('data'), 'file_name');
        $this->assertSame(['Medical_report'], $parentNames);
    }

    public function test_only_staff_with_edit_cases_can_upload_or_delete_case_documents(): void
    {
        $caseId = $this->case['id'];
        $upload = fn () => ['file_name' => 'Assessment', 'file' => UploadedFile::fake()->create('assessment.pdf', 4, 'application/pdf')];

        Sanctum::actingAs($this->parent);
        $this->post("/api/cases/{$caseId}/add_file", $upload())->assertForbidden();

        Sanctum::actingAs($this->teacher);
        $this->post("/api/cases/{$caseId}/add_file", $upload())->assertForbidden();

        $this->teacher->givePermissionTo('edit_cases');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($this->teacher->fresh());
        $this->post("/api/cases/{$caseId}/add_file", $upload())->assertOk();
        $this->assertTrue(File::exists($this->documentsDirectory($caseId).'/Assessment.pdf'));

        Sanctum::actingAs($this->parent);
        $this->post("/api/cases/{$caseId}/delete_file", ['file_name' => 'Assessment', 'file_extension' => 'pdf'])->assertForbidden();
        $this->assertTrue(File::exists($this->documentsDirectory($caseId).'/Assessment.pdf'));

        Sanctum::actingAs($this->teacher->fresh());
        $this->post("/api/cases/{$caseId}/delete_file", ['file_name' => 'Assessment', 'file_extension' => 'pdf'])->assertOk();
        $this->assertFalse(File::exists($this->documentsDirectory($caseId).'/Assessment.pdf'));
    }

    public function test_session_clips_and_case_documents_do_not_share_storage(): void
    {
        $this->storeCaseDocument($this->case['id'], 'Medical_report.pdf');

        $documents = array_column($this->files($this->staff, $this->case['id'])->json('data'), 'file_name');
        $this->assertNotContains('message_image', $documents);

        $clipFiles = collect($this->clips($this->staff, $this->case['id'])->json('data'))
            ->flatMap(fn ($row) => [$row['image']['file_name'] ?? null, $row['file']['file_name'] ?? null, $row['video']['file_name'] ?? null])
            ->filter()
            ->all();
        $this->assertNotContains('Medical_report', $clipFiles);
    }

    public function test_sender_type_follows_parent_only_role_rule(): void
    {
        $message = Message::with('user.roles')->findOrFail($this->case['parent_photo']);
        $this->assertSame(Message::SENDER_PARENT, $message->senderType());

        $teacherMessage = Message::with('user.roles')->findOrFail($this->case['teacher_comment']);
        $this->assertSame(Message::SENDER_STAFF, $teacherMessage->senderType());

        DB::table('users')->where('id', $this->teacher->id)->delete();
        $orphan = Message::with('user.roles')->findOrFail($this->case['teacher_comment']);
        $this->assertNull($orphan->senderType());
    }

    /**
     * Case with an individual-plan goal (teacher sessions) and a speech therapy goal where the
     * parent uploaded a photo; plus a second beneficiary in the same center with its own message.
     *
     * @return array<string, int>
     */
    private function beneficiaryWithContributions(): array
    {
        $case = $this->makeCase('Case 70 fixture', self::CENTER, linkParent: true);
        DB::table('scase_user')->insert([
            'scase_id' => $case, 'user_id' => $this->teacher->id, 'relationship_type' => System::USER_TYPE_TEACHER,
        ]);
        $programGoal = $this->makeGoal($case, 'educational', media: 'none');
        $speechGoal = $this->makeGoal($case, 'pronouncement', media: 'none');

        $otherCase = $this->makeCase('Sibling in same center', self::CENTER, linkParent: false);
        $otherGoal = $this->makeGoal($otherCase, 'educational', media: 'none');

        return [
            'id' => $case,
            'program_goal' => $programGoal,
            'speech_goal' => $speechGoal,
            'session_started' => $this->insertMessage($programGoal, $this->teacher, 6, type: Message::SYS_STARTED_SESSION, content: 'started_session'),
            'teacher_session_image' => $this->insertMessage($programGoal, $this->teacher, 5, logWork: 1, shared: true, media: 'image'),
            'teacher_hidden_video' => $this->insertMessage($programGoal, $this->teacher, 4, logWork: 1, shared: false, media: 'video'),
            'session_ended' => $this->insertMessage($programGoal, $this->teacher, 3, type: Message::SYS_ENDED_SESSION, content: 'ended_session'),
            'teacher_comment' => $this->insertMessage($speechGoal, $this->teacher, 2, shared: true),
            'parent_photo' => $this->insertMessage($speechGoal, $this->parent, 1, shared: true, media: 'image'),
            'other_case' => $otherCase,
            'other_case_message' => $this->insertMessage($otherGoal, $this->staff, 1, logWork: 1, shared: true),
        ];
    }

    private function insertMessage(
        int $goalId,
        User $author,
        int $daysAgo,
        int $logWork = 0,
        bool $shared = false,
        ?string $type = null,
        string $content = 'Note',
        ?string $media = null
    ): int {
        $names = ['image' => 'message_image', 'video' => 'message_video'];
        $extensions = ['image' => 'jpg', 'video' => 'mp4'];
        $paths = ['image_path' => '', 'file_path' => '', 'video_path' => ''];
        if ($media !== null) {
            $paths[$media.'_path'] = $names[$media].'.'.$extensions[$media];
        }

        $id = (int) DB::table('messages')->insertGetId([
            'goal_id' => $goalId,
            'user_id' => $author->id,
            'content' => $content,
            'type' => $type,
            'log_work' => $logWork,
            'parents_can_see' => $shared ? 1 : 0,
            'created_at' => now()->subDays($daysAgo)->toDateTimeString(),
        ] + $paths);

        if ($media !== null) {
            $directory = $this->storageRoot."/app/public/goals/{$goalId}/messages/{$id}";
            File::ensureDirectoryExists($directory);
            File::put($directory.'/'.$names[$media].'.'.$extensions[$media], 'media');
        }

        return $id;
    }

    private function documentsDirectory(int $caseId): string
    {
        return $this->storageRoot."/app/public/child_case/{$caseId}/attachments";
    }

    private function storeCaseDocument(int $caseId, string $fileName): void
    {
        File::ensureDirectoryExists($this->documentsDirectory($caseId));
        File::put($this->documentsDirectory($caseId).'/'.$fileName, 'pdf');
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function clips(User $as, int $caseId, array $extra = [])
    {
        Sanctum::actingAs($as);

        return $this->getJson('/api/messages?'.http_build_query(['case_id' => $caseId] + $extra));
    }

    private function files(User $as, int $caseId)
    {
        Sanctum::actingAs($as);

        return $this->postJson("/api/cases/{$caseId}/files", [
            'options' => ['page' => 1, 'itemsPerPage' => 10],
            'page' => 1,
        ]);
    }
}
