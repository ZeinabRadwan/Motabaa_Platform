<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuestionnaireQuestion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'questionnaires_questions';
    
    protected $fillable = [
        'questionnaire_id',
        'title',
        'title_local',
        'type',
    ];

    public function answers()
    {
        return $this->hasMany(QuestionnaireAnswer::class, 'question_id', 'id');
    }

    public function getNameAttribute()
    {
        $locale = App::getLocale();
        if ($locale === 'en' && $this->title_local != null){
            return $this->title_local;
        }
        return $this->title;
    }
}
