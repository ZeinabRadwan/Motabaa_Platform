<?php

namespace Tests\Feature;

use App\Support\ParentChunkedUpload;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

/**
 * Parent uploads on the goal details page (Individual Plan and Support Services) through the
 * chunked route, exercised end to end against local storage.
 */
class ParentGoalUploadTest extends ParentGoalsTestCase
{
    private int $caseId;

    private int $goalId;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('logs')) {
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
        }

        $this->caseId = $this->makeCase('فيصل سعد الدوسري', self::CENTER, linkParent: true);
        $this->goalId = $this->makeGoal($this->caseId, 'educational', media: 'none');
    }

    public function test_multi_chunk_video_resumes_after_an_interruption_and_is_shown_to_the_parent(): void
    {
        $video = $this->mp4Bytes(2 * ParentChunkedUpload::CHUNK_BYTES + 12345);
        $chunks = str_split($video, ParentChunkedUpload::CHUNK_BYTES);
        $this->assertCount(3, $chunks);

        $this->sendChunk('resume-upload-000001', 'clip.mp4', strlen($video), 0, $chunks[0])
            ->assertOk()->assertJsonPath('data.complete', false);

        // The connection dropped after chunk 1 reached the server; the client re-sends it.
        $this->sendChunk('resume-upload-000001', 'clip.mp4', strlen($video), 1, $chunks[1])->assertOk();
        $this->sendChunk('resume-upload-000001', 'clip.mp4', strlen($video), 1, $chunks[1])->assertOk();

        $response = $this->sendChunk('resume-upload-000001', 'clip.mp4', strlen($video), 2, $chunks[2], 'Home practice')
            ->assertOk();

        $mediaId = (int) $response->json('data.media.id');
        $this->assertNotNull($response->json('data.media.video'));
        $message = DB::table('messages')->find($mediaId);
        $this->assertSame(1, (int) $message->parents_can_see);
        $this->assertSame('Home practice', $message->content);
        $this->assertSame($video, $this->storedFileContents($mediaId, $message->video_path));

        $details = $this->parentGoalDetails($this->caseId, $this->goalId)->assertOk();
        $this->assertContains($mediaId, array_column($details->json('data.media'), 'id'));
    }

    public function test_retried_last_chunk_returns_the_same_message_without_duplicating_it(): void
    {
        $video = $this->mp4Bytes(ParentChunkedUpload::CHUNK_BYTES + 100);
        [$first, $last] = str_split($video, ParentChunkedUpload::CHUNK_BYTES);

        $this->sendChunk('retry-last-chunk-01', 'clip.mp4', strlen($video), 0, $first)->assertOk();
        $done = $this->sendChunk('retry-last-chunk-01', 'clip.mp4', strlen($video), 1, $last)->assertOk();
        $again = $this->sendChunk('retry-last-chunk-01', 'clip.mp4', strlen($video), 1, $last)->assertOk();

        $this->assertSame($done->json('data.media.id'), $again->json('data.media.id'));
        $this->assertSame(1, DB::table('messages')->where('goal_id', $this->goalId)->count());
    }

    public function test_chunk_arriving_while_the_file_is_being_saved_is_refused_with_423(): void
    {
        $video = $this->mp4Bytes(ParentChunkedUpload::CHUNK_BYTES + 100);
        [$first, $last] = str_split($video, ParentChunkedUpload::CHUNK_BYTES);
        $this->sendChunk('finalizing-upload-01', 'clip.mp4', strlen($video), 0, $first)->assertOk();

        $upload = new ParentChunkedUpload($this->parent->id, 'finalizing-upload-01');
        $response = $upload->withFinalizeLock(
            fn () => $this->sendChunk('finalizing-upload-01', 'clip.mp4', strlen($video), 1, $last)
        );

        $response->assertStatus(423)->assertJsonPath('reason', 'upload_finalizing');
        $this->assertSame(0, DB::table('messages')->where('goal_id', $this->goalId)->count());
    }

    public function test_upload_declared_above_200_mb_is_rejected_with_a_size_message(): void
    {
        $totalSize = 200 * 1024 * 1024 + 1;

        $this->sendChunk('too-large-upload-01', 'clip.mp4', $totalSize, 0, str_repeat("\x00", ParentChunkedUpload::CHUNK_BYTES))
            ->assertStatus(422)
            ->assertJsonPath('errors.video.0', __('validation.parent_goal_details.too_large', ['mb' => 200]));

        $this->assertDirectoryDoesNotExist((new ParentChunkedUpload($this->parent->id, 'too-large-upload-01'))->directory());
    }

    public function test_upload_of_exactly_200_mb_is_accepted(): void
    {
        $this->sendChunk('max-size-upload-001', 'clip.mov', 200 * 1024 * 1024, 0, str_repeat("\x00", ParentChunkedUpload::CHUNK_BYTES))
            ->assertOk()
            ->assertJsonPath('data.complete', false);
    }

    public function test_abandoned_uploads_do_not_lock_the_parent_out(): void
    {
        foreach (['abandoned-upload-01', 'abandoned-upload-02', 'abandoned-upload-03'] as $id) {
            $upload = new ParentChunkedUpload($this->parent->id, $id);
            $upload->begin(['goal_id' => $this->goalId]);
            touch($upload->directory().DIRECTORY_SEPARATOR.'meta.json', time() - ParentChunkedUpload::IDLE_SECONDS - 60);
        }

        $this->sendChunk('fresh-upload-000001', 'clip.mp4', ParentChunkedUpload::CHUNK_BYTES + 10, 0, str_repeat("\x00", ParentChunkedUpload::CHUNK_BYTES))
            ->assertOk();
        $this->assertSame(1, ParentChunkedUpload::activeUploadCount($this->parent->id));
    }

    public function test_three_uploads_in_progress_still_limit_a_fourth(): void
    {
        foreach (['running-upload-0001', 'running-upload-0002', 'running-upload-0003'] as $id) {
            (new ParentChunkedUpload($this->parent->id, $id))->begin(['goal_id' => $this->goalId]);
        }

        $this->sendChunk('fourth-upload-00001', 'clip.mp4', ParentChunkedUpload::CHUNK_BYTES + 10, 0, str_repeat("\x00", ParentChunkedUpload::CHUNK_BYTES))
            ->assertStatus(429)
            ->assertJsonPath('reason', 'too_many_uploads');
    }

    public function test_script_disguised_as_a_large_video_is_rejected_after_reassembly(): void
    {
        $payload = "<?php system(\$_GET['c']);\n".str_repeat('a', ParentChunkedUpload::CHUNK_BYTES);
        [$first, $last] = str_split($payload, ParentChunkedUpload::CHUNK_BYTES);

        $this->sendChunk('disguised-upload-01', 'clip.mp4', strlen($payload), 0, $first)->assertOk();
        $this->sendChunk('disguised-upload-01', 'clip.mp4', strlen($payload), 1, $last)->assertStatus(422);

        $this->assertSame(0, DB::table('messages')->where('goal_id', $this->goalId)->count());
    }

    public function test_unauthenticated_chunk_is_rejected(): void
    {
        $this->post("/api/goals/parent-details/{$this->goalId}/messages/chunks", [], ['Accept' => 'application/json'])
            ->assertStatus(401);
    }

    public function test_parent_cannot_upload_to_a_goal_of_another_beneficiary(): void
    {
        $otherCase = $this->makeCase('Other child', self::CENTER, linkParent: false);
        $otherGoal = $this->makeGoal($otherCase, 'educational', media: 'none');

        $response = $this->sendChunk('other-child-upload1', 'clip.mp4', 1000, 0, str_repeat("\x00", 1000), null, $otherGoal, $otherCase);

        $this->assertContains($response->status(), [403, 404]);
        $this->assertSame(0, DB::table('messages')->where('goal_id', $otherGoal)->count());
        $this->assertDirectoryDoesNotExist((new ParentChunkedUpload($this->parent->id, 'other-child-upload1'))->directory());
    }

    public function test_staff_account_cannot_use_the_parent_upload_route(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->post("/api/goals/parent-details/{$this->goalId}/messages/chunks", $this->chunkFields(
            'staff-upload-000001', 'clip.mp4', 1000, 0, $this->caseId
        ) + ['chunk' => UploadedFile::fake()->createWithContent('chunk', str_repeat("\x00", 1000))], ['Accept' => 'application/json']);

        $this->assertContains($response->status(), [403, 404]);
    }

    private function sendChunk(
        string $uploadId,
        string $fileName,
        int $totalSize,
        int $index,
        string $bytes,
        ?string $content = null,
        ?int $goalId = null,
        ?int $caseId = null
    ) {
        Sanctum::actingAs($this->parent);

        $fields = $this->chunkFields($uploadId, $fileName, $totalSize, $index, $caseId ?? $this->caseId);
        if ($content !== null) {
            $fields['content'] = $content;
        }
        $fields['chunk'] = $this->chunkFile($bytes);

        return $this->post(
            '/api/goals/parent-details/'.($goalId ?? $this->goalId).'/messages/chunks',
            $fields,
            ['Accept' => 'application/json']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function chunkFields(string $uploadId, string $fileName, int $totalSize, int $index, int $caseId): array
    {
        return [
            'center_id' => self::CENTER,
            'case_id' => $caseId,
            'section' => 'program',
            'field' => 'video',
            'upload_id' => $uploadId,
            'file_name' => $fileName,
            'total_size' => $totalSize,
            'total_chunks' => ParentChunkedUpload::expectedChunks($totalSize),
            'chunk_index' => $index,
        ];
    }

    /**
     * A closed temp file like PHP's own upload temp files (a fake upload keeps its handle
     * open, which stops Windows from replacing the chunk when it is re-sent).
     */
    private function chunkFile(string $bytes): UploadedFile
    {
        $path = $this->storageRoot.DIRECTORY_SEPARATOR.'upload-'.uniqid('', true).'.tmp';
        File::put($path, $bytes);

        return new UploadedFile($path, 'chunk', 'application/octet-stream', UPLOAD_ERR_OK, true);
    }

    private function mp4Bytes(int $size): string
    {
        $header = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom";

        return $header.str_repeat("\x00", $size - strlen($header));
    }

    private function storedFileContents(int $messageId, string $storedName): string
    {
        $path = $this->storageRoot."/app/public/goals/{$this->goalId}/messages/{$messageId}/{$storedName}";
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
