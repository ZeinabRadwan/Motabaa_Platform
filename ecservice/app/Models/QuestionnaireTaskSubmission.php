<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionnaireTaskSubmission extends Model
{
    protected $table = 'questionnaire_task_submissions';

    protected $fillable = [
        'task_id',
        'user_id',
        'submitted_at',
        'last_edited_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'last_edited_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireTask::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
