<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use App\Models\Concerns\StoresNamedFilePath;

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

    public function saveFile($file, $type) {

        $fileName = '';
        if($type == 'image') {
            $fileName = self::IMAGE_NAME;
        }
        else if($type == 'file') {
            $fileName = self::FILE_NAME;
        }
        else if($type == 'video') {
            $fileName = self::VIDEO_NAME;
        }

        $fileName = $fileName .'.'.$file->getClientOriginalExtension();
        saveFile($this->storagePath(), $fileName, $file);
        $column = $type === 'image' ? 'image_path' : ($type === 'video' ? 'video_path' : 'file_path');
        $this->persistStoredNamedPath($column, $fileName);
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

    public function skipsAttachmentLookup(): bool
    {
        return $this->isSystemMessage()
            || ($this->image_path === '' && $this->file_path === '' && $this->video_path === '');
    }

    public function urlFile(){
        if ($this->isSystemMessage() || $this->file_path === '') {
            return null;
        }

        $file = resolveStoredNamedFile($this->storagePath(), self::FILE_NAME, $this->file_path);
        $isVideo = false;
        if($file && in_array($file['file_extension'], ['mp4', 'mov', 'ogg', 'webm']))
            $isVideo = true;

        return $file ? ['file'=> $file, 'is_video'=> $isVideo] : null;
    }

    public function urlImage(){
        if ($this->isSystemMessage() || $this->image_path === '') {
            return null;
        }

        return resolveStoredNamedFile($this->storagePath(), self::IMAGE_NAME, $this->image_path);
    }

    public function urlVideo(){
        if ($this->isSystemMessage() || $this->video_path === '') {
            return null;
        }

        return resolveStoredNamedFile($this->storagePath(), self::VIDEO_NAME, $this->video_path);
    }

    public function deleteFolder() {
        return deleteFile($this->storagePath());
    }

    protected function storedNamedFileColumns(): array
    {
        return ['image_path', 'file_path', 'video_path'];
    }
}
