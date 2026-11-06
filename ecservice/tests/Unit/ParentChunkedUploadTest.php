<?php

namespace Tests\Unit;

use App\Http\Requests\ParentGoalMessageChunkRequest;
use App\Http\Requests\ParentGoalMessageRequest;
use App\Support\ParentChunkedUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ParentChunkedUploadTest extends TestCase
{
    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pgu-'.uniqid();
        File::ensureDirectoryExists($this->storage.'/app');
        $this->app->useStoragePath($this->storage);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    private function chunkValidator(array $input, UploadedFile $chunk): \Illuminate\Validation\Validator
    {
        $request = ParentGoalMessageChunkRequest::create('/x', 'POST', $input, [], ['chunk' => $chunk]);
        $validator = Validator::make($request->all(), $request->rules(), $request->messages());
        $request->withValidator($validator);
        $validator->passes();

        return $validator;
    }

    private function chunkInput(array $overrides = []): array
    {
        return $overrides + [
            'upload_id' => 'abcdefghijklmnop1234',
            'field' => 'video',
            'file_name' => 'clip.mp4',
            'total_size' => 21 * 1024 * 1024,
            'total_chunks' => 6,
            'chunk_index' => 0,
        ];
    }

    public function test_chunk_math_for_a_21_mb_video(): void
    {
        $size = 21 * 1024 * 1024;

        $this->assertSame(6, ParentChunkedUpload::expectedChunks($size));
        $this->assertSame(ParentChunkedUpload::CHUNK_BYTES, ParentChunkedUpload::expectedChunkSize(0, $size));
        $this->assertSame(1024 * 1024, ParentChunkedUpload::expectedChunkSize(5, $size));
    }

    public function test_largest_allowed_video_needs_50_chunks(): void
    {
        $this->assertSame(50, ParentChunkedUpload::expectedChunks(ParentGoalMessageRequest::MAX_KILOBYTES['video'] * 1024));
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function totalSizesWithinTheLimit(): array
    {
        return [
            '5 MB' => [5 * 1024 * 1024],
            '21 MB' => [21 * 1024 * 1024],
            '50 MB' => [50 * 1024 * 1024],
            '100 MB' => [100 * 1024 * 1024],
            '199 MB' => [199 * 1024 * 1024],
            '200 MB' => [200 * 1024 * 1024],
        ];
    }

    /**
     * @dataProvider totalSizesWithinTheLimit
     */
    public function test_chunk_request_accepts_totals_up_to_200_mb(int $totalSize): void
    {
        foreach (['video' => 'clip.mov', 'file' => 'report.pdf', 'image' => 'photo.jpg'] as $field => $name) {
            $validator = $this->chunkValidator(
                $this->chunkInput([
                    'field' => $field,
                    'file_name' => $name,
                    'total_size' => $totalSize,
                    'total_chunks' => ParentChunkedUpload::expectedChunks($totalSize),
                ]),
                UploadedFile::fake()->create('blob', 4096)
            );

            $this->assertFalse($validator->errors()->any(), $field.': '.$validator->errors()->first());
        }
    }

    public function test_rejects_unsafe_upload_ids(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ParentChunkedUpload(1, '../../etc/passwd-xxxxxxxx');
    }

    public function test_reassembles_chunks_byte_for_byte_and_is_idempotent_on_retry(): void
    {
        $upload = new ParentChunkedUpload(7, 'upload-0000000000000001');
        $upload->begin(['goal_id' => 1]);

        $upload->storeChunk(0, UploadedFile::fake()->createWithContent('blob', 'first-'));
        $upload->storeChunk(1, UploadedFile::fake()->createWithContent('blob', 'broken'));
        $this->assertFalse($upload->hasAllChunks(3));

        // A retried chunk replaces the earlier attempt instead of being appended twice.
        $upload->storeChunk(1, UploadedFile::fake()->createWithContent('blob', 'second-'));
        $upload->storeChunk(2, UploadedFile::fake()->createWithContent('blob', 'third'));
        $this->assertTrue($upload->hasAllChunks(3));

        $path = $upload->assemble(3);

        $this->assertSame('first-second-third', file_get_contents($path));
        $this->assertStringStartsWith(ParentChunkedUpload::root(), $path);
        $this->assertStringNotContainsString('public', str_replace($this->storage, '', $path));
    }

    public function test_completion_marker_survives_and_discard_removes_everything(): void
    {
        $upload = new ParentChunkedUpload(7, 'upload-0000000000000002');
        $upload->begin(['goal_id' => 1]);
        $upload->storeChunk(0, UploadedFile::fake()->createWithContent('blob', 'data'));
        $upload->assemble(1);

        $upload->markCompleted(['goal_id' => 1, 'message_id' => 55]);

        $this->assertSame(55, $upload->completion()['message_id']);
        $this->assertSame(0, ParentChunkedUpload::activeUploadCount(7));

        $upload->discard();
        $this->assertDirectoryDoesNotExist($upload->directory());
    }

    public function test_counts_active_uploads_per_user(): void
    {
        (new ParentChunkedUpload(8, 'upload-0000000000000003'))->begin(['goal_id' => 1]);
        (new ParentChunkedUpload(8, 'upload-0000000000000004'))->begin(['goal_id' => 1]);
        (new ParentChunkedUpload(9, 'upload-0000000000000005'))->begin(['goal_id' => 1]);

        $this->assertSame(2, ParentChunkedUpload::activeUploadCount(8));
        $this->assertSame(1, ParentChunkedUpload::activeUploadCount(9));
    }

    public function test_prunes_only_stale_uploads(): void
    {
        $stale = new ParentChunkedUpload(10, 'upload-0000000000000006');
        $stale->begin(['goal_id' => 1]);
        touch($stale->directory().DIRECTORY_SEPARATOR.'meta.json', time() - ParentChunkedUpload::STALE_SECONDS - 60);
        $fresh = new ParentChunkedUpload(10, 'upload-0000000000000007');
        $fresh->begin(['goal_id' => 1]);

        $this->assertSame(1, ParentChunkedUpload::pruneStale());
        $this->assertDirectoryDoesNotExist($stale->directory());
        $this->assertDirectoryExists($fresh->directory());
    }

    public function test_finalize_lock_is_exclusive(): void
    {
        $upload = new ParentChunkedUpload(11, 'upload-0000000000000008');

        $inner = $upload->withFinalizeLock(fn () => (new ParentChunkedUpload(11, 'upload-0000000000000008'))
            ->withFinalizeLock(fn () => 'second'));

        $this->assertNull($inner);
    }

    public function test_reports_finalizing_only_while_the_lock_is_held(): void
    {
        $upload = new ParentChunkedUpload(11, 'upload-0000000000000009');
        $upload->begin(['goal_id' => 1]);
        $probe = new ParentChunkedUpload(11, 'upload-0000000000000009');

        $this->assertFalse($probe->isFinalizing());
        $this->assertTrue($upload->withFinalizeLock(fn () => $probe->isFinalizing()));
        $this->assertFalse($probe->isFinalizing());
    }

    public function test_discards_only_idle_unfinished_uploads_of_that_user(): void
    {
        $idle = new ParentChunkedUpload(12, 'upload-0000000000000010');
        $idle->begin(['goal_id' => 1]);
        touch($idle->directory().DIRECTORY_SEPARATOR.'meta.json', time() - ParentChunkedUpload::IDLE_SECONDS - 60);

        $active = new ParentChunkedUpload(12, 'upload-0000000000000011');
        $active->begin(['goal_id' => 1]);

        $finished = new ParentChunkedUpload(12, 'upload-0000000000000012');
        $finished->begin(['goal_id' => 1]);
        $finished->markCompleted(['goal_id' => 1, 'message_id' => 9]);
        touch($finished->directory().DIRECTORY_SEPARATOR.'meta.json', time() - ParentChunkedUpload::IDLE_SECONDS - 60);

        $otherUser = new ParentChunkedUpload(13, 'upload-0000000000000013');
        $otherUser->begin(['goal_id' => 1]);
        touch($otherUser->directory().DIRECTORY_SEPARATOR.'meta.json', time() - ParentChunkedUpload::IDLE_SECONDS - 60);

        $this->assertSame(1, ParentChunkedUpload::discardIdle(12));
        $this->assertDirectoryDoesNotExist($idle->directory());
        $this->assertDirectoryExists($active->directory());
        $this->assertSame(9, $finished->completion()['message_id']);
        $this->assertDirectoryExists($otherUser->directory());
    }

    public function test_does_not_discard_an_idle_upload_that_is_being_finalized(): void
    {
        $upload = new ParentChunkedUpload(14, 'upload-0000000000000014');
        $upload->begin(['goal_id' => 1]);
        touch($upload->directory().DIRECTORY_SEPARATOR.'meta.json', time() - ParentChunkedUpload::IDLE_SECONDS - 60);

        $removed = $upload->withFinalizeLock(fn () => ParentChunkedUpload::discardIdle(14));

        $this->assertSame(0, $removed);
        $this->assertDirectoryExists($upload->directory());
    }

    public function test_chunk_request_accepts_android_3gp_video(): void
    {
        $validator = $this->chunkValidator($this->chunkInput(['file_name' => 'VID_0001.3gp']), UploadedFile::fake()->create('blob', 4096));

        $this->assertFalse($validator->errors()->any());
    }

    public function test_reassembled_3gp_video_passes_content_validation(): void
    {
        $path = $this->writeTemp("\x00\x00\x00\x18ftyp3gp4\x00\x00\x00\x00isom3gp4".str_repeat("\x00", 4096));
        $file = new UploadedFile($path, 'VID_0001.3gp', null, UPLOAD_ERR_OK, true);

        $validator = ParentGoalMessageRequest::validatorFor('video', $file, null);

        $this->assertFalse($validator->fails(), (string) $validator->errors()->first());
    }

    public function test_chunk_request_accepts_a_correct_first_chunk(): void
    {
        $validator = $this->chunkValidator($this->chunkInput(), UploadedFile::fake()->create('blob', 4096));

        $this->assertFalse($validator->errors()->any());
    }

    public function test_chunk_request_rejects_wrong_chunk_size(): void
    {
        $validator = $this->chunkValidator($this->chunkInput(), UploadedFile::fake()->create('blob', 4000));

        $this->assertTrue($validator->errors()->has('chunk'));
    }

    public function test_chunk_request_rejects_tampered_chunk_count(): void
    {
        $validator = $this->chunkValidator($this->chunkInput(['total_chunks' => 400]), UploadedFile::fake()->create('blob', 4096));

        $this->assertTrue($validator->errors()->has('chunk'));
    }

    public function test_chunk_request_rejects_totals_above_the_limit(): void
    {
        $totalSize = 200 * 1024 * 1024 + 1;
        $validator = $this->chunkValidator(
            $this->chunkInput(['total_size' => $totalSize, 'total_chunks' => ParentChunkedUpload::expectedChunks($totalSize)]),
            UploadedFile::fake()->create('blob', 4096)
        );

        $this->assertSame(__('validation.parent_goal_details.too_large', ['mb' => 200]), $validator->errors()->first('video'));
    }

    public function test_chunk_request_rejects_disallowed_extension(): void
    {
        $validator = $this->chunkValidator($this->chunkInput(['file_name' => 'clip.php']), UploadedFile::fake()->create('blob', 4096));

        $this->assertTrue($validator->errors()->has('video'));
    }

    public function test_reassembled_real_video_passes_content_validation(): void
    {
        $path = $this->writeTemp("\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom".str_repeat("\x00", 4096));
        $file = new UploadedFile($path, 'clip.mp4', null, UPLOAD_ERR_OK, true);

        $validator = ParentGoalMessageRequest::validatorFor('video', $file, 'note');

        $this->assertFalse($validator->fails(), (string) $validator->errors()->first());
    }

    public function test_reassembled_script_named_as_video_is_rejected(): void
    {
        $path = $this->writeTemp("<?php echo 'x';\n".str_repeat('a', 2048));
        $file = new UploadedFile($path, 'clip.mp4', null, UPLOAD_ERR_OK, true);

        $validator = ParentGoalMessageRequest::validatorFor('video', $file, null);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('video'));
    }

    private function writeTemp(string $bytes): string
    {
        $path = $this->storage.DIRECTORY_SEPARATOR.'assembled-'.uniqid().'.bin';
        file_put_contents($path, $bytes);

        return $path;
    }
}
