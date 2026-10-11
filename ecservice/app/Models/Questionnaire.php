<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Eloquent\SoftDeletes;

class Questionnaire extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'title',
        'title_local',
        'allow_parent_edit_after_submit',
    ];

    protected $casts = [
        'allow_parent_edit_after_submit' => 'boolean',
    ];

    public function sections()
    {
        return $this->hasMany(QuestionnaireSection::class, 'questionnaire_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function questions()
    {
        return $this->hasMany(QuestionnaireQuestion::class, 'questionnaire_id')
            ->orderBy('sort_order')
            ->orderBy('id');
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
