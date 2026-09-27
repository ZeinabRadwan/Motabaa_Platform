<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Role;

class CenterActivity extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'center_id',
        'term_id',
        'title',
        'created_by',
    ];

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class, 'center_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'term_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CenterActivityEntry::class, 'center_activity_id');
    }

    public function visibleRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'center_activity_role', 'center_activity_id', 'role_id');
    }
}
