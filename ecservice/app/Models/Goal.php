<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;

class Goal extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'term_id',
        'case_id',
        'title',
        'title_local',
        'description',
        'custom_first_feild',
        'custom_general_goal',
        'assessment_id',
        'ended_session',
        'value',
        'date_from',
        'date_to',
        'category',
        'standard',
        'generalization',
        'last_started_session',
        'started_session',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $casts = [
        'started_session' => 'datetime',
        'last_started_session' => 'datetime',
    ];

    public function case()
    {
        return $this->belongsTo(SCase::class, 'case_id');
    }

    public function getGoalTitleAttribute()
    {
        $locale = App::getLocale();
        if ($locale === 'en' && $this->title_local != null){
            return $this->title_local;
        }
        return $this->title;
    }

    public function term()
    {
        return $this->belongsTo(Term::class, 'term_id');
    }

    public function assesment()
    {
        return $this->belongsTo(Assessment::class, 'assessment_id');
    }

    public function goal_evaluations()
    {
        return $this->hasMany(GoalEvaluation::class);
    }

    public function evaluationMethodValue()
    {
        return $this->belongsTo(EvaluationMethodValue::class, 'value_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function evaluation_steps()
    {
        return $this->hasMany(GoalEvaluationStep::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {

            if($model->value!==null) {
                
                $assesment = Assessment::withTrashed()->find($model->assessment_id);
                $evaluationMethodID = ($assesment)?$assesment->evaluation_method_id:1;

                $value = EvaluationMethodValue::select('*')
                ->where('evaluation_method_id', $evaluationMethodID)
                ->where('value', $model->value)
                ->first();
                if($value) {
                    $model->value_id = $value->id;
                }
                else {
                    em("error GoalEvaluation $model->id: assesment_id:$model->assessment_id,  evaluationMethodID: $evaluationMethodID, value = $model->value");
                }
            }
            else {
                $model->value_id = null;
            }
        });
    }
}
