<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflowStep extends Model
{
    protected $table = 'hr_approval_workflow_steps';

    protected $fillable = [
        'workflow_id',
        'step_order',
        'approver_type',
        'approver_value',
    ];

    protected $casts = [
        'step_order' => 'integer',
    ];

    public function workflow()
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }
}
