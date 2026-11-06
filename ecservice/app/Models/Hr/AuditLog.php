<?php

namespace App\Models\Hr;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only: rows are written by App\Support\Hr\HrAuditLogger and can never be updated or deleted
 * through Eloquent.
 */
class AuditLog extends Model
{
    protected $table = 'hr_audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'center_id',
        'auditable_type',
        'auditable_id',
        'event',
        'old_values',
        'new_values',
        'user_id',
        'via_impersonation',
        'ip_address',
        'user_agent',
        'url',
        'correlation_id',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'via_impersonation' => 'boolean',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function auditable()
    {
        return $this->morphTo();
    }
}
