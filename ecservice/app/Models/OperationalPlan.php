<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Eloquent\SoftDeletes;


class OperationalPlan extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'operational_plans';

    public function term() {
        return $this->belongsTo(Term::class, 'term_id');
    }

    public function goals()
    {
        return $this->hasMany(OperationalPlanGoal::class, 'operational_plan_id', 'id');
    }
}
