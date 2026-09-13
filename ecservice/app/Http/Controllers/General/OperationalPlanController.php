<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OperationalPlan;
use App\Models\OperationalPlanGoal;
use App\Models\Center;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\OperationalPlanResource;
use App\Http\Resources\OperationalPlanGoalResource;
use App\Models\System\PDF;
use Carbon\Carbon;
use App\Models\Log;
use App\Models\Term;

class OperationalPlanController extends Controller
{

    public function index(Request $request){

        $perPage = resolvePerPage($request);

        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);

        $plans = OperationalPlan::where('center_id', $center)
        ->when(
            $request->status && $request->status == 'all',
            fn ($q) => $q->withTrashed()
        )
        ->when(
            $request->status && $request->status == 'inactive',
            fn ($q) => $q->onlyTrashed()
        );

        if (!Term::applyVisibleTerm($plans, $user, $center, $request->term)) {
            return Term::forbiddenResponse();
        }

        $plans = $plans->paginate($perPage);
        return apiPaginateResponse($plans, OperationalPlanResource::collection($plans));
    }

    public function show(Request $request, OperationalPlan $plan){
        
        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);

        $denied = Term::abortIfInaccessible($user, $center ?: $plan->center_id, $plan->term_id);
        if ($denied) {
            return $denied;
        }

        if($plan->center_id == $center)
            $plan = new OperationalPlanResource($plan);
        else
            $plan = null;

        return apiResponse($plan);
    }

    public function pdfPlan(Request $request, OperationalPlan $plan){

        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id) ?: $plan->center_id;
        $denied = Term::abortIfInaccessible($user, $center, $plan->term_id);
        if ($denied) {
            return $denied;
        }

        ini_set('max_execution_time', 30000);
        $date = Carbon::now()->toDateString();
        $request->center_id = $plan->center_id;

        $request->fetch = 'fetch_employees';
        $employees = (new \App\Http\Controllers\Admin\UserController)->fetchRolesUsers($request);

        $request->fetch = 'fetch_users_count';
        
        $request->like_roles = ['specialist'];
        $nSpecialists = (new \App\Http\Controllers\Admin\UserController)->fetchRolesUsers($request);

        $request->like_roles = ['teacher'];
        $nTeachers = (new \App\Http\Controllers\Admin\UserController)->fetchRolesUsers($request);

        $request->like_roles = ['specialist'];
        $request->nationality = 'SA';
        $nSASpecialists = (new \App\Http\Controllers\Admin\UserController)->fetchRolesUsers($request);

        $request->like_roles = ['teacher'];
        $request->nationality = 'SA';
        $nSATeachers = (new \App\Http\Controllers\Admin\UserController)->fetchRolesUsers($request);

        $request->nationality = null;
        $request->like_roles = null;
        $request->exclude_roles = ['admin', 'parent'];
        $request->not_like_roles = ['specialist', 'teacher'];
        $nRoles = (new \App\Http\Controllers\Admin\UserController)->fetchRolesUsers($request);
        
        app()->setLocale('ar');
        $departments = OperationalPlanGoal::departments();

        $data = [
            'center' => Center::find($request->center_id),
            'plan' => $plan,
            'information' => json_decode($plan->information, true),
            'departments' => $departments,
            'employees' => json_decode($employees->content(), true)['data'],
            'specialists' => json_decode($nSpecialists->content(), true)['data'],
            'teachers' => json_decode($nTeachers->content(), true)['data'],
            'sa_specialists' => json_decode($nSASpecialists->content(), true)['data'],
            'sa_teachers' => json_decode($nSATeachers->content(), true)['data'],
            'roles' => json_decode($nRoles->content(), true)['data']
        ];
        
        $config = [
            'format' => 'A4-L' // Landscape
        ];
        $token = PDF::savePdf('operational_plan', $data, 'Operational Plan '.$date.'.pdf', $config);

        return success(['url'=> route('file.download', ['token'=> $token])]);
    }

    public function put(Request $request, $plan){

        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);

        $operationalPlan = new OperationalPlan();
        if($plan>0) {
            $operationalPlan = OperationalPlan::find($plan);
            $denied = Term::abortIfInaccessible($user, $center ?: $operationalPlan?->center_id, $operationalPlan?->term_id);
            if ($denied) {
                return $denied;
            }
        }
        else if($request->term_id){

            $denied = Term::abortIfInaccessible($user, $center, $request->term_id);
            if ($denied) {
                return $denied;
            }

            if(!$center)
                return response()->json(['errors' => ['error' => [__("validation.You don't belong to any center")]]], 422);
            
            $termExists = OperationalPlan::where('center_id', $center)->where('term_id', $request->term_id)->exists();
            if($termExists)
                return response()->json(['errors' => ['error' => [__('validation.operational_plan.This term already exists')]]], 422);

            $operationalPlan->center_id = $center;
            $operationalPlan->term_id = $request->term_id;
        }

        $operationalPlan->information = $request->information ? $request->information : null;
        $operationalPlan->save();

        $type = 'add_plan';
        if($plan>0)
            $type = 'edit_plan';

        Log::add($type, $operationalPlan, $operationalPlan->id, '');

        if($plan>0)
            return response()->json(['message' => 'Updated successfully.', 'status' => true], 200);
        else
            return response()->json(['message' => 'Added successfully.', 'status' => true], 200);
    }

    public function delete($id) {

        $record = OperationalPlan::find($id);
        $record->delete();
        Log::add('delete_plan', $record, $record->id, '');
        return response()->json(['message' => 'Deleted successfully.', 'status' => true]);
    }

    public function restore($id) {

        $record = OperationalPlan::withTrashed()->find($id);
        $record->restore();
        Log::add('restore_plan', $record, $record->id, '');
        return response()->json(['message' => 'Restored successfully.', 'status' => true]);
    }
    
    public function goals(Request $request){

        $perPage = resolvePerPage($request);
        $keywords = getFTS($request->q);

        $goals = OperationalPlanGoal::when(
            $keywords,
            fn ($q) => $q->where('general_goal', 'like',"%{$keywords}%")
        )
        ->when(
            $request->operational_plan_id,
            fn ($q) => $q->where('operational_plan_id', $request->operational_plan_id)
        )
        ->when(
            $request->department,
            fn ($q) => $q->where('department', $request->department)
        )
        ->when(
            ($request->status || $request->status === '0') && $request->status != 'all',
            fn ($q) => $q->where('status', $request->status)
        )
        ->paginate($perPage);
        return apiPaginateResponse($goals, OperationalPlanGoalResource::collection($goals));
    }

    public function showGoal(Request $request, OperationalPlanGoal $goal){

        return apiResponse(new OperationalPlanGoalResource($goal));
    }

    public function putGoal(Request $request, $goal){

        $message = "";
        if($goal>0) {

            $type = 'edit_plan_goal';
            $message = 'Updated successfully.';
            $goal = OperationalPlanGoal::find($goal);
        }
        else {

            $type = 'add_plan_goal';
            $message = 'Added successfully.';
            $goal = new OperationalPlanGoal();
        }

        $goal->operational_plan_id = $request->operational_plan_id;
        $goal->department = $request->department;
        $goal->general_goal = $request->general_goal;
        $goal->activities_and_programs = ($request->activities_and_programs!='null') ? $request->activities_and_programs : null;
        $goal->targeted_by = ($request->targeted_by!='null') ? $request->targeted_by : null;
        $goal->implemented_by = ($request->implemented_by!='null') ? $request->implemented_by : null;
        $goal->goals_services = ($request->goals_services!='null') ? $request->goals_services : null;
        $goal->performance_indicator = ($request->performance_indicator!='null') ? $request->performance_indicator : null;
        $goal->reference_feed = ($request->reference_feed!='null') ? $request->reference_feed : null;
        $goal->status = $goal->status ? $goal->status : 0;
        $goal->save();

        Log::add($type, $goal, $goal->id, '');

        return response()->json(['message' => $message, 'status' => true], 200);
    }

    public function actionGoal(Request $request, $goal){

        $goal = OperationalPlanGoal::find($goal);
        if($request->action == 'implemented') {
            $goal->status = 1;
            $goal->implemented_at = $request->implemented_at;
        }
        else if($request->action == 'not_implemented') {
            $goal->status = 0;
            $goal->implemented_at = null;
        }
        $goal->action_by = auth()->user()->id;;
        $goal->action_at = Carbon::now();
        $goal->save();

        Log::add($request->action.'_plan_goal', $goal, $goal->id, '');

        return response()->json(['message' => 'Updated successfully.', 'status' => true], 200);
    }

    public function deleteGoal($id) {

        $record = OperationalPlanGoal::find($id);
        Log::add('delete_plan_goal', $record, $record->id, '');
        $record->delete();
        return response()->json(['message' => 'Deleted successfully.', 'status' => true]);
    }
}
