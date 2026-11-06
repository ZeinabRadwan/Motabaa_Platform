<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use App\Models\Concerns\StoresNamedFilePath;
use App\Models\System\System;
use App\Services\MediaAttachmentService;
use Illuminate\Database\Eloquent\Builder;

class Message extends Model
{
    use HasFactory, StoresNamedFilePath;
    protected $fillable = [
        'goal_id',
        'user_id',
        'meeting_room_id',
        'content',
        'type',
        'log_work',
        'parents_can_see',
        'created_at'
    ];

    const IMAGE_NAME = 'message_image';
    const FILE_NAME = 'message_file';
    const VIDEO_NAME = 'message_video';


    const SYS_TYPE = 'SYS';
    const SYS_STARTED_SESSION = 'started_session';
    const SYS_ENDED_SESSION = 'ended_session';
    const SYS_START_NOW_MEETING = 'start_now_meeting';
    const SYS_START_LATER_MEETING = 'start_later_meeting';

    const SENDER_PARENT = 'parent';
    const SENDER_STAFF = 'staff';

    /** Case profile filters: session activity (and its start/end markers), staff comments, parent contributions. */
    const CONTRIBUTION_SESSION = 'session';
    const CONTRIBUTION_STAFF_COMMENT = 'staff_comment';
    const CONTRIBUTION_PARENT = 'parent';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function goal()
    {
        return $this->belongsTo(Goal::class, 'goal_id');
    }

    public function storagePath(){

        $path = "";
        if(!usesBunnyStorage())
            $path = "public/";

        if($this->goal_id){
            $path .= "goals/{$this->goal_id}/messages/{$this->id}"; 
        }
        else if($this->meeting_room_id){
            $path .= "meeting_rooms/{$this->meeting_room_id}/messages/{$this->id}"; 
        }
        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    public function saveFile($file, $type, array $auditContext = []) {

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
                'context_type' => $this->goal_id ? 'goal_message' : 'meeting_room_message',
                'context_id' => $this->goal_id ?: $this->meeting_room_id,
            ], $auditContext),
        );
    }

    public function isSystemMessage(): bool
    {
        return in_array($this->type, [
            self::SYS_TYPE,
            self::SYS_STARTED_SESSION,
            self::SYS_ENDED_SESSION,
            self::SYS_START_NOW_MEETING,
            self::SYS_START_LATER_MEETING,
        ], true);
    }

    /**
     * Parent when every role of the author is the parent role (same rule as `isParentUser`),
     * staff otherwise; null when the author account no longer exists.
     */
    public function senderType(): ?string
    {
        $roles = $this->user?->roles;
        if ($roles === null) {
            return null;
        }

        $isParent = $roles->isNotEmpty()
            && $roles->every(fn ($role) => $role->default_name === System::USER_TYPE_PARENT_ROLE_NAME);

        return $isParent ? self::SENDER_PARENT : self::SENDER_STAFF;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $users
     */
    public static function constrainToParentOnlyUsers($users): void
    {
        $parentRole = System::USER_TYPE_PARENT_ROLE_NAME;

        $users->whereHas('roles', fn ($roles) => $roles->where('default_name', $parentRole))
            ->whereDoesntHave('roles', fn ($roles) => $roles->where(function ($other) use ($parentRole) {
                $other->whereNull('default_name')->orWhere('default_name', '!=', $parentRole);
            }));
    }

    public function scopeFromParentUsers(Builder $query): void
    {
        $query->whereHas('user', fn ($users) => self::constrainToParentOnlyUsers($users));
    }

    public function scopeFromStaffUsers(Builder $query): void
    {
        $query->whereDoesntHave('user', fn ($users) => self::constrainToParentOnlyUsers($users));
    }

    public function scopeContribution(Builder $query, ?string $kind): void
    {
        if ($kind === self::CONTRIBUTION_SESSION) {
            $query->where(function ($session) {
                $session->where('log_work', 1)
                    ->orWhereIn('type', [self::SYS_STARTED_SESSION, self::SYS_ENDED_SESSION]);
            });
        } elseif ($kind === self::CONTRIBUTION_STAFF_COMMENT) {
            $query->fromStaffUsers()
                ->where(fn ($comment) => $comment->whereNull('log_work')->orWhere('log_work', '!=', 1))
                ->where(fn ($comment) => $comment->whereNull('type')->orWhereNotIn('type', [
                    self::SYS_TYPE,
                    self::SYS_STARTED_SESSION,
                    self::SYS_ENDED_SESSION,
                    self::SYS_START_NOW_MEETING,
                    self::SYS_START_LATER_MEETING,
                ]));
        } elseif ($kind === self::CONTRIBUTION_PARENT) {
            $query->fromParentUsers();
        }
    }

    public function skipsAttachmentLookup(): bool
    {
        if ($this->isSystemMessage()) {
            return true;
        }

        // Empty string = confirmed no attachment (new rows). NULL = legacy/unbackfilled — still resolve.
        return $this->image_path === ''
            && $this->file_path === ''
            && $this->video_path === '';
    }

    protected function storedPathIsMissing(string $column): bool
    {
        return $this->getAttribute($column) === '';
    }

    public function urlFile(){
        if ($this->isSystemMessage() || $this->storedPathIsMissing('file_path')) {
            return null;
        }

        $file = resolveStoredNamedFile($this->storagePath(), self::FILE_NAME, $this->file_path);
        $isVideo = false;
        if($file && in_array($file['file_extension'], ['mp4', 'mov', 'ogg', 'webm']))
            $isVideo = true;

        return $file ? ['file'=> $file, 'is_video'=> $isVideo] : null;
    }

    public function urlImage(){
        if ($this->isSystemMessage() || $this->storedPathIsMissing('image_path')) {
            return null;
        }

        return resolveStoredNamedFile($this->storagePath(), self::IMAGE_NAME, $this->image_path);
    }

    public function urlVideo(){
        if ($this->isSystemMessage() || $this->storedPathIsMissing('video_path')) {
            return null;
        }

        return resolveStoredNamedFile($this->storagePath(), self::VIDEO_NAME, $this->video_path);
    }

    public function deleteFolder(array $auditContext = []) {
        app(MediaAttachmentService::class)->deleteAttachableStorage(
            $this,
            fn () => $this->storagePath(),
            array_merge([
                'context_type' => $this->goal_id ? 'goal_message' : 'meeting_room_message',
                'context_id' => $this->goal_id ?: $this->meeting_room_id,
            ], $auditContext),
        );
    }

    protected function storedNamedFileColumns(): array
    {
        return ['image_path', 'file_path', 'video_path'];
    }
}
