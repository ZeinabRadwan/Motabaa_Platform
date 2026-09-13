<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoalEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'goal_id',
        'period',
        'value',
    ];

    public function goal()
    {
        return $this->belongsTo(Goal::class, 'goal_id');
    }

    public function evaluationMethodValue()
    {
        return $this->belongsTo(EvaluationMethodValue::class, 'value_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {

        	$goal = Goal::withTrashed()->find($model->goal_id);

        	if($goal && $model->value!==null) {
                
                $assesment = Assessment::withTrashed()->find($goal->assessment_id);
                $evaluationMethodID = ($assesment)?$assesment->evaluation_method_id:1;

                $value = EvaluationMethodValue::select('*')
                ->where('evaluation_method_id', $evaluationMethodID)
                ->where('value', $model->value)
                ->first();
                if($value) {
                    $model->value_id = $value->id;
                }
                else {
                    em("error GoalEvaluation $model->id: goal_id:$goal->id, assesment_id:$goal->assessment_id,  evaluationMethodID: $evaluationMethodID, value = $model->value");
                }
            }
            else {
                $model->value_id = null;
            }
        });
    }
}
