<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;

class QuestionnaireSection extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'questionnaires_sections';

    protected $fillable = [
        'questionnaire_id',
        'title',
        'title_local',
        'sort_order',
    ];

    public function questionnaire()
    {
        return $this->belongsTo(Questionnaire::class, 'questionnaire_id');
    }

    public function questions()
    {
        return $this->hasMany(QuestionnaireQuestion::class, 'section_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function getNameAttribute()
    {
        $locale = App::getLocale();
        if ($locale === 'en' && $this->title_local != null) {
            return $this->title_local;
        }

        return $this->title;
    }
}
