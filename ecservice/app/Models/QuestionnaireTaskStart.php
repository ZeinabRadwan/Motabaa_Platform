<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionnaireTaskStart extends Model
{
    protected $table = 'questionnaire_task_starts';

    protected $fillable = [
        'task_id',
        'user_id',
        'started_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
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
