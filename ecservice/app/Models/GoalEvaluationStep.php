<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoalEvaluationStep extends Model
{
    use HasFactory;
    protected $table = 'goals_evaluations_steps';

    protected $fillable = [
        'goal_id',
        'service_type',
        'date',
        'time',
        'value',
    ];

    public function goal() {
        return $this->belongsTo(Goal::class, 'goal_id');
    }
}
