<?php

namespace App\Models\Hr\Concerns;

/**
 * HR master data stores both languages explicitly (name_ar / name_en). Arabic is required and is the
 * fallback when no English name was entered.
 */
trait HasLocalizedName
{
    public function getNameAttribute(): string
    {
        if (app()->getLocale() === 'en' && filled($this->name_en)) {
            return (string) $this->name_en;
        }

        return (string) $this->name_ar;
    }
}
