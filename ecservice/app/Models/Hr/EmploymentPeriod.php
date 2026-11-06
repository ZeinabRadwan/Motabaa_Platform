<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\BelongsToCenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One continuous employment of an employee (start_date through end_date inclusive, null = ongoing).
 * Re-hiring opens a new period; past periods are never reopened or edited.
 */
class EmploymentPeriod extends Model
{
    use BelongsToCenter;

    protected $table = 'hr_employment_periods';

    protected $fillable = [
        'center_id',
        'employee_id',
        'start_date',
        'end_date',
        'end_reason',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id')->withTrashed();
    }

    public function scopeOverlapping(Builder $query, string $from, string $to): Builder
    {
        return $query->where('start_date', '<=', $to)
            ->where(fn (Builder $q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $from));
    }
}
