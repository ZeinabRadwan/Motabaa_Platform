<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TermPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'term_id',
        'period_index',
        'evaluation_at',
    ];

    protected $casts = [
        'evaluation_at' => 'date',
        'period_index' => 'integer',
    ];

    public function term()
    {
        return $this->belongsTo(Term::class);
    }
}
