<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\Auditable;
use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\Hr\Concerns\HasLocalizedName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A leave type of one center. System types (annual, sick, unpaid, ...) are created on first use and keep their code;
 * centers edit their rules, deactivate them, or add their own types.
 */
class LeaveType extends Model
{
    use Auditable, BelongsToCenter, HasLocalizedName, SoftDeletes;

    public const CATEGORIES = ['annual', 'sick', 'unpaid', 'maternity', 'marriage', 'bereavement', 'paternity', 'hajj', 'other'];

    /** Entitlement from the leave.annual_days settings (more after five years of service). */
    public const BALANCE_ANNUAL = 'annual';
    /** days_per_year every year. */
    public const BALANCE_FIXED = 'fixed';
    /** No balance: limited only by max_days_per_request. */
    public const BALANCE_NONE = 'none';

    public const BALANCE_MODES = [self::BALANCE_ANNUAL, self::BALANCE_FIXED, self::BALANCE_NONE];

    public const COUNT_WORKING_DAYS = 'working_days';
    public const COUNT_CALENDAR_DAYS = 'calendar_days';

    public const DAY_COUNTINGS = [self::COUNT_WORKING_DAYS, self::COUNT_CALENDAR_DAYS];

    protected $table = 'hr_leave_types';

    protected $fillable = [
        'center_id',
        'code',
        'name_ar',
        'name_en',
        'category',
        'is_system',
        'is_paid',
        'balance_mode',
        'days_per_year',
        'carry_over_max_days',
        'max_days_per_request',
        'day_counting',
        'requires_attachment',
        'requires_hr_approval',
        'allow_self_request',
        'color',
        'sort_order',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_paid' => 'boolean',
        'days_per_year' => 'float',
        'carry_over_max_days' => 'float',
        'max_days_per_request' => 'integer',
        'requires_attachment' => 'boolean',
        'requires_hr_approval' => 'boolean',
        'allow_self_request' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @return array<string, array<string, string>> option group => value => label */
    public static function optionLabels(): array
    {
        return [
            'categories' => Employee::labels(self::CATEGORIES, 'tr.hr.leave_categories'),
            'balance_modes' => Employee::labels(self::BALANCE_MODES, 'tr.hr.leave_balance_modes'),
            'day_countings' => Employee::labels(self::DAY_COUNTINGS, 'tr.hr.leave_day_countings'),
        ];
    }

    public function tracksBalance(): bool
    {
        return $this->balance_mode !== self::BALANCE_NONE;
    }

    public function countsCalendarDays(): bool
    {
        return $this->day_counting === self::COUNT_CALENDAR_DAYS;
    }

    public function requests()
    {
        return $this->hasMany(LeaveRequest::class, 'leave_type_id');
    }
}
