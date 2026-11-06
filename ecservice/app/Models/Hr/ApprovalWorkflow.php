<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\Auditable;
use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\Hr\Concerns\HasLocalizedName;
use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflow extends Model
{
    use Auditable, BelongsToCenter, HasLocalizedName;

    protected $table = 'hr_approval_workflows';

    protected $fillable = [
        'center_id',
        'request_type',
        'name_ar',
        'name_en',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function steps()
    {
        return $this->hasMany(ApprovalWorkflowStep::class, 'workflow_id')->orderBy('step_order');
    }
}
