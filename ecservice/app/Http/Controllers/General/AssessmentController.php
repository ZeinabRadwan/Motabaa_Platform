<?php

namespace App\Http\Controllers\General;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssessmentRequest;
use App\Models\Assessment;
use App\Models\EvaluationMethod;
use App\Models\Center;
use App\Http\Resources\EvaluationMethodResource;
use App\Http\Resources\AssessmentsResource;
use App\Models\System\System;
use Carbon\Carbon;
use App\Models\Log;
use App\Support\ReferenceCache;

use App\Models\System\ExportExcel;
use App\Exports\ExportAssessmentsSheets;

class AssessmentController extends Controller
{

    public function index(Request $request) {

        $user = auth()->user();
        $isAdminCases = $user->can('admin_scales');
        $keywords = mb_ereg_replace(" ", "%", getFTS($request->q));

        $center = null;
        if($request->center_id && $user->isInCenter($request->center_id)) {
            $center = $request->center_id;
        }
        else if($user->centers) {
            $center = $user->centers[0]->id;
        }

        $assessments = Assessment::select('id', 'center_id', 'type', 'parent_id', 'title', 'title_local', 'category', 'evaluation_method_id', 'deleted_at')
        ->where(function ($subQuery) use ($center) {
            $subQuery->whereNull('assessments.center_id');
            $subQuery->orWhere('assessments.center_id', $center);
        })
        ->when(
            !$request->load_goals,
            fn ($q) => $q->where('parent_id', $request->get('parent_id'))
        )
        ->when(
            $request->load_goals && $request->load_goals == 'load',
            fn ($q) => $q->where('type', Assessment::GOAL_TYPE)
                    ->where('parents_ids', 'like', "%#{$request->parent_id}_%")
                    ->limit($request->goals_limit ?? 100)
        )
        ->when(
            $request->q,
            fn ($q) => $q->where('title', 'like',"%{$keywords}%")
        )
        ->when(
            $request->category,
            fn ($q) => $q->where('category',$request->category)
        )
        ->when(
            !$isAdminCases,
            fn ($q) => $q->usersRoles($user->roles, $request->get('parent_id'))
        )
        ->when(
            $request->status && $request->status == 'all',
            fn ($q) => $q->withTrashed()
        );
        
        if($request->export == 'export_assessments') {

            $assessments = $assessments->whereNull('parent_id')->get();
            $assessments = AssessmentsResource::collection($assessments);
            $data = [];
            $childrenData = [];
            foreach ($assessments as $assessment) {
                $data[$assessment->title] = [];
                foreach ($assessment->recursiveChildren as $recursiveChild) {
                    $data[$assessment->title] = array_merge($data[$assessment->title], Assessment::exportData($recursiveChild));
                }
            }
            
            $fileName = 'Assessments '.Carbon::now().'.xlsx';
            $token = ExportExcel::saveExcel(new ExportAssessmentsSheets($data), $fileName);
            return success(['url'=> route('file.download', ['token'=> $token])]);
        }
        else {
            
            $assessments = $assessments->get();
            $assessments = AssessmentsResource::collection($assessments);
            return apiResponse($assessments);
        }
    }

    public function put(AssessmentRequest $request, $assessment = null) {

        $input = $request->validated();
        $center = Center::find($request->center_id);
        if($center) {
            $nAssessments = Assessment::where('center_id', $center->id)->count();
            if($center->package->identifier == 'trial' && $nAssessments >= 1)
                return response()->json(['errors' => ['error' => [__("validation.Your are using trial package and can not create more than one assessment")]]], 422);
        }

        $type = 'add_assessment';
        $input['center_id'] = null;
        if($assessment!=null && $assessment!='null') {
            $type = 'edit_assessment';
        }
        
        $user = auth()->user();
        if($request->center_id && $user->isInCenter($request->center_id)) {
            $input['center_id'] = $request->center_id;
        }

        $assessment = Assessment::updateOrCreate(['id' => $assessment], $input);
        if($assessment && $assessment->type == Assessment::ROOT_TYPE) {
            Assessment::where('id', '!=', $assessment->id)
            ->where('root_id', $assessment->id)
            ->update([
                'center_id'=> $input['center_id'],
                'category'=> $input['category'],
                'evaluation_method_id'=> $input['evaluation_method_id'],
            ]);
        }

        Log::add($type, $assessment, $assessment->id, '');
        return apiResponse($assessment);
    }

    public function evaluationMethods(Request $request) {

        $user = auth()->user();
        $center = null;
        if($request->center_id && $user->isInCenter($request->center_id)) {
            $center = $request->center_id;
        }
        else if($user->centers){ 
            $center = $user->centers[0]->id;
        }

        $payload = ReferenceCache::remember('evaluation-methods', 'c'.($center ?: 'none').':'.app()->getLocale(), function () use ($center) {
            $types = EvaluationMethod::where(function ($subQuery) use ($center) {
                $subQuery->whereNull('center_id');
                $subQuery->orWhere('center_id', $center);
            })
            ->get();

            return ReferenceCache::payload(EvaluationMethodResource::collection($types));
        });

        return apiResponse($payload);
    }

    public function delete(Assessment $assessment) {
        
        $user = auth()->user();
        if ($assessment) {
            if($assessment->isUsed()) {
                if($assessment->hasChildren()) {
                    if($assessment->type == Assessment::ROOT_TYPE) {
                        Assessment::where('id', '!=', $assessment->id)
                        ->where('root_id', $assessment->id)
                        ->delete();
                    }
                    else {
                        Assessment::where('id', '!=', $assessment->id)
                        ->where('parents_ids', 'like', "%#{$assessment->id}_%")
                        ->delete();
                    }
                }
                Log::add('soft_delete_assessment', $assessment, $assessment->id, '');
                $assessment->delete();
                return success();
            }
            else if(isHasRole('admin', $user) || (isHasRole('manager', $user) && $assessment->center_id>0 && $user->isInCenter($assessment->center_id))) {
                if($assessment->hasChildren()) {
                    if($assessment->type == Assessment::ROOT_TYPE) {
                        Assessment::where('id', '!=', $assessment->id)
                        ->where('root_id', $assessment->id)
                        ->forceDelete();
                    }
                    else {
                        Assessment::where('id', '!=', $assessment->id)
                        ->where('parents_ids', 'like', "%#{$assessment->id}_%")
                        ->forceDelete();
                    }
                }
                Log::add('hard_delete_assessment', $assessment, $assessment->id, '');
                $assessment->forceDelete();
                return success();
            }
        }
        return error(406);
    }

    public function restore($assessment) {

        $user = auth()->user();
        $assessment = Assessment::withTrashed()->find($assessment);
        if (!$assessment) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }
        if(isHasRole('admin', $user)) {
            if($assessment->hasChildrenWithTrashed()) {
                if($assessment->type == Assessment::ROOT_TYPE) {
                    Assessment::withTrashed()->where('id', '!=', $assessment->id)
                    ->where('root_id', $assessment->id)
                    ->restore();
                }
                else {
                    Assessment::withTrashed()->where('id', '!=', $assessment->id)
                    ->where('parents_ids', 'like', "%#{$assessment->id}_%")
                    ->restore();
                }
            }
            $assessment->restore();
            return success();
        }
        return error(406);
    }

    public function import(Request $request) {

        if($request->import == 'import_assessments') {
            return $this->importAssessments($request);
        }
    }

    public function importAssessments(Request $request) {
        
        if($request->action == 'preview') {
            $data = [];
            $openedRoots = [];
            $openedFields = [];
            $sheets = [];
            $records = [];
            $errors = [];
            $nErrors = 0;
            $assessments = [];
            $rootExists = false;
            $file_mimes = array('text/x-comma-separated-values', 'text/comma-separated-values', 'application/octet-stream', 'application/vnd.ms-excel', 'application/excel', 'application/vnd.msexcel', 'text/plain', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            if(isset($_FILES['file']['name']) && in_array($_FILES['file']['type'], $file_mimes)) {

                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                $excel = $reader->load($_FILES['file']['tmp_name']);
                $sheetCount = $excel->getSheetCount();
                
                $nEmpty = 0;
                for ($i = 0; $i < $sheetCount; $i++) {		
                    
                    $sheet = $excel->getSheet($i);

                    $identifier = 1;
                
                    $root = Assessment::where('title', $sheet->getTitle())
                    ->whereNull('parent_id')
                    ->where('type', 0)
                    ->first();

                    if($root) {
                        $rootExists = true;
                        continue;
                    }

                    $root = new Assessment();
                    $root->identifier = $identifier;
                    $root->title = $sheet->getTitle();
                    $root->title_local = $sheet->getTitle();
                    $root->parent_id = null;
                    $root->type = 0;
                    $assessmentRootKey = getFTS($root->title);
                    $root->key = $assessmentRootKey;
                    $assessments[$assessmentRootKey] = $root;
                    $records[$root->title] = $root;

                    $openedRoots[] = $root->title;
                    
                    $rows = $sheet->toArray(null, true, true, true);
                    $goalKey = null;
                    $sheets[$root->title] = $rows;

                    foreach ($rows as $values) {

                        $errors = [];
            
                        if(empty($values)) continue;
                        $keys = array_keys($values);
                        
                        if(!$goalKey) {
            
                            $goalKeyIndex = count($keys) - 1;
                            while($goalKeyIndex>=0) {
                            
                                $goalKey = $keys[$goalKeyIndex];
                                if(!empty($values[$goalKey])) break;
                                $goalKeyIndex--;		    		
                            }
            
                            if($goalKeyIndex<0) {
                                $errors[] = __("tr.Error").": ".__("tr.Errors.Error in header")." ({$sheet->getTitle()})";
                                $nErrors++;
                            }
            
                            continue;
                        }	
            
                        $parent = $root;

                        foreach ($values as $key => $value) {
            
                            if(empty($value)) continue;
                            if(empty(str_replace(" ", "", $value))) continue;

                            $assessmentKey = getFTS($value);
                            $assessment = new Assessment();

                            if(array_key_exists($assessmentKey, $assessments)) {

                                $assessment = $assessments[$assessmentKey];
                            }
                            else {

                                $identifier++;
                                $assessment->identifier = $identifier;
                                $assessment->title = $value;
                                $assessment->title_local = $value;
                                $assessment->parent_id = $parent->id;
                                $assessment->parent_name = $parent->title;
                                $assessment->type = ($key!=$goalKey)?1:2;
                                $assessmentParentKey = getFTS($assessment->parent_name);
                                $assessment->key = $assessments[$assessmentParentKey]->key."_{$assessmentKey}";
                                $assessments[$assessmentKey] = $assessment;
                                
                                if($assessment->type == 1 && $assessment->parent_name == $root->title)
                                    $openedFields[] = $assessmentKey;
                            }

                            $keys = explode('_', $assessment->key);
                            $record = $records[$root->title];
                            foreach ($keys  as $keyValue) {

                                if(getFTS($root->title) == $keyValue) continue;

                                if(isset($record[$keyValue])) {
                                    $record = $record[$keyValue];
                                }
                                else {
                                    $record[$keyValue] = $assessment;
                                }
                            }

                            if($key==$goalKey) break;
                            $parent = $assessment;
                        }
                    }
                }
            }

            $data['sheets'] = $sheets;
            $data['open'] = $openedRoots;
            $data['records'] = $records;
            $data['errors'] = $errors;
            $data['n_errors'] = $nErrors;
            $data['root_exists'] = $rootExists;
            $data['sheets_count'] = count($sheets);

            if(count($sheets)==1)
                $data['open'] = array_merge($openedRoots, $openedFields);

            return apiResponse($data);
        }
        else {

            $root = null;
            foreach ($request->data as $sheetTitle => $sheetRecords) {
                
                $root = Assessment::where('title', $sheetTitle)
                ->whereNull('parent_id')
                ->where('type', 0)
                ->first();

                if(!$root) {
                    
                    $root = new Assessment();
                    $root->center_id = $request->center_id;
                    $root->title = $sheetTitle;
                    $root->title_local = $sheetTitle;
                    $root->parent_id = null;
                    $root->type = 0;
                    $root->save();
                }
                
                $goalKey = null;

                foreach ($sheetRecords as $values) {

                    if(empty($values)) continue;
                    $keys = array_keys($values);
                    
                    if(!$goalKey) {

                        $goalKeyIndex = count($keys) - 1;
                        while($goalKeyIndex>=0) {
                        
                            $goalKey = $keys[$goalKeyIndex];
                            if(!empty($values[$goalKey])) break;
                            $goalKeyIndex--;		    		
                        }

                        continue;
                    }

                    $parent = $root;

                    foreach ($values as $key => $value) {

                        if(empty($value)) continue;

                        $assessment = Assessment::where('title', $value)
                        ->when(
                            $parent,
                            fn ($q) => $q->where('parent_id', $parent->id)
                        )->first();

                        if(!$assessment) {

                            $assessment = new Assessment();
                        }

                        $assessment->center_id = $parent->center_id;
                        $assessment->title = $value;
                        $assessment->title_local = $value;
                        $assessment->parent_id = $parent->id;
                        $assessment->type = ($key!=$goalKey)?1:2;
                        $assessment->save();

                        if($key==$goalKey) break;
                        $parent = $assessment;
                    }
                }
            }

            Log::add('import_assessment', $root, $root->id, '');
            return response()->json(['message' => 'saved_successfully', 'status' => true], 200);
        }
    }
}
