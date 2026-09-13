<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssessmentEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'assesment_id',
        'case_id',
        'value',
        'ability'
    ];

    public function case()
    {
        return $this->belongsTo(SCase::class, 'case_id');
    }

    public function assesment()
    {
        return $this->belongsTo(Assessment::class, 'assesment_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {

            if($model->value!==null) {
                
                $assesment = Assessment::withTrashed()->find($model->assesment_id);

                if($assesment) {
                    $value = EvaluationMethodValue::select('*')
                    ->where('evaluation_method_id', $assesment->evaluation_method_id)
                    ->where('value', $model->value)
                    ->first();
                    if($value) {
                        $model->value_id = $value->id;
                    }
                    else {
                        em("error AssessmentEvaluation $model->id: value = $model->value");
                    }
                }
                else {
                    em("error AssessmentEvaluation $model->id: assesment_id = $model->assesment_id");
                }
            }
            else {
                $model->value_id = null;
            }
        });
    }
}
