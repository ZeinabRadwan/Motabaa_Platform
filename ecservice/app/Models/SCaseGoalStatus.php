<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SCaseGoalStatus extends Model
{
    use HasFactory;

    protected $table = 'scases_goals_status';
    
    protected $fillable = [
        'case_id',
        'term_id',
        'category',
        'period',
        'status',
    ];
    
    const STATUS_UNFINISHED = 0;
    const STATUS_FINISHED   = 1;

    public function scase() {
        return $this->belongsTo(SCase::class, 'case_id');
    }
}
