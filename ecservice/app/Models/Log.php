<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    use HasFactory;

    protected static $resolvedModels = [];

    public function createdBy() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function add($type, $modelType, $modelId, $description, $center=0, $createdBy=null) {

        $model = get_class($modelType);

        $user = auth()->user();
        if($center == 0 && $user && $user->centers && isset($user->centers[0]->id)) {
            $center = $user->centers[0]->id;
        }

        $log = new Log();
        $log->center_id = $center ?: null;
        $log->created_by = $createdBy ?: ($user?->id);
        $log->type = $type;
        $log->description = $description;
        $log->url = request()->path();
        $log->model_type = $model;
        $log->model_id = $modelId;
        $log->saveFTS();
    }

    public function saveFTS() {

        if(empty($this->id))
            $this->save();
        
        $model = $this->model();
        
        $searchText = "";
        $searchText .= "#" .getFTS($this->id);
        $searchText .= ", ".getFTS($this->scalarSearch($model['type_name'] ? __("tr.logs.{$model['type_name']}") : ''));
        $searchText .= ", ".getFTS($this->scalarSearch($model['id_name']));
        $searchText .= ", ".getFTS($this->scalarSearch(__("tr.logs.{$this->type}")));
        $searchText .= ", ".getFTS($this->scalarSearch($this->description));

        $this->search_text = $searchText;
        $this->save();
    }

    private function scalarSearch($value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return '';
    }

    public function model() {
        return self::modelType($this->model_type, $this->model_id);
    }

    public static function preloadModelNames($logs): void
    {
        $grouped = [];
        foreach ($logs as $log) {
            if (!$log->model_type || !$log->model_id) {
                continue;
            }
            $grouped[$log->model_type][$log->model_id] = $log->model_id;
        }

        foreach ($grouped as $type => $ids) {
            foreach (self::batchModelNames($type, array_values($ids)) as $id => $data) {
                self::$resolvedModels[$type.'#'.$id] = $data;
            }
        }
    }

    public static function modelType($modelType , $modelID=null) {
        $cacheKey = $modelType.'#'.$modelID;
        if (array_key_exists($cacheKey, self::$resolvedModels)) {
            return self::$resolvedModels[$cacheKey];
        }

        $data = [];
        $data['type_name'] = '';
        $data['id_name'] = '';

        switch ($modelType) {
            case 'App\Models\User':
                $data['type_name'] = 'user';
                if($modelID) {
                    $model = User::withTrashed()->find($modelID);
                    $data['id_name'] = $model ? $model->name : '';
                }
                break;
            case 'App\Models\SCase':
                $data['type_name'] = 'case';
                if($modelID) {
                    $model = SCase::withTrashed()->find($modelID);
                    $data['id_name'] = $model ? $model->name : '';
                }
                break;
            case 'App\Models\EmployeeAttendance':
                $data['type_name'] = 'employee_attendance';
                if($modelID) {
                    $model = EmployeeAttendance::find($modelID);
                    $data['id_name'] = $model ? $model->user->name : '';
                }
                break; 'user';
            case 'App\Models\EmployeeLeave':
                $data['type_name'] = 'employee_leave';
                if($modelID) {
                    $model = EmployeeLeave::find($modelID);
                    $data['id_name'] = $model && $model->user ? $model->user->name : '';
                }
                break;
            case 'App\Models\Attendance':
                $data['type_name'] = 'case_attendance';
                if($modelID) {
                    $model = Attendance::find($modelID);
                    $data['id_name'] = $model ? $model->case->name : '';
                }
                break;
            case 'App\Models\Message':
                $data['type_name'] = 'message';
                if($modelID) {
                    $model = Message::find($modelID);
                    $data['id_name'] = $model ? $model->user->name : '';
                }
                break;
            case 'App\Models\MeetingRoom':
                $data['type_name'] = 'meeting_room';
                if($modelID) {
                    $model = MeetingRoom::find($modelID);
                    $data['id_name'] = $model ? $model->title : '';
                }
                break;
            case 'App\Models\SCasePayment':
                $data['type_name'] = 'case_payment';
                if($modelID) {
                    $model = SCasePayment::find($modelID);
                    $data['id_name'] = $model ? $model->scase->name : '';
                }
                break;
            case 'App\Models\Term':
                $data['type_name'] = 'term';
                if($modelID) {
                    $model = Term::withTrashed()->find($modelID);
                    $data['id_name'] = $model ? $model->title : '';
                }
                break;
            case 'App\Models\QuestionnaireTask':
                $data['type_name'] = 'questionnaire_task';
                if($modelID) {
                    $model = QuestionnaireTask::withTrashed()->find($modelID);
                    $data['id_name'] = $model->term ? 'استبيان '.$model->term->title : '';
                }
                break;
            case 'App\Models\OperationalPlanGoal':
                $data['type_name'] = 'operational_plan_goal';
                if($modelID) {
                    $model = OperationalPlanGoal::find($modelID);
                    $data['id_name'] = $model ? $model->general_goal : '';
                }
                break;
            case 'App\Models\OperationalPlan':
                $data['type_name'] = 'operational_plan';
                if($modelID) {
                    $model = OperationalPlanGoal::find($modelID);
                    $data['id_name'] = '';
                }
                break;
            case 'App\Models\Assessment':
                $data['type_name'] = 'assessment';
                if($modelID) {
                    $model = Assessment::find($modelID);
                    $data['id_name'] = $model ? $model->title : '';
                }
                break;
            case 'App\Models\AssessmentEvaluation':
                $data['type_name'] = 'assessment_evaluation';
                if($modelID) {
                    $model = AssessmentEvaluation::find($modelID);
                    $data['id_name'] = ($model && $model->case) ? $model->case->name : '';
                }
                break;
            case 'App\Models\GoalEvaluation':
                $data['type_name'] = 'goal_evaluation';
                if($modelID) {
                    $model = GoalEvaluation::find($modelID);
                    $data['id_name'] = ($model && $model->goal && $model->goal->case) ? $model->goal->case->name : '';
                }
                break;
            case 'App\Models\Goal':
                $data['type_name'] = 'goal';
                if($modelID) {
                    $model = Goal::find($modelID);
                    $data['id_name'] = $model ? $model->title : '';
                }
                break;
            case 'App\Models\GoalsSteps':
                $data['type_name'] = 'goal_step';
                if($modelID) {
                    $model = GoalsSteps::find($modelID);
                    $data['id_name'] = ($model && $model->goal) ? $model->goal->title : '';
                }
                break;
            case 'App\Models\Disability':
                $data['type_name'] = 'disability';
                if($modelID) {
                    $model = Disability::find($modelID);
                    $data['id_name'] = ($model && $model->goal) ? $model->goal->title : '';
                }
                break;
            case 'App\Models\SCaseFee':
                $data['type_name'] = 'case_fee';
                if($modelID) {
                    $model = SCaseFee::find($modelID);
                    $data['id_name'] = $model ? $model->case->name : '';
                }
                break;
            case 'App\Models\SCaseGoalStatus':
                $data['type_name'] = 'goal_evaluations_status';
                if($modelID) {
                    $model = SCaseGoalStatus::find($modelID);
                    $data['id_name'] = $model ? $model->scase->name : '';
                }
                break;
            case 'App\Models\GoalEvaluationStep':
                $data['type_name'] = 'goal_evaluations_step';
                if($modelID) {
                    $model = GoalEvaluationStep::find($modelID);
                    $data['id_name'] = ($model && $model->goal) ? $model->goal->title : '';
                }
                break;
            case 'App\Models\Center':
                $data['type_name'] = 'center';
                if($modelID) {
                    $model = Center::find($modelID);
                    $data['id_name'] = $model ? $model->title : '';
                }
                break;
        }
        return self::$resolvedModels[$cacheKey] = $data;
    }

    protected static function batchModelNames(string $modelType, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, fn ($id) => $id !== null && $id !== '')));
        $typeName = self::typeNameFor($modelType);
        $names = array_fill_keys($ids, '');

        if (!$ids) {
            return [];
        }

        switch ($modelType) {
            case User::class:
                foreach (User::withTrashed()->whereIn('id', $ids)->get(['id', 'name']) as $row) {
                    $names[$row->id] = $row->name ?: '';
                }
                break;
            case SCase::class:
                foreach (SCase::withTrashed()->whereIn('id', $ids)->get(['id', 'name']) as $row) {
                    $names[$row->id] = $row->name ?: '';
                }
                break;
            case EmployeeAttendance::class:
                foreach (EmployeeAttendance::with('user:id,name')->whereIn('id', $ids)->get() as $row) {
                    $names[$row->id] = $row->user ? $row->user->name : '';
                }
                break;
            case EmployeeLeave::class:
                foreach (EmployeeLeave::with('user:id,name')->whereIn('id', $ids)->get() as $row) {
                    $names[$row->id] = $row->user ? $row->user->name : '';
                }
                break;
            case Attendance::class:
                foreach (Attendance::with('case:id,name')->whereIn('id', $ids)->get() as $row) {
                    $names[$row->id] = $row->case ? $row->case->name : '';
                }
                break;
            case Message::class:
                foreach (Message::with('user:id,name')->whereIn('id', $ids)->get() as $row) {
                    $names[$row->id] = $row->user ? $row->user->name : '';
                }
                break;
            case MeetingRoom::class:
                foreach (MeetingRoom::whereIn('id', $ids)->get(['id', 'title']) as $row) {
                    $names[$row->id] = $row->title ?: '';
                }
                break;
            case SCasePayment::class:
                foreach (SCasePayment::with('scase:id,name')->whereIn('id', $ids)->get() as $row) {
                    $names[$row->id] = $row->scase ? $row->scase->name : '';
                }
                break;
            case Term::class:
                foreach (Term::withTrashed()->whereIn('id', $ids)->get(['id', 'title']) as $row) {
                    $names[$row->id] = $row->title ?: '';
                }
                break;
            case QuestionnaireTask::class:
                foreach (QuestionnaireTask::withTrashed()->with('term:id,title')->whereIn('id', $ids)->get() as $row) {
                    $names[$row->id] = $row->term ? 'استبيان '.$row->term->title : '';
                }
                break;
            case OperationalPlanGoal::class:
                foreach (OperationalPlanGoal::whereIn('id', $ids)->get(['id', 'general_goal']) as $row) {
                    $names[$row->id] = $row->general_goal ?: '';
                }
                break;
            case OperationalPlan::class:
                foreach (OperationalPlanGoal::whereIn('id', $ids)->get(['id']) as $row) {
                    $names[$row->id] = '';
                }
                break;
            case Assessment::class:
                foreach (Assessment::whereIn('id', $ids)->get(['id', 'title']) as $row) {
                    $names[$row->id] = $row->title ?: '';
                }
                break;
            case AssessmentEvaluation::class:
                foreach (AssessmentEvaluation::with('case:id,name')->whereIn('id', $ids)->get() as $row) {
                    $names[$row->id] = $row->case ? $row->case->name : '';
                }
                break;
            case GoalEvaluation::class:
                foreach (GoalEvaluation::with(['goal:id,case_id', 'goal.case:id,name'])->whereIn('id', $ids)->get() as $row) {
                    $names[$row->id] = ($row->goal && $row->goal->case) ? $row->goal->case->name : '';
                }
                break;
            case Goal::class:
                foreach (Goal::whereIn('id', $ids)->get(['id', 'title']) as $row) {
                    $names[$row->id] = $row->title ?: '';
                }
                break;
            case GoalsSteps::class:
                foreach (GoalsSteps::with('goal:id,title')->whereIn('id', $ids)->get() as $row) {
                    $names[$row->id] = $row->goal ? $row->goal->title : '';
                }
                break;
            case Disability::class:
                foreach ($ids as $id) {
                    $names[$id] = '';
                }
                break;
            case SCaseFee::class:
                foreach (SCaseFee::with('case:id,name')->whereIn('id', $ids)->get() as $row) {
                    $names[$row->id] = $row->case ? $row->case->name : '';
                }
                break;
            case SCaseGoalStatus::class:
                foreach (SCaseGoalStatus::with('scase:id,name')->whereIn('id', $ids)->get() as $row) {
                    $names[$row->id] = $row->scase ? $row->scase->name : '';
                }
                break;
            case GoalEvaluationStep::class:
                foreach (GoalEvaluationStep::with('goal:id,title')->whereIn('id', $ids)->get() as $row) {
                    $names[$row->id] = $row->goal ? $row->goal->title : '';
                }
                break;
            case Center::class:
                foreach (Center::whereIn('id', $ids)->get(['id', 'title']) as $row) {
                    $names[$row->id] = $row->title ?: '';
                }
                break;
        }

        $resolved = [];
        foreach ($ids as $id) {
            $resolved[$id] = [
                'type_name' => $typeName,
                'id_name' => $names[$id] ?? '',
            ];
        }

        return $resolved;
    }

    protected static function typeNameFor(string $modelType): string
    {
        return match ($modelType) {
            User::class => 'user',
            SCase::class => 'case',
            EmployeeAttendance::class => 'employee_attendance',
            EmployeeLeave::class => 'employee_leave',
            Attendance::class => 'case_attendance',
            Message::class => 'message',
            MeetingRoom::class => 'meeting_room',
            SCasePayment::class => 'case_payment',
            Term::class => 'term',
            QuestionnaireTask::class => 'questionnaire_task',
            OperationalPlanGoal::class => 'operational_plan_goal',
            OperationalPlan::class => 'operational_plan',
            Assessment::class => 'assessment',
            AssessmentEvaluation::class => 'assessment_evaluation',
            GoalEvaluation::class => 'goal_evaluation',
            Goal::class => 'goal',
            GoalsSteps::class => 'goal_step',
            Disability::class => 'disability',
            SCaseFee::class => 'case_fee',
            SCaseGoalStatus::class => 'goal_evaluations_status',
            GoalEvaluationStep::class => 'goal_evaluations_step',
            Center::class => 'center',
            default => '',
        };
    }
}
