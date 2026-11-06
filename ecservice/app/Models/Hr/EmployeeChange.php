<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only employee timeline. Values are stored as they were at the time of the change (raw value plus
 * a bilingual label snapshot for referenced records), so renaming a branch later never rewrites history.
 */
class EmployeeChange extends Model
{
    use BelongsToCenter;

    protected $table = 'hr_employee_changes';

    public const UPDATED_AT = null;

    public const ACTION_SET = 'set';
    public const ACTION_CANCELLED = 'cancelled';

    public const TYPES = [
        'hired', 'rehired', 'terminated', 'status',
        'branch', 'department', 'work_location', 'manager', 'job_title', 'employee_number', 'contract',
        'attendance_method', 'gps_policy', 'grace_period', 'attendance_policy', 'shift',
        'salary', 'allowances', 'insurance',
    ];

    /** Types whose values expose pay data; hidden from users without hr-compensation access. */
    public const FINANCIAL_TYPES = ['salary', 'allowances', 'insurance'];

    /** Types whose raw value is an enum translated at read time. */
    private const ENUM_LABEL_PREFIXES = [
        'status' => 'tr.hr.employment_statuses',
        'attendance_method' => 'tr.hr.attendance_modes',
        'gps_policy' => 'tr.hr.gps_policies',
        'terminated' => 'tr.hr.termination_reasons',
    ];

    protected $fillable = [
        'center_id',
        'employee_id',
        'change_type',
        'action',
        'old_value',
        'new_value',
        'old_label',
        'new_label',
        'effective_date',
        'reason',
        'source_type',
        'source_id',
        'changed_by',
        'created_at',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public static function typeLabels(): array
    {
        return Employee::labels(self::TYPES, 'tr.hr.change_types');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id')->withTrashed();
    }

    public function changer()
    {
        return $this->belongsTo(User::class, 'changed_by')->withTrashed();
    }

    /** Human-readable old/new value in the current locale. */
    public function display(string $side): ?string
    {
        $label = $this->getAttribute("{$side}_label");
        if ($label !== null && $label !== '') {
            $decoded = json_decode((string) $label, true);
            if (is_array($decoded)) {
                $locale = app()->getLocale();

                return (string) ($decoded[$locale] ?? $decoded['ar'] ?? reset($decoded));
            }

            return (string) $label;
        }

        $value = $this->getAttribute("{$side}_value");
        if ($value === null || $value === '') {
            return null;
        }

        $prefix = self::ENUM_LABEL_PREFIXES[$this->change_type] ?? null;
        if ($prefix !== null) {
            $key = "{$prefix}.{$value}";
            $translated = __($key);

            return $translated === $key ? (string) $value : $translated;
        }

        return (string) $value;
    }
}
