<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\Auditable;
use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\Hr\Concerns\HasLocalizedName;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Official or company holiday for a date range, for every branch (branch_id null) or one branch.
 */
class Holiday extends Model
{
    use Auditable, BelongsToCenter, HasLocalizedName, SoftDeletes;

    public const TYPE_OFFICIAL = 'official';
    public const TYPE_COMPANY = 'company';

    public const TYPES = [self::TYPE_OFFICIAL, self::TYPE_COMPANY];

    public const MAX_DAYS = 31;

    protected $table = 'hr_holidays';

    protected $fillable = [
        'center_id',
        'branch_id',
        'name_ar',
        'name_en',
        'date_from',
        'date_to',
        'holiday_type',
        'is_paid',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'is_paid' => 'boolean',
    ];

    public static function typeLabels(): array
    {
        return Employee::labels(self::TYPES, 'tr.hr.holiday_types');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id')->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function covers(string $date): bool
    {
        return $this->date_from->toDateString() <= $date && $this->date_to->toDateString() >= $date;
    }

    public function appliesToBranch(?int $branchId): bool
    {
        return $this->branch_id === null || ($branchId !== null && (int) $this->branch_id === $branchId);
    }

    public function daysCount(): int
    {
        return (int) $this->date_from->diffInDays($this->date_to) + 1;
    }
}
