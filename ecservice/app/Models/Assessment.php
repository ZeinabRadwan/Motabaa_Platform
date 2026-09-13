<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use App\Models\System\System;


class Assessment extends Model
{
    use HasFactory, SoftDeletes;

    const ROOT_TYPE = 0;
    const FEILD_TYPE = 1;
    const GOAL_TYPE = 2;

    protected $fillable = [
        'center_id',
        'type',
        'parent_id',
        'title',
        'title_local',
        'category',
        'evaluation_method_id',
    ];

    // protected $with = ['children'];
    protected $appends = ['type_name', 'assessment_title'];

    
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            $model->parents_ids = "";
            $parent = $model;
            $root = $model;
    
            while ($parent = $parent->parent) {
                $model->parents_ids = "#{$parent->id}_{$model->parents_ids}";
                $root = $parent;
            }    
            $model->root_id = ($root->id>0) ? $root->id : 1;
            $model->evaluation_method_id = $root->evaluation_method_id;
            if($root->id>0)
                $model->center_id = $root->center_id;

            $model->category = $root->category;
            $text = getFTS("#$model->title, $model->title_local");
            $model->search_text = "#$model->id, #$text";

        });
    }
    
    public function getAssessmentTitleAttribute()
    {
        $locale = App::getLocale();
        if ($locale === 'en' && $this->title_local != null){
            return $this->title_local;
        }
        return $this->title;
    }

    public function getTypeNameAttribute()
    {
        if($this->type == self::ROOT_TYPE){
            return 'root';
        }
        if($this->type == self::FEILD_TYPE){
            return 'feild';
        }
        if($this->type == self::GOAL_TYPE){
            return 'goal';
        }
    }

    public function evaluation_method()
    {
        return $this->belongsTo(EvaluationMethod::class, 'evaluation_method_id');
    }

    public function assessment_evaluation()
    {
        return $this->hasMany(AssessmentEvaluation::class, 'assesment_id');
    }

    public function parent()
    {
        return $this->belongsTo(Assessment::class, 'parent_id');
    }

    public function recursiveParents()
    {
        $parents = [];
        $parentsIDs = str_replace('#', '', $this->parents_ids);
        $parentsIDs = explode('_', $parentsIDs);
        $ids = [];
        foreach ($parentsIDs as $parentID) {
            if ($parentID) {
                $ids[] = $parentID;
            }
        }

        $store = self::lookupStore();
        $missing = [];
        foreach ($ids as $id) {
            if (!$store->offsetExists($id.'|')) {
                $missing[] = $id;
            }
        }
        if ($missing) {
            foreach (self::whereIn('id', $missing)->get() as $row) {
                $store[$row->id.'|'] = $row;
            }
            foreach ($missing as $id) {
                if (!$store->offsetExists($id.'|')) {
                    $store[$id.'|'] = null;
                }
            }
        }

        foreach ($ids as $id) {
            $parents[] = $store[$id.'|'] ?? null;
        }

        return $parents;
    }

    public function children()
    {
        return $this->hasMany(Assessment::class, 'parent_id');
    }

    public function recursiveChildren()
    {
        return $this->hasMany(Assessment::class, 'parent_id')
            ->with('recursiveChildren');
    }
    
    public function getGoalCount($case_id)
    {
        $primed = self::goalCountStore();
        if ($primed->offsetExists($this->id.':'.$case_id)) {
            return $primed[$this->id.':'.$case_id];
        }

        $type = self::GOAL_TYPE;

        return DB::select("SELECT
            (SELECT COUNT(id) FROM assessments WHERE type = '{$type}' AND parents_ids LIKE '%#{$this->id}\\\\_%' ESCAPE '\\\\') AS total,
            (SELECT COUNT(assessment_evaluations.id) FROM assessments 
                LEFT JOIN assessment_evaluations ON assessment_evaluations.assesment_id = assessments.id and ability = 'power'
                WHERE case_id = {$case_id} AND type = '{$type}' AND parents_ids LIKE '%#{$this->id}\\\\_%' ESCAPE '\\\\') AS total_power,
            (SELECT COUNT(assessment_evaluations.id) FROM assessments
                LEFT JOIN assessment_evaluations ON assessment_evaluations.assesment_id = assessments.id and ability = 'weak'
                WHERE case_id = {$case_id} AND type = '{$type}' AND parents_ids LIKE '%#{$this->id}\\\\_%' ESCAPE '\\\\') AS total_weak
    
        ")[0];

    }

    public static function primeGoalCounts(iterable $assessments, $case_id): void
    {
        $ids = [];
        foreach ($assessments as $assessment) {
            if ($assessment && $assessment->id) {
                $ids[] = (int) $assessment->id;
            }
        }
        $ids = array_values(array_unique($ids));
        if (!$ids) {
            return;
        }

        $type = self::GOAL_TYPE;
        $goals = self::query()
            ->where('type', $type)
            ->where(function ($query) use ($ids) {
                foreach ($ids as $id) {
                    $query->orWhereRaw("parents_ids LIKE ? ESCAPE '\\\\'", ['%#'.$id.'\_%']);
                }
            })
            ->get(['id', 'parents_ids']);

        $evalCounts = [];
        $goalIds = $goals->pluck('id')->all();
        if ($goalIds && $case_id) {
            $evalRows = AssessmentEvaluation::query()
                ->selectRaw('assesment_id, ability, count(*) as count')
                ->where('case_id', $case_id)
                ->whereIn('ability', ['power', 'weak'])
                ->whereIn('assesment_id', $goalIds)
                ->groupBy('assesment_id', 'ability')
                ->get();
            foreach ($evalRows as $row) {
                $evalCounts[$row->assesment_id][$row->ability] = (int) $row->count;
            }
        }

        $store = self::goalCountStore();
        foreach ($ids as $id) {
            $needle = '#'.$id.'_';
            $total = 0;
            $power = 0;
            $weak = 0;
            foreach ($goals as $goal) {
                if (!str_contains((string) $goal->parents_ids, $needle)) {
                    continue;
                }
                $total++;
                $power += $evalCounts[$goal->id]['power'] ?? 0;
                $weak += $evalCounts[$goal->id]['weak'] ?? 0;
            }
            $store[$id.':'.$case_id] = (object) [
                'total' => $total,
                'total_power' => $power,
                'total_weak' => $weak,
            ];
        }
    }

    protected static function goalCountStore(): \ArrayObject
    {
        if (!app()->bound('motabaa.assessment_goal_counts')) {
            app()->instance('motabaa.assessment_goal_counts', new \ArrayObject());
        }

        return app('motabaa.assessment_goal_counts');
    }

    public function hasChildren()
    {
        return $this->children()->count() > 0;
    }

    public function hasChildrenWithTrashed()
    {
        return $this->children()->withTrashed()->count() > 0;
    }

    public function save(array $options = [])
    {        
        $this->parents_ids = "";
        $parent = $this;

        while ($parent = $parent->parent) {
            $this->parents_ids = "#{$parent->id}_{$this->parents_ids}";
        }
        // d("PIDS:".$this->parents_ids);

        $text = getFTS("#$this->title, $this->title_local");
        $this->search_text = "#$this->id, #$text";

        parent::save($options);
    }

    public static function primeLookups(iterable $assessments): void
    {
        $ids = [];
        foreach ($assessments as $assessment) {
            if (!$assessment) {
                continue;
            }

            $ids[] = $assessment->id;
            if ($assessment->parent_id) {
                $ids[] = $assessment->parent_id;
            }

            $rootId = self::getAssessmentViaSteps($assessment->parents_ids, 0);
            $firstId = self::getAssessmentViaSteps($assessment->parents_ids, 1);
            if ($rootId) {
                $ids[] = (int) $rootId;
            }
            if ($firstId) {
                $ids[] = (int) $firstId;
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));
        if (!$ids) {
            return;
        }

        $store = self::lookupStore();
        $rows = self::with('evaluation_method')->whereIn('id', $ids)->get();
        foreach ($rows as $row) {
            $store[$row->id.'|'] = $row;
            $store[$row->id.'|evaluation_method'] = $row;
        }
    }

    protected function lookupAssessment($id, array $with = [])
    {
        if (!$id) {
            return null;
        }

        $store = self::lookupStore();
        $cacheKey = $id . '|' . implode(',', $with);

        if (!$store->offsetExists($cacheKey)) {
            $query = self::query()->where('id', $id);
            if ($with) {
                $query->with($with);
            }
            $store[$cacheKey] = $query->first();
        }

        return $store[$cacheKey];
    }

    public static function lookupStore(): \ArrayObject
    {
        if (!app()->bound('motabaa.assessment_lookup')) {
            app()->instance('motabaa.assessment_lookup', new \ArrayObject());
        }

        return app('motabaa.assessment_lookup');
    }

    public static function primeParents(array $ids): void
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if (!$ids) {
            return;
        }

        $store = self::lookupStore();
        $missing = [];
        foreach ($ids as $id) {
            if (!$store->offsetExists($id.'|')) {
                $missing[] = $id;
            }
        }
        if (!$missing) {
            return;
        }

        foreach (self::whereIn('id', $missing)->get() as $row) {
            $store[$row->id.'|'] = $row;
        }
        foreach ($missing as $id) {
            if (!$store->offsetExists($id.'|')) {
                $store[$id.'|'] = null;
            }
        }
    }

    public function getRoot(){
        return $this->lookupAssessment(self::getAssessmentViaSteps($this->parents_ids));
    }

    public function getFirstFeild(){
        return $this->lookupAssessment(self::getAssessmentViaSteps($this->parents_ids, 1));
    }

    public function getEvaluationMethod(){
        return $this->lookupAssessment(self::getAssessmentViaSteps($this->parents_ids), ['evaluation_method'])?->evaluation_method;
    }

    public function getPowerEvaluationMethod(){
        $methods = $this->lookupAssessment(self::getAssessmentViaSteps($this->parents_ids), ['evaluation_method']);
        $power = null;
        if($methods && isset($methods->evaluation_method)) {
            foreach ($methods->evaluation_method as $method) {
                if(is_array($method) && isset($method['items'])) {
                    $items = json_decode($method['items'], true);
                    foreach ($items as $item) {
                        if($item['ability'] == 'power')
                            $power = $item['value'];
                    }
                }
            }
        }
        return $power;
    }

    public function scopeUsersRoles(Builder $query, $roles, $parent_id)
    {
        $types = [];
        foreach ($roles as $role) {
            switch ($role->default_name) {
                case System::USER_TYPE_TEACHER_ROLE_NAME:
                    $types[] = System::TYPE_EDUCATIONAL;
                    $types[] = System::TYPE_INDEPENDENT;
                    break;
                case System::USER_TYPE_PHYSIOTHERAPIST_ROLE_NAME:
                    $types[] = System::TYPE_PHYSICAL_THERAPY;
                    break;
                case System::USER_TYPE_OCCUPATIONAL_THERAPY_ROLE_NAME:
                    $types[] = System::TYPE_OCCUPATIONAL_THERAPY;
                    break;
                case System::USER_TYPE_PSYCHOTHERAPIST_ROLE_NAME:
                    $types[] = System::TYPE_PSYCHIATRIC_TREATMENT;
                    break;
                case System::USER_TYPE_PRONUNCIATION_SPEECH_ROLE_NAME:
                    $types[] = System::TYPE_PRONOUNCEMENT;
                    break;
            }            
        }
        if (!empty($types) && $parent_id == null) {
            return $query->whereIn('category', $types);
        }
        
        return $query;
    }
    
    public static function getAssessmentViaSteps($string, $steps = 0) {
        if (preg_match_all('/#([^#]+)_/', $string, $matches)) {
            try {
                return $matches[1][$steps];
            } catch (\Throwable $th) {
                return $matches[1][0];
            }
        }
        return null;
    }
    
    public static function exportData($assessment) {

        $children = [];
        $data = [];
        $data[] = $assessment->title;
        if(count($assessment->recursiveChildren)>0) {
            foreach ($assessment->recursiveChildren as $recursiveChildren) {
                if($recursiveChildren->type_name == 'goal') {
                    $children[] = array_merge($data, [$recursiveChildren->title]);
                }
                else {
                    $recursive = self::exportData($recursiveChildren);
                    if(count($recursive)>0) {
                        foreach ($recursive as $recursiveChild) {
                            if(is_string($recursiveChild)) {
                                $children[] = array_merge($data, [$recursiveChild]);
                            }
                            else {
                                $children[] = array_merge($data, $recursiveChild);
                            }
                        }
                    }
                }
            }
            return $children;
        }
        else {
            return [$data];
        }
    }

    public function isUsed() {
        
        if($this->hasChildren()) {
            
            if($this->type == self::ROOT_TYPE) {

                $assessmentEvaluation = AssessmentEvaluation::whereHas('assesment', function ($query) {
                    $query->where('assessments.root_id', $this->id);
                })->exists();
                if($assessmentEvaluation)
                    return true;

                $goals = Goal::whereHas('assesment', function ($query) {
                    $query->where('assessments.root_id', $this->id);
                })->exists();
                if($goals)
                    return true;
            }
            else {

                $assessmentEvaluation = AssessmentEvaluation::whereHas('assesment', function ($query) {
                    $query->where('assessments.parents_ids', 'like', "%#{$this->id}_%");
                })->exists();
                if($assessmentEvaluation)
                    return true;

                $goals = Goal::whereHas('assesment', function ($query) {
                    $query->where('assessments.parents_ids', 'like', "%#{$this->id}_%");
                })->exists();
                if($goals)
                    return true;
            }
        }
        else {

            $assessmentEvaluation = AssessmentEvaluation::where('assesment_id', $this->id)->exists();
            if($assessmentEvaluation)
                return true;

            $goals = Goal::where('assessment_id', $this->id)->exists();
            if($goals)
                return true;
        }

        return false;
    }
}
