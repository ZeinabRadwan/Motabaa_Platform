<?php

namespace App\Http\Controllers\General;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssessmentRequest;
use App\Models\Assessment;
use App\Models\AssessmentEvaluation;
use App\Http\Resources\EvaluationMethodResource;
use App\Http\Resources\AssessmentsResource;
use Illuminate\Support\Facades\DB;
use App\Models\Goal;
use App\Models\Log;
use App\Models\Term;

class AssessmentEvaluationController extends Controller
{
    public function index(Request $request){
        $user = auth()->user();
        $parent_id = $request->get('parent_id');
        $isAdminCases = $user->can('admin_cases');
        $case_id = $request->get('case_id');

        $center = null;
        if($request->center_id && $user->isInCenter($request->center_id)) {
            $center = $request->center_id;
        }
        else if($user->centers) {
            $center = $user->centers[0]->id;
        }

        $assessments = Assessment::with(['evaluation_method', 'assessment_evaluation' => function ($query) use ($case_id) {
            $query->whereHas('assesment', function ($subquery) {
                $subquery->where('assessments.type', Assessment::GOAL_TYPE);
            })->where('case_id', $case_id);
        }])
        ->where(function ($subQuery) use ($center) {
            $subQuery->whereNull('assessments.center_id');
            $subQuery->orWhere('assessments.center_id', $center);
        })
        ->when(
            !$isAdminCases,
            fn ($q) => $q->usersRoles($user->roles, $parent_id)
        )
        ->select('id', 'type', 'parent_id', 'title', 'title_local', 'category', 'evaluation_method_id')
        ->where('parent_id', $request->get('parent_id'))->get();

        Assessment::primeGoalCounts($assessments, $case_id);
        $assessments->map(function ($assessment) use ($case_id) {
            $data = $assessment->getGoalCount($case_id);
            $assessment->total_goals = $data->total;
            $assessment->total_power_goals = $data->total_power;
            $assessment->total_weak_goals = $data->total_weak;
        
            return $assessment;
        });
        return apiResponse(AssessmentsResource::collection($assessments));
    }

    public function put(Request $request){
        foreach ($request->input('goals') as $goal) {
            $assessmentEvaluation = AssessmentEvaluation::updateOrCreate(
                [
                    'assesment_id' => $goal['assesment_id'],
                    'case_id' => $goal['case_id']
                ],
                [
                    'value' => $goal['value'],
                    'ability' => $goal['ability'],
                ]
            );
            Log::add('put_assessment_evaluation', $assessmentEvaluation, $assessmentEvaluation->id, '');
        }
        return success();
    }

    public function list_assessment_evaluation(Request $request){
        
        $user = auth()->user();
        $case_id = $request->get('case_id');
        $term_id = $request->get('term_id');
        $isAdminCases = $user->can('admin_cases');
        $case_id = $request->get('case_id');

        $center = Term::resolveCenterId($user, $request->center_id);
        $denied = Term::abortIfInaccessible($user, $center, $term_id);
        if ($denied) {
            return $denied;
        }

        $assesments = [];
        if($request->assesment_id) {
            $assesments = Assessment::where(function ($subQuery) use ($center) {
                $subQuery->whereNull('center_id');
                $subQuery->orWhere('center_id', $center);
            })
            ->where('root_id', $request->assesment_id)
            ->where('type', 2)
            ->pluck('id')
            ->toArray();
        }

        $assessment_evaluation = AssessmentEvaluation::with('assesment')
        ->where('case_id', $case_id)
        ->where('ability', 'weak')
        ->whereIn('assesment_id', $assesments)
        ->get();

        $goals = [];
        $goalsParents = [];
        $rootIds = [];
        $parentIds = [];
        foreach ($assessment_evaluation as $goal) {
            if (!$goal->assesment) {
                continue;
            }
            $rootIds[] = Assessment::getAssessmentViaSteps($goal->assesment->parents_ids);
            $rawParents = str_replace('#', '', (string) $goal->assesment->parents_ids);
            foreach (explode('_', $rawParents) as $parentId) {
                if ($parentId) {
                    $parentIds[] = $parentId;
                }
            }
        }

        $roots = [];
        $rootIds = array_values(array_unique(array_filter($rootIds)));
        if ($rootIds) {
            $rootRows = Assessment::select('id', 'category')
                ->whereIn('id', $rootIds)
                ->when(
                    !$isAdminCases,
                    fn ($q) => $q->usersRoles($user->roles, null)
                )
                ->get();
            foreach ($rootRows as $root) {
                $roots[$root->id] = $root->category;
            }
        }

        Assessment::primeParents($parentIds);

        foreach($assessment_evaluation as $goal){

            if($goal->assesment){

                $root_id = Assessment::getAssessmentViaSteps($goal->assesment->parents_ids);
                $category = $roots[$root_id] ?? false;

                if($category) {

                    $goals[$goal->id] = [
                        'term_id' => $term_id,
                        'case_id' => $case_id,
                        'title' => $goal->assesment->title,
                        'title_local' => $goal->assesment->title_local,
                        'assessment_id' => $goal->assesment->id,
                        'value' => $goal->value,
                        'category' => $category
                    ];
                    
                    $goalsParents[$goal->id] = $goal->assesment->recursiveParents();
                }
            }
        }
        return apiResponse(['goals'=> $goals, 'goals_parents'=> $goalsParents]);
    }

    public function add_weaks(Request $request){

        $goalsData = $request->get('goals');
        $termIds = [];
        $caseIds = [];
        $assessmentIds = [];
        $now = now();

        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);
        foreach ($goalsData as $goal) {
            $denied = Term::abortIfInaccessible($user, $center, $goal['term_id'] ?? null);
            if ($denied) {
                return $denied;
            }
        }

        foreach ($goalsData as $key => $goal) {
            $termIds[] = $goal['term_id'];
            $caseIds[] = $goal['case_id'];
            $assessmentIds[] = $goal['assessment_id'];
            $goalsData[$key]['created_at'] = $now;
            $goalsData[$key]['updated_at'] = $now;
        }
        Goal::where('term_id', $termIds[0])
            ->where('case_id', $caseIds[0])
            ->whereIn('assessment_id', $assessmentIds)
            ->withTrashed()
            ->update(['deleted_at' => null]);

        Goal::insertOrIgnore($goalsData);
        return success();
    }
}
