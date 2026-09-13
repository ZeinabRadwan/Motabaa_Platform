<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

class QuestionnaireAnswer extends Model
{
    use HasFactory;

    protected $table = 'questionnaires_answers';
    
    protected $fillable = [
        'task_id',
        'user_id',
        'question_id',
        'answer',
    ];
}
