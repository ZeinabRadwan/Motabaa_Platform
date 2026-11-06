<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\Auditable;
use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\Hr\Concerns\EffectiveDated;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Monthly salary package in force from effective_from. Amounts are stored, never recomputed, so a payroll
 * run for a past month always reads the package that applied then.
 */
class EmployeeSalary extends Model
{
    use Auditable, BelongsToCenter, EffectiveDated;

    protected $table = 'hr_employee_salaries';

    public const AMOUNT_FIELDS = ['basic_salary', 'housing_allowance', 'transportation_allowance', 'other_allowances'];

    public const ALLOWANCE_FIELDS = ['housing_allowance', 'transportation_allowance', 'other_allowances'];

    protected $fillable = [
        'center_id',
        'employee_id',
        'effective_from',
        'effective_to',
        'basic_salary',
        'housing_allowance',
        'transportation_allowance',
        'other_allowances',
        'other_allowances_note',
        'currency',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'housing_allowance' => 'decimal:2',
        'transportation_allowance' => 'decimal:2',
        'other_allowances' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id')->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /** Sum of the package components; a display figure, not a payroll calculation. */
    public function grossSalary(): string
    {
        $total = 0;
        foreach (self::AMOUNT_FIELDS as $field) {
            $total += (int) round(((float) $this->{$field}) * 100);
        }

        return number_format($total / 100, 2, '.', '');
    }
}
