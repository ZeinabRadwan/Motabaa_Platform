<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;
use App\Models\System\System;
use App\Models\Concerns\StoresNamedFilePath;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRoles, StoresNamedFilePath;

    const STATUS_CAN_LOGIN = 1;
    const STATUS_CANNT_LOGIN = 0;
    
    const GENDER_MALE = 1;
    const GENDER_FEMALE = 2;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'status',
        'password',
        'phone',
        'birthdate',
        'id_or_residence_number',
        'gender',
        'email_verified_at',
        'address_building',
        'address_street',
        'address_area',
        'search_text',
        'address_city',
        'address_zipcode',
        'address_number',
        'address_unit',
        'qualification',
        'specialization',
        'precise_specialization',
        'current_work',
        'nationality',
        'on_center_sponsorship',
        'job_title',
        'department',
        'work_shift',
        'contract_type',
        'hire_date',
        'contract_end_date',
        'id_expiry_date',
        'annual_leave_entitlement',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'hire_date' => 'date',
        'contract_end_date' => 'date',
        'id_expiry_date' => 'date',
    ];

    public const DEFAULT_ANNUAL_LEAVE_ENTITLEMENT = 21;
    public const HR_ALERT_DAYS = 90;

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {

            $text = getFTS("#$model->name");
            $model->search_text = "#$model->id, #$text, $model->email, $model->id_or_residence_number, $model->phone, $model->job_title";

        });
    }

    public static function genderTypes() {
        return [
            self::GENDER_MALE => __('tr.male'),
            self::GENDER_FEMALE => __('tr.female'),
        ];
    }

    public static function departments() {
        return [
            'general' => __('tr.department.general'),
            'occupational_therapy' => __('tr.department.occupational_therapy'),
            'physical_therapy' => __('tr.department.physical_therapy'),
            'pronouncement' => __('tr.department.pronouncement'),
            'psychiatric_treatment' => __('tr.department.psychiatric_treatment'),
            'social' => __('tr.department.social'),
            'nursing' => __('tr.department.nursing'),
            'administration' => __('tr.department.administration'),
            'education' => __('tr.department.education'),
        ];
    }

    public static function contractTypes() {
        return [
            'permanent' => __('tr.employee_affairs.contract_types.permanent'),
            'temporary' => __('tr.employee_affairs.contract_types.temporary'),
            'part_time' => __('tr.employee_affairs.contract_types.part_time'),
        ];
    }

    public const SHIFT_MORNING = 'morning';
    public const SHIFT_EVENING = 'evening';
    public const SHIFT_BOTH = 'both';

    public static function hasWorkShiftColumn(): bool
    {
        static $cached = null;
        if ($cached === null) {
            $cached = Schema::hasColumn((new static)->getTable(), 'work_shift');
        }

        return $cached;
    }

    public static function workShifts(): array
    {
        return [
            self::SHIFT_MORNING => __('tr.employee_affairs.work_shift_morning'),
            self::SHIFT_EVENING => __('tr.employee_affairs.work_shift_evening'),
            self::SHIFT_BOTH => __('tr.employee_affairs.work_shift_both'),
        ];
    }

    public static function normalizeWorkShift($value): ?string
    {
        if (is_array($value)) {
            $values = array_values(array_unique(array_filter(array_map('strval', $value))));
            $hasMorning = in_array(self::SHIFT_MORNING, $values, true);
            $hasEvening = in_array(self::SHIFT_EVENING, $values, true);
            if ($hasMorning && $hasEvening) {
                return self::SHIFT_BOTH;
            }
            if ($hasMorning) {
                return self::SHIFT_MORNING;
            }
            if ($hasEvening) {
                return self::SHIFT_EVENING;
            }

            return null;
        }

        $value = is_string($value) ? trim($value) : $value;
        if ($value === '' || $value === null) {
            return null;
        }

        return in_array($value, [self::SHIFT_MORNING, self::SHIFT_EVENING, self::SHIFT_BOTH], true)
            ? $value
            : null;
    }

    public function setWorkShiftAttribute($value): void
    {
        $this->attributes['work_shift'] = self::normalizeWorkShift($value);
    }

    public function workShiftValues(): array
    {
        return match ($this->work_shift) {
            self::SHIFT_BOTH => [self::SHIFT_MORNING, self::SHIFT_EVENING],
            self::SHIFT_MORNING, self::SHIFT_EVENING => [$this->work_shift],
            default => [],
        };
    }

    public function workShiftLabel(): ?string
    {
        $labels = array_map(
            fn ($shift) => self::workShifts()[$shift] ?? $shift,
            $this->workShiftValues()
        );

        return count($labels) ? implode(', ', $labels) : null;
    }

    public function scaseParent()
    {
        return $this->belongsToMany(SCase::class, 'scase_user', 'user_id', 'scase_id')
                    ->wherePivot('relationship_type', System::USER_TYPE_PARENT);
    }

    public function centers()
    {
        return $this->belongsToMany(Center::class, 'centers_users', 'user_id', 'center_id');
    }

    public function terms()
    {
        return $this->belongsToMany(Term::class, 'term_user');
    }

    public function isInCenter($centerID)
    {
        if ($this->relationLoaded('centers')) {
            return $this->centers->contains('id', (int) $centerID);
        }

        return $this->centers()->where('centers.id', $centerID)->exists();
    }

    public function leaves()
    {
        return $this->hasMany(EmployeeLeave::class);
    }

    public function fileMeta()
    {
        return $this->hasMany(UserFileMeta::class);
    }

    public function annualLeaveEntitlement(): int
    {
        return (int) ($this->annual_leave_entitlement ?? self::DEFAULT_ANNUAL_LEAVE_ENTITLEMENT);
    }

    public function annualLeaveBalance(?int $year = null, ?int $excludeLeaveId = null): array
    {
        $year = $year ?: (int) now()->year;
        $entitlement = $this->annualLeaveEntitlement();

        $approvedQuery = $this->leaves()
            ->where('type', 'annual')
            ->where('status', 'approved')
            ->whereYear('date_from', $year);

        if ($excludeLeaveId) {
            $approvedQuery->where('id', '!=', $excludeLeaveId);
        }

        $used = (int) $approvedQuery->sum('days');
        $pending = (int) $this->leaves()
            ->where('type', 'annual')
            ->where('status', 'pending')
            ->whereYear('date_from', $year)
            ->when($excludeLeaveId, fn ($q) => $q->where('id', '!=', $excludeLeaveId))
            ->sum('days');

        return [
            'year' => $year,
            'entitlement' => $entitlement,
            'used' => $used,
            'pending' => $pending,
            'remaining' => max(0, $entitlement - $used),
        ];
    }

    public static function staffInCenter($center)
    {
        return static::whereHas('centers', function ($query) use ($center) {
            $query->where('centers.id', $center);
        })->whereHas('roles', function ($query) {
            $query->whereNotIn('default_name', ['parent', 'admin'])
                ->orWhereNull('default_name');
        });
    }

    public static function hrAlertDateRange(): array
    {
        return [now()->toDateString(), now()->addDays(self::HR_ALERT_DAYS)->toDateString()];
    }

    public function storagePath() {

        $path = "";
        if(!usesBunnyStorage())
            $path = "public/";

        return $path."users/{$this->id}";
    }

    public function setImage($file, $currentImage=null) {

        deleteFile($this->storagePath(), 'user_image');
        $fileName = 'user_image.'.$file->getClientOriginalExtension();
        saveFile($this->storagePath(), $fileName, $file, $currentImage);
        $this->persistStoredNamedPath('image_path', $fileName);
    }

    public function urlImage() {
        return resolveStoredNamedFile($this->storagePath(), 'user_image', $this->image_path);
    }

    public function deleteImage() {
        deleteFile($this->storagePath(), 'user_image');
        $this->persistStoredNamedPath('image_path', '');
    }

    protected function storedNamedFileColumns(): array
    {
        return ['image_path'];
    }

    public function sendPasswordResetNotification($token)
    {
        return $this->notify(new \App\Notifications\Auth\ResetPasswordNotification($token));
    }

    public function filesStoragePath() {
        return $this->storagePath()."/attachments";
    }

    public function saveFile($fileName, $file) {
        return saveFile($this->filesStoragePath(), $fileName, $file);
    }

    public function fetchFiles($searchName=null) {
        $files = fetchFiles($this->filesStoragePath(), $searchName);
        if(isset($files['file_name']))
            $files = [$files];
        return $files;
    }

    public function deleteFile($fileName, $extension=null) {
        return deleteFile($this->filesStoragePath(), $fileName, $extension);
    }
    
    public function attendanceStoragePath(){
        return $this->storagePath()."/attendance";
    }

    public function setAttendanceFile($fileName, $file) {
        
        $this->deleteAttendanceFile($fileName);
        $fileName = $fileName .'.'.$file->getClientOriginalExtension();
        return saveFile($this->attendanceStoragePath(), $fileName, $file);
    }

    public function urlAttendanceFile($searchName) {
        return fetchFiles($this->attendanceStoragePath(), $searchName);
    }

    public function deleteAttendanceFile($fileName, $extension=null) {
        return deleteFile($this->attendanceStoragePath(), $fileName, $extension);
    }

    public function genderType()
    {
        return $this->belongsToMany(SCase::class, 'scase_user', 'user_id', 'scase_id')
                    ->wherePivot('relationship_type', System::USER_TYPE_PARENT);
    }
}
