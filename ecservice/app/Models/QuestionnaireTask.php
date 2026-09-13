<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class QuestionnaireTask extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'questionnaires_tasks';
    
    protected $fillable = [
        'questionnaire_id',
        'term_id',
        'starts_at',
        'ends_at',
    ];

    public function questionnaire() {
        return $this->belongsTo(Questionnaire::class, 'questionnaire_id');
    }

    public function term() {
        return $this->belongsTo(Term::class, 'term_id');
    }

    public function isOpen() {
        
        $currentDate = Carbon::now();
        $startDate = Carbon::parse($this->starts_at);
        $endDate = Carbon::parse($this->ends_at);
        
        if ($currentDate->between($startDate, $endDate))
            return true;

        return false;
    }
}
