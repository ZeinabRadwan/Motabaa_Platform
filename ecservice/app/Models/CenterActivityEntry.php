<?php

namespace App\Models;

use App\Models\Concerns\StoresNamedFilePath;
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

    public function saveFile($file, $type): void
    {
        $fileName = '';
        if ($type == 'image') {
            $fileName = self::IMAGE_NAME;
        } elseif ($type == 'file') {
            $fileName = self::FILE_NAME;
        } elseif ($type == 'video') {
            $fileName = self::VIDEO_NAME;
        }

        $fileName = $fileName.'.'.$file->getClientOriginalExtension();
        saveFile($this->storagePath(), $fileName, $file);
        $column = $type === 'image' ? 'image_path' : ($type === 'video' ? 'video_path' : 'file_path');
        $this->persistStoredNamedPath($column, $fileName);
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

    public function deleteFolder()
    {
        return deleteFile($this->storagePath());
    }

    protected function storedNamedFileColumns(): array
    {
        return ['image_path', 'file_path', 'video_path'];
    }
}
