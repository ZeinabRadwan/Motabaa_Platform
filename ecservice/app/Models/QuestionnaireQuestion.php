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
        'section_id',
        'title',
        'title_local',
        'type',
        'required',
        'sort_order',
        'options',
    ];

    protected $casts = [
        'options' => 'array',
    ];

    /**
     * Custom choice labels for type 2, or null for legacy yes/no questions.
     *
     * @return array<int, string>|null
     */
    public function choiceOptions(): ?array
    {
        if ((int) $this->type !== 2) {
            return null;
        }

        $options = $this->options;
        if (! is_array($options) || count($options) === 0) {
            return null;
        }

        $normalized = [];
        foreach ($options as $option) {
            $label = trim(is_array($option) ? (string) ($option['title'] ?? $option['value'] ?? '') : (string) $option);
            if ($label !== '') {
                $normalized[] = $label;
            }
        }

        return count($normalized) > 0 ? array_values($normalized) : null;
    }

    public function isLegacyYesNoQuestion(): bool
    {
        return (int) $this->type === 2 && $this->choiceOptions() === null;
    }

    public function section()
    {
        return $this->belongsTo(QuestionnaireSection::class, 'section_id');
    }

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
