<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPhoneNumber extends Model
{
    public const METHOD_LEGACY_PRIMARY = 'legacy_primary';

    public const METHOD_ADMIN_MANUAL = 'admin_manual';

    public const METHOD_ACCOUNT_PHONE_CHANGE = 'account_phone_change';

    protected $table = 'user_phone_numbers';

    protected $fillable = [
        'user_id',
        'phone',
        'raw_phone',
        'is_primary',
        'verified_at',
        'verification_method',
        'label',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at');
    }
}
