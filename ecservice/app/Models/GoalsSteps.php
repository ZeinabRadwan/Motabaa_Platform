<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class GoalsSteps extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'goal_id',
        'procedural_objectives',
        'attempts',
        'successful_attempts',
        'performance_evaluation',
        'reinforcement',
        'created_by',
        'order',
    ];

    public function goal()
    {
        return $this->belongsTo(Goal::class, 'goal_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
