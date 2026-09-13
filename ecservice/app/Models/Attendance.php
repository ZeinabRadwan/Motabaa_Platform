<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\Concerns\StoresNamedFilePath;

class Attendance extends Model
{
    use HasFactory, SoftDeletes, StoresNamedFilePath;

    const ATTEND = 1;
    const ABSENT = 0;

    const FILE_NAME = 'ABSENT_FILE';

    protected $fillable = [
        'case_id',
        'attendance_at',
        'status',
        'created_by'
    ];

    public static function statusLabels()
    {
        return [
            self::ATTEND => __('tr.attendances.attend'),
            self::ABSENT => __('tr.attendances.absent')
        ];
    }

    public function case()
    {
        return $this->belongsTo(SCase::class, 'case_id');
    }

    public function createdBy() {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function storagePath(){
        
        $path = "cases";
        if(!usesBunnyStorage())
            $path = "public/child_case";

        return $path."/{$this->case_id}/attendances/{$this->id}";
    }

    public function saveFile($file) {

        deleteFile($this->storagePath(), self::FILE_NAME);
        $fileName = self::FILE_NAME .'.'.$file->getClientOriginalExtension();
        saveFile($this->storagePath(), $fileName, $file);
        $this->persistStoredNamedPath('file_path', $fileName);
    }

    public function urlFile() {
        return resolveStoredNamedFile($this->storagePath(), self::FILE_NAME, $this->file_path);
    }

    public function deleteFile($extension=null) {
        deleteFile($this->storagePath(), self::FILE_NAME, $extension);
        $this->persistStoredNamedPath('file_path', '');
    }

    protected function storedNamedFileColumns(): array
    {
        return ['file_path'];
    }
}
