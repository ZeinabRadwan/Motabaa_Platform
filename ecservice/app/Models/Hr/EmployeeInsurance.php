<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\Auditable;
use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\Hr\Concerns\EffectiveDated;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Social insurance (GOSI) registration in force from effective_from. Rates are entered per employee rather
 * than hard-coded because GOSI rates change by regulation and by registration date.
 */
class EmployeeInsurance extends Model
{
    use Auditable, BelongsToCenter, EffectiveDated;

    protected $table = 'hr_employee_insurances';

    public const CATEGORY_SAUDI = 'saudi';
    public const CATEGORY_NON_SAUDI = 'non_saudi';
    public const CATEGORY_GCC = 'gcc';
    public const CATEGORY_EXEMPT = 'exempt';

    public const CATEGORIES = [
        self::CATEGORY_SAUDI,
        self::CATEGORY_NON_SAUDI,
        self::CATEGORY_GCC,
        self::CATEGORY_EXEMPT,
    ];

    /** GCC nationals other than Saudi, covered by the GCC insurance extension. */
    public const GCC_NATIONALITIES = ['AE', 'BH', 'KW', 'OM', 'QA'];

    protected $fillable = [
        'center_id',
        'employee_id',
        'effective_from',
        'effective_to',
        'category',
        'employee_rate',
        'employer_rate',
        'contribution_start_date',
        'subscription_number',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'employee_rate' => 'decimal:2',
        'employer_rate' => 'decimal:2',
        'contribution_start_date' => 'date',
    ];

    public static function categoryLabels(): array
    {
        return Employee::labels(self::CATEGORIES, 'tr.hr.insurance_categories');
    }

    /** Whether the category is allowed for a nationality (ISO alpha-2 code). */
    public static function categoryMatchesNationality(string $category, ?string $nationality): bool
    {
        $nationality = strtoupper((string) $nationality);

        return match ($category) {
            self::CATEGORY_SAUDI => $nationality === 'SA',
            self::CATEGORY_GCC => in_array($nationality, self::GCC_NATIONALITIES, true),
            self::CATEGORY_NON_SAUDI => $nationality !== 'SA' && ! in_array($nationality, self::GCC_NATIONALITIES, true),
            default => true,
        };
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id')->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function totalRate(): string
    {
        return number_format((float) $this->employee_rate + (float) $this->employer_rate, 2, '.', '');
    }
}
