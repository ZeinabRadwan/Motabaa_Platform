<?php

namespace App\Models\Hr\Concerns;

use App\Models\Center;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToCenter
{
    public function center()
    {
        return $this->belongsTo(Center::class, 'center_id');
    }

    public function scopeForCenter(Builder $query, int $centerId): Builder
    {
        return $query->where($this->getTable().'.center_id', $centerId);
    }
}
