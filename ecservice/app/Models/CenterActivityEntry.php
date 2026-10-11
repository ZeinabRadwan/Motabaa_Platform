<?php

namespace App\Models;

use App\Models\Concerns\StoresNamedFilePath;
use App\Services\MediaAttachmentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CenterActivityEntry extends Model
{
    use StoresNamedFilePath;

    protected $fillable = [
        'center_activity_id',
        'user_id',
        'content',
        'parents_can_see',
    ];

    protected $casts = [
        'parents_can_see' => 'boolean',
    ];

    const IMAGE_NAME = 'message_image';

    const FILE_NAME = 'message_file';

    const VIDEO_NAME = 'message_video';

    public function activity(): BelongsTo
    {
        return $this->belongsTo(CenterActivity::class, 'center_activity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function storagePath(): string
    {
        $path = '';
        if (! usesBunnyStorage()) {
            $path = 'public/';
        }

        return $path."center_activities/{$this->center_activity_id}/entries/{$this->id}";
    }

    /**
     * @return array<string, mixed>
     */
    public function saveFile($file, $type, array $auditContext = []): array
    {
        $baseName = match ($type) {
            'image' => self::IMAGE_NAME,
            'file' => self::FILE_NAME,
            'video' => self::VIDEO_NAME,
            default => throw new \InvalidArgumentException('Invalid media type'),
        };

        $column = $type === 'image' ? 'image_path' : ($type === 'video' ? 'video_path' : 'file_path');
        $service = app(MediaAttachmentService::class);

        return $service->upload(
            $this,
            $file,
            $type,
            $baseName,
            fn () => $this->storagePath(),
            fn (string $storedBasename) => $this->persistStoredNamedPath($column, $storedBasename),
            array_merge([
                'context_type' => 'center_activity_entry',
                'context_id' => $this->center_activity_id,
            ], $auditContext),
        );
    }

    public function skipsAttachmentLookup(): bool
    {
        return $this->image_path === ''
            && $this->file_path === ''
            && $this->video_path === '';
    }

    protected function storedPathIsMissing(string $column): bool
    {
        return $this->getAttribute($column) === '';
    }

    public function urlFile()
    {
        if ($this->storedPathIsMissing('file_path')) {
            return null;
        }

        $file = resolveStoredNamedFile($this->storagePath(), self::FILE_NAME, $this->file_path);
        $isVideo = false;
        if ($file && in_array($file['file_extension'], ['mp4', 'mov', 'ogg', 'webm'])) {
            $isVideo = true;
        }

        return $file ? ['file' => $file, 'is_video' => $isVideo] : null;
    }

    public function urlImage()
    {
        if ($this->storedPathIsMissing('image_path')) {
            return null;
        }

        return resolveStoredNamedFile($this->storagePath(), self::IMAGE_NAME, $this->image_path);
    }

    public function urlVideo()
    {
        if ($this->storedPathIsMissing('video_path')) {
            return null;
        }

        return resolveStoredNamedFile($this->storagePath(), self::VIDEO_NAME, $this->video_path);
    }

    public function deleteFolder(array $auditContext = [])
    {
        app(MediaAttachmentService::class)->deleteAttachableStorage(
            $this,
            fn () => $this->storagePath(),
            array_merge([
                'context_type' => 'center_activity_entry',
                'context_id' => $this->center_activity_id,
            ], $auditContext),
        );
    }

    protected function storedNamedFileColumns(): array
    {
        return ['image_path', 'file_path', 'video_path'];
    }
}
