<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

class EvaluationMethodValue extends Model
{
    use HasFactory;
    
    public $timestamps = false;

    protected $table = 'evaluation_methods_values';
}
