<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use App\Models\System\System;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Concerns\StoresNamedFilePath;


class SCase extends Model
{
    use HasFactory, SoftDeletes, StoresNamedFilePath;

    private $userRoles = null;

    protected $table = 'scases';

    protected $fillable = [
        'center_id',
        'name',
        'beneficiary_number',
        'period',
        'id_or_residence_number',
        'nationality',
        'birthdate',
        'phone',
        'emergency_contact',
        'blood_type',
        'address_city',
        'address_area',
        'address_street',
        'address_building',
        'address_number',
        'address_unit',
        'search_text',
        'address_zipcode',
        'psychological_study',
        'general_questions',
        'case_study',
    ];

    protected $casts = [
        'birthdate' => 'date',
        'psychological_study' => 'json',
        'general_questions' => 'json',
        'case_study' => 'json',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {

            $text = getFTS("#$model->name");
            $model->search_text = "#$model->id, #$text, $model->beneficiary_number, $model->id_or_residence_number, $model->phone";

        });
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'case_id');
    }

    public function disabilities()
    {
        return $this->belongsToMany(Disability::class, 'scase_disability', 'scase_id', 'disability_id');
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'scase_service', 'scase_id', 'service_id');
    }

    public function parents()
    {
        return $this->belongsToMany(User::class, 'scase_user', 'scase_id', 'user_id')
                    ->wherePivot('relationship_type', System::USER_TYPE_PARENT);
    }
    
    public function specialists()
    {
        return $this->belongsToMany(User::class, 'scase_user', 'scase_id', 'user_id')
                    ->wherePivotIn('scase_user.relationship_type', [
                            System::USER_TYPE_PHYSIOTHERAPIST,
                            System::USER_TYPE_OCCUPATIONAL_THERAPY,
                            System::USER_TYPE_SOCIAL,
                            System::USER_TYPE_PRONUNCIATION_SPEECH,
                            System::USER_TYPE_MENTAL,
                            System::USER_TYPE_PSYCHOTHERAPIST
                        ]);
    }

    public function specialists_teachers()
    {
        return $this->belongsToMany(User::class, 'scase_user', 'scase_id', 'user_id')
                    ->wherePivotIn('scase_user.relationship_type', [
                            System::USER_TYPE_PHYSIOTHERAPIST,
                            System::USER_TYPE_OCCUPATIONAL_THERAPY,
                            System::USER_TYPE_SOCIAL,
                            System::USER_TYPE_PRONUNCIATION_SPEECH,
                            System::USER_TYPE_MENTAL,
                            System::USER_TYPE_PSYCHOTHERAPIST,
                            System::USER_TYPE_TEACHER
                        ]);
    }

    public function physiotherapist()
    {
        return $this->belongsToMany(User::class, 'scase_user', 'scase_id', 'user_id')
                    ->wherePivot('relationship_type', System::USER_TYPE_PHYSIOTHERAPIST);
    }

    public function occupational_therapy()
    {
        return $this->belongsToMany(User::class, 'scase_user', 'scase_id', 'user_id')
                    ->wherePivot('relationship_type', System::USER_TYPE_OCCUPATIONAL_THERAPY);
    }

    public function psychotherapist()
    {
        return $this->belongsToMany(User::class, 'scase_user', 'scase_id', 'user_id')
                    ->wherePivot('relationship_type', System::USER_TYPE_PSYCHOTHERAPIST);
    }

    public function pronunciation_speech()
    {
        return $this->belongsToMany(User::class, 'scase_user', 'scase_id', 'user_id')
                    ->wherePivot('relationship_type', System::USER_TYPE_PRONUNCIATION_SPEECH);
    }

    public function teacher()
    {
        return $this->belongsToMany(User::class, 'scase_user', 'scase_id', 'user_id')
                    ->wherePivot('relationship_type', System::USER_TYPE_TEACHER);
    }

    public function payments()
    {
        return $this->belongsToMany(SCasePayment::class, 'scases_payments', 'scase_id', 'id');
    }

    public function usersPivot()
    {
        $types = [];
        foreach ($this->userRoles as $role) {
            // if($role->default_name == 'parent'){
            //     $types[] = System::USER_TYPE_PARENT;
            // } else if($role->default_name == 'teacher'){
            //     $types[] = System::USER_TYPE_TEACHER;
            // } else if($role->default_name == 'physiotherapist_specialist'){
            //     $types[] = System::USER_TYPE_PHYSIOTHERAPIST;
            // } else if($role->default_name == 'occupational_specialist'){
            //     $types[] = System::USER_TYPE_OCCUPATIONAL_THERAPY;
            // } else if($role->default_name == 'psychotherapist_specialist'){
            //     $types[] = System::USER_TYPE_PSYCHOTHERAPIST;
            // } else if($role->default_name == 'social_specialist'){
            //     $types[] = System::USER_TYPE_SOCIAL;
            // } else if($role->default_name == 'pronunciation_speech_specialist'){
            //     $types[] = System::USER_TYPE_PRONUNCIATION_SPEECH;
            // } else if($role->default_name == 'mental_disability_specialist'){
            //     $types[] = System::USER_TYPE_MENTAL;
            // }

            switch ($role->default_name) {
                case 'parent':
                    $types[] = System::USER_TYPE_PARENT;
                    break;
                case 'teacher':
                    $types[] = System::USER_TYPE_TEACHER;
                    break;
                case 'physiotherapist_specialist':
                    $types[] = System::USER_TYPE_PHYSIOTHERAPIST;
                    break;
                case 'occupational_specialist':
                    $types[] = System::USER_TYPE_OCCUPATIONAL_THERAPY;
                    break;
                case 'psychotherapist_specialist':
                    $types[] = System::USER_TYPE_PSYCHOTHERAPIST;
                    break;
                case 'social_specialist':
                    $types[] = System::USER_TYPE_SOCIAL;
                    break;
                case 'pronunciation_speech_specialist':
                    $types[] = System::USER_TYPE_PRONUNCIATION_SPEECH;
                    break;
                case 'mental_disability_specialist':
                    $types[] = System::USER_TYPE_MENTAL;
                    break;
                // Add more cases as needed
            }            
        }
        return $this->belongsToMany(User::class, 'scase_user', 'scase_id', 'user_id')
                    ->wherePivotIn('scase_user.relationship_type', $types );
    }

    public function scopeUsersRoles(Builder $query, $roles, $userId): void
    {
        $this->userRoles = $roles;
        $types = [];
        foreach ($roles as $role) {
            switch ($role->default_name) {
                case 'parent':
                    $types[] = System::USER_TYPE_PARENT;
                    break;
                case 'teacher':
                    $types[] = System::USER_TYPE_TEACHER;
                    break;
                case 'physiotherapist_specialist':
                    $types[] = System::USER_TYPE_PHYSIOTHERAPIST;
                    break;
                case 'occupational_specialist':
                    $types[] = System::USER_TYPE_OCCUPATIONAL_THERAPY;
                    break;
                case 'psychotherapist_specialist':
                    $types[] = System::USER_TYPE_PSYCHOTHERAPIST;
                    break;
                case 'social_specialist':
                    $types[] = System::USER_TYPE_SOCIAL;
                    break;
                case 'pronunciation_speech_specialist':
                    $types[] = System::USER_TYPE_PRONUNCIATION_SPEECH;
                    break;
                case 'mental_disability_specialist':
                    $types[] = System::USER_TYPE_MENTAL;
                    break;
            }
        }

        $query->whereExists(function ($pivot) use ($userId, $types) {
            $pivot->selectRaw('1')
                ->from('scase_user')
                ->join('users', 'users.id', '=', 'scase_user.user_id')
                ->whereColumn('scase_user.scase_id', 'scases.id')
                ->where('scase_user.user_id', $userId)
                ->whereNull('users.deleted_at');
            if ($types === []) {
                $pivot->whereRaw('0 = 1');
            } else {
                $pivot->whereIn('scase_user.relationship_type', $types);
            }
        });
    }

    public function storagePath() {

        $path = "cases";
        if(!usesBunnyStorage())
            $path = "public/child_case";

        return $path."/{$this->id}";
    }

    public function setImage($file, $currentImage=null) {

        deleteFile($this->storagePath(), 'case_image');
        $fileName = 'case_image.'.$file->getClientOriginalExtension();
        saveFile($this->storagePath(), $fileName, $file, $currentImage);
        $this->persistStoredNamedPath('image_path', $fileName);
    }

    public function urlImage() {
        return resolveStoredNamedFile($this->storagePath(), 'case_image', $this->image_path);
    }

    public function deleteImage() {
        deleteFile($this->storagePath(), 'case_image');
        $this->persistStoredNamedPath('image_path', '');
    }

    protected function storedNamedFileColumns(): array
    {
        return ['image_path'];
    }

    public function filesStoragePath() {
        return $this->storagePath()."/attachments";
    }

    public function saveFile($fileName, $file) {
        return saveFile($this->filesStoragePath(), $fileName, $file);
    }

    public function fetchFiles($fileName=null) {
        $files = fetchFiles($this->filesStoragePath(), $fileName);
        if(isset($files['file_name']))
            $files = [$files];
        return $files;
    }

    public function deleteFile($fileName, $extension=null) {
        return deleteFile($this->filesStoragePath(), $fileName, $extension);
    }

    public function terms() {
        $user = auth()->user();
        $center = $this->center_id;

        return Term::query()
            ->where('center_id', $center)
            ->visibleToUser($user, $center)
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get(['id', 'title', 'title_local', 'starts_at', 'ends_at', 'center_id'])
            ->map(fn ($term) => [
                'id' => (int) $term->id,
                'value' => (int) $term->id,
                'title' => $term->name,
            ])
            ->values();
    }

    public function plansTypes() {

        return Goal::select('category')
        ->where('goals.case_id', $this->id)
        ->groupBy('category')
        ->get();
    }
}
