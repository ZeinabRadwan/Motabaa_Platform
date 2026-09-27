<?php

namespace App\Http\Controllers\General;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\GoalsSteps;
use App\Http\Requests\GoalStepsRequest;
use App\Http\Resources\GoalStepsResource;
use App\Models\Log;
use App\Models\Goal;
use App\Models\SCase;
use App\Models\Term;

class GoalsStepsController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        if (isParentUser($user)) {
            return apiPaginateResponse(
                new \Illuminate\Pagination\LengthAwarePaginator([], 0, max(resolvePerPage($request), 1)),
                GoalStepsResource::collection(collect())
            );
        }

        $perPage = resolvePerPage($request);

        if ($request->goal_id) {
            $goal = Goal::with('case')->find($request->goal_id);
            if (!$goal || !$goal->case || !SCase::userCanAccessCase($user, $goal->case)) {
                return Term::forbiddenResponse();
            }
            $denied = Term::abortIfInaccessible($user, $goal->case->center_id, $goal->term_id);
            if ($denied) {
                return $denied;
            }
        }

        $goals = GoalsSteps::
        when(
            $request->goal_id,
            fn ($q) => $q->where('goal_id',$request->goal_id)
        )
        ->when(
            $request->status &&  $request->status == 'all',
            fn ($q) => $q->withTrashed()
        )
        ->when(
            $request->status &&  $request->status == 'inactive',
            fn ($q) => $q->onlyTrashed()
        )
        ->orderBy('order', 'ASC');

        $goals = $goals->paginate($perPage);

        return apiPaginateResponse($goals, GoalStepsResource::collection($goals));
    }

    public function put(GoalStepsRequest $request, $goal = null){

        $inputs = $request->validated();
        
        $type = 'add_goal_step';
        if($goal>0)
            $type = 'edit_goal_step';
        
        if($type == 'add_goal_step') {
            $inputs['order'] = $request->total+1;
            $inputs['created_by'] = auth()->user()->id;
        }

        $goalSteps = GoalsSteps::updateOrCreate(['id' => $goal], $inputs);
        Log::add($type, $goalSteps, $goalSteps->id, '');
        return success();
    }

    public function delete($id)
    {
        $record = GoalsSteps::find($id);
        if (!$record) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }
        $record->order = null;
        $record->save();
        $record->delete();

        $steps = GoalsSteps::where('goal_id', $record->goal_id)
        ->orderBy('order', 'ASC')
        ->get();
        
        $order = 1;
        foreach ($steps as $value) {

            $value->order = $order;
            $value->save();

            $order++;
        }

        Log::add('delete_goal_step', $record, $record->id, '');
        return response()->json(['message' => 'Record deleted successfully', 'status' => true]);
    }

    public function restore($id)
    {
        $record = GoalsSteps::withTrashed()->find($id);
        if (!$record) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }

        $steps = GoalsSteps::where('goal_id', $record->goal_id)->count();
        $record->restore();
        $record->order = ($steps+1);
        $record->save();

        Log::add('restore_goal_step', $record, $record->id, '');
        return response()->json(['message' => 'Record restored successfully', 'status' => true]);
    }

    public function reOrder(Request $request, $step){

        $record = GoalsSteps::find($step);
        if (!$record) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }

        $steps = GoalsSteps::where('id', '!=', $step)
        ->where('goal_id', $record->goal_id)
        ->orderBy('order', 'ASC')
        ->get();

        if($request->new_order > (count($steps)+1))
            $request->new_order = (count($steps)+1);

        $order = 1;
        foreach ($steps as $value) {

            if($order == $request->new_order)
                $order++;
            
            $value->order = $order;
            $value->save();

            $order++;
        }
        
        $record->order = $request->new_order;
        $record->save();
        
        Log::add('change_step_order', $record, $record->id, '');
        return response()->json(['message' => 'Updated successfully.', 'status' => true], 200);
    }
}
