<?php

namespace App\Models\Hr\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Rows valid from effective_from through effective_to (inclusive, null = open-ended). Rows are never
 * edited: App\Support\Hr\HrEffectiveDating appends a new row and closes the previous one.
 */
trait EffectiveDated
{
    public function initializeEffectiveDated(): void
    {
        $this->mergeCasts([
            'effective_from' => 'date',
            'effective_to' => 'date',
        ]);
    }

    public function scopeEffectiveOn(Builder $query, CarbonInterface|string $date): Builder
    {
        $day = Carbon::parse($date)->toDateString();
        $table = $this->getTable();

        // End of day so the bound also matches dates stored with a midnight time part (SQLite keeps casts as text).
        return $query->where("{$table}.effective_from", '<=', "{$day} 23:59:59")
            ->where(fn (Builder $q) => $q->whereNull("{$table}.effective_to")->orWhere("{$table}.effective_to", '>=', $day));
    }

    public function isEffectiveOn(CarbonInterface|string $date): bool
    {
        $day = Carbon::parse($date)->startOfDay();

        return $this->effective_from->lte($day) && ($this->effective_to === null || $this->effective_to->gte($day));
    }

    public function isScheduled(): bool
    {
        return $this->effective_from->gt(today());
    }
}
