<?php

namespace Tests\Unit;

use App\Services\MediaAttachmentService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MediaAttachmentServiceTest extends TestCase
{
    public function test_unique_stored_basenames_do_not_collide_for_same_original_name(): void
    {
        $service = new MediaAttachmentService();
        $fileA = UploadedFile::fake()->create('clip.mp4', 100, 'video/mp4');
        $fileB = UploadedFile::fake()->create('clip.mp4', 100, 'video/mp4');

        $nameA = $service->uniqueStoredBasename('message_video', $fileA);
        $nameB = $service->uniqueStoredBasename('message_video', $fileB);

        $this->assertNotSame($nameA, $nameB);
        $this->assertStringStartsWith('message_video_', $nameA);
        $this->assertStringEndsWith('.mp4', $nameA);
    }

    public function test_orphan_cleanup_targets_single_object_not_directory(): void
    {
        $service = new MediaAttachmentService();
        $path = 'public/goals/1/messages/999';
        $stored = $service->uniqueStoredBasename('message_video', UploadedFile::fake()->create('a.mp4', 10, 'video/mp4'));

        if (! is_dir(storage_path('app/'.$path))) {
            mkdir(storage_path('app/'.$path), 0777, true);
        }

        $full = storage_path('app/'.$path.'/'.$stored);
        file_put_contents($full, 'video-bytes');
        $marker = storage_path('app/'.$path.'/keep.txt');
        file_put_contents($marker, 'keep');

        $service->deleteSingleStoredObject($path, $stored);

        $this->assertFileDoesNotExist($full);
        $this->assertFileExists($marker);

        @unlink($marker);
        @rmdir(storage_path('app/'.$path));
    }
}
