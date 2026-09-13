<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OperationalPlanGoal extends Model
{
    use HasFactory;
    protected $table = 'operational_plans_goals';
    protected $fillable = [
        'operational_plan_id',
        'department',
        'general_goal',
        'activities_and_programs',
        'targeted_by',
        'implemented_by',
        'goals_services',
        'performance_indicator',
        'reference_feed',
    ];

    public static function departments() {
        return [
            "general" => __('tr.department.general'),
            "occupational_therapy" => __('tr.department.occupational_therapy'),
            "physical_therapy" => __('tr.department.physical_therapy'),
            "pronouncement" => __('tr.department.pronouncement'),
            "psychiatric_treatment" => __('tr.department.psychiatric_treatment'),
            "social" => __('tr.department.social'),
            "nursing" => __('tr.department.nursing'),
        ];
    }

    public function actionBy()
    {
        return $this->belongsTo(User::class, 'action_by');
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
