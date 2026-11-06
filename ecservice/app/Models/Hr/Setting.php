<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\Auditable;
use App\Models\Hr\Concerns\BelongsToCenter;
use Illuminate\Database\Eloquent\Model;

/**
 * One overridden HR setting for a center. Read and write through App\Support\Hr\HrSettings, which owns
 * JSON encoding and the defaults.
 */
class Setting extends Model
{
    use Auditable, BelongsToCenter;

    protected $table = 'hr_settings';

    protected $fillable = [
        'center_id',
        'key',
        'value',
        'updated_by',
    ];

    public function auditExcludedAttributes(): array
    {
        return ['updated_by'];
    }
}
