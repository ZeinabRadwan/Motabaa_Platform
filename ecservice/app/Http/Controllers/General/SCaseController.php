<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SCase;
use App\Models\SCaseFee;
use App\Models\ScaseFeeService;
use App\Models\SCasePayment;
use App\Models\Disability;
use App\Models\Service;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Center;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\Case\SCaseResource;
use App\Http\Resources\Case\SCasesResource;
use App\Http\Resources\Case\SCaseFileResource;
use App\Http\Resources\Case\SCaseFeeResource;
use App\Http\Resources\Case\SCasePaymentResource;
use App\Http\Requests\SCaseRequest;
use App\Models\System\System;
use App\Models\System\PDF;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use App\Models\Log;
use App\Models\Term;

use App\Models\System\ExportExcel;
use App\Exports\ExportCases;

class SCaseController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $isAdminCases =$user->can('admin_cases');

        $perPage = resolvePerPage($request);
        $disability = $request->disability;
        $service = $request->service;
        $teacher_id = $request->teacher_id;
        $specialist_id = $request->specialist_id;
        $specialist_teacher_id = $request->specialist_teacher_id;
        $keywords = mb_ereg_replace(" ", "%", getFTS($request->q));
        
        $center = null;
        if($request->center_id && $user->isInCenter($request->center_id)) {
            $center = $request->center_id;
        }
        else if($user->centers){ 
            $center = $user->centers[0]->id;
        }

        $cases = SCase::query()
        ->with([
            'disabilities:id,name_ar,name_en',
            'services:id,name_ar,name_en',
            'parents:id,name',
        ])
        ->where('scases.center_id', $center)
        ->when(
            $request->q,
            fn ($q) => $q->where('search_text', 'like',"%{$keywords}%")
        )
        ->when(
            $request->insurance == '0' ,
            fn ($q) => $q->whereNull('beneficiary_number')
        )
        ->when(
            $request->insurance ==  '1' ,
            fn ($q) => $q->whereNotNull('beneficiary_number')
        )
        ->when(
            $disability,
            fn ($q) => $q->whereHas('disabilities', function ($query) use ($disability) {
                $query->whereIn('disabilities.id', $disability);
            })
        )
        ->when(
            $service,
            fn ($q) => $q->whereHas('services', function ($query) use ($service) {
                $query->whereIn('services.id', $service);
            })
        )
        ->when(
            $teacher_id,
            fn ($q) => $q->whereHas('teacher', function ($query) use ($teacher_id) {
                $query->where('users.id', $teacher_id);
            })
        )
        ->when(
            $specialist_id,
            fn ($q) => $q->whereHas('specialists', function ($query) use ($specialist_id) {
                $query->where('users.id', $specialist_id);
            })
        )
        ->when(
            $specialist_teacher_id,
            fn ($q) => $q->whereHas('specialists_teachers', function ($query) use ($specialist_teacher_id) {
                $query->where('users.id', $specialist_teacher_id);
            })
        )
        ->when(
            !$isAdminCases,
            fn ($q) => $q->usersRoles($user->roles, $user->id)
        )
        ->when(
            $request->task,
            fn ($q) => $q->where($request->task,'1')
        )
        ->when(
            $request->status &&  $request->status == 'all',
            fn ($q) => $q->withTrashed()
        )
        ->when(
            $request->status &&  $request->status == 'inactive',
            fn ($q) => $q->onlyTrashed()
        );
        
        if($request->export == 'export_cases') {
            
            $cases = $cases->get();
            $fileName = 'Cases '.Carbon::now().'.xlsx';
            $token = ExportExcel::saveExcel(new ExportCases($cases), $fileName);
            return success(['url'=> route('file.download', ['token'=> $token])]);
        }
        else {
            $cases->select([
                'scases.id',
                'scases.name',
                'scases.beneficiary_number',
                'scases.image_path',
                'scases.deleted_at',
            ]);

            $cases = $cases->paginate($perPage);
            return apiPaginateResponse($cases, SCasesResource::collection($cases));
        }
    }

    public function selectItems(Request $request)
    {
        $autocomplete = parseAutocompleteRequest($request);
        $preload = $request->boolean('preload');
        if ($preload) {
            $autocomplete['run'] = true;
        }
        if (!$autocomplete['run']) {
            return apiResponse([]);
        }

        $user = auth()->user();
        $isAdminCases = $user->can('admin_cases');
        $limit = $autocomplete['limit'];
        if ($preload) {
            $limit = min(max((int) $request->get('limit', 500), 1), 500);
        }
        $disability = $request->disability;
        $service = $request->service;
        $teacher_id = $request->teacher_id;
        $specialist_id = $request->specialist_id;
        $specialist_teacher_id = $request->specialist_teacher_id;
        $keywords = $autocomplete['keywords'];

        // Resolved the same way as the lists this dropdown filters, so a user
        // without their own center (platform admin) still gets the right scope.
        $center = Term::resolveCenterId($user, $request->center_id);

        $cases = SCase::select('scases.id', 'scases.name')
            ->where('scases.center_id', $center)
            ->when(
                $keywords && $autocomplete['ids'],
                fn ($q) => $q->where(function ($inner) use ($keywords, $autocomplete) {
                    $inner->where('search_text', 'like', "%{$keywords}%")
                        ->orWhereIn('scases.id', $autocomplete['ids']);
                })
            )
            ->when(
                $keywords && !$autocomplete['ids'],
                fn ($q) => $q->where('search_text', 'like', "%{$keywords}%")
            )
            ->when(
                !$keywords && $autocomplete['ids'],
                fn ($q) => $q->whereIn('scases.id', $autocomplete['ids'])
            )
            ->when(
                $teacher_id,
                fn ($q) => $q->whereHas('teacher', function ($query) use ($teacher_id) {
                    $query->where('users.id', $teacher_id);
                })
            )
            ->when(
                $specialist_id,
                fn ($q) => $q->whereHas('specialists', function ($query) use ($specialist_id) {
                    $query->where('users.id', $specialist_id);
                })
            )
            ->when(
                $specialist_teacher_id,
                fn ($q) => $q->whereHas('specialists_teachers', function ($query) use ($specialist_teacher_id) {
                    $query->where('users.id', $specialist_teacher_id);
                })
            )
            ->when(
                $disability,
                fn ($q) => $q->whereHas('disabilities', function ($query) use ($disability) {
                    $query->whereIn('disabilities.id', $disability);
                })
            )
            ->when(
                $service,
                fn ($q) => $q->whereHas('services', function ($query) use ($service) {
                    $query->whereIn('services.id', $service);
                })
            )
            ->when(
                !$isAdminCases,
                fn ($q) => $q->usersRoles($user->roles, $user->id)
            )
            ->when(
                $request->status && $request->status == 'all',
                fn ($q) => $q->withTrashed()
            )
            ->when(
                $request->status && $request->status == 'inactive',
                fn ($q) => $q->onlyTrashed()
            )
            ->orderBy('scases.name')
            ->limit($limit)
            ->get();

        return apiResponse($cases);
    }

    /**
     * Staff (teachers / specialists) that are actually assigned to cases
     * of the current center, used by the cases list filter.
     */
    public function assignedStaff(Request $request)
    {
        $user = auth()->user();
        $isAdminCases = $user->can('admin_cases');

        $center = null;
        if($request->center_id && $user->isInCenter($request->center_id)) {
            $center = $request->center_id;
        }
        else if($user->centers){
            $center = $user->centers[0]->id;
        }

        if(!$center) {
            return apiResponse([]);
        }

        $staffTypes = [
            System::USER_TYPE_TEACHER,
            System::USER_TYPE_PHYSIOTHERAPIST,
            System::USER_TYPE_OCCUPATIONAL_THERAPY,
            System::USER_TYPE_SOCIAL,
            System::USER_TYPE_PRONUNCIATION_SPEECH,
            System::USER_TYPE_MENTAL,
            System::USER_TYPE_PSYCHOTHERAPIST,
        ];

        $visibleCaseIds = null;
        if(!$isAdminCases) {
            $visibleCaseIds = SCase::withTrashed()
                ->where('scases.center_id', $center)
                ->usersRoles($user->roles, $user->id)
                ->pluck('scases.id');
        }

        $staff = User::select('users.id', 'users.name', 'users.job_title')
            ->with('roles:id,name,default_name')
            ->whereHas('centers', function ($query) use ($center) {
                $query->where('centers.id', $center);
            })
            ->whereIn('users.id', function ($query) use ($center, $staffTypes, $visibleCaseIds) {
                $query->select('scase_user.user_id')
                    ->from('scase_user')
                    ->join('scases', 'scases.id', '=', 'scase_user.scase_id')
                    ->where('scases.center_id', $center)
                    ->whereIn('scase_user.relationship_type', $staffTypes);

                if($visibleCaseIds !== null) {
                    $query->whereIn('scase_user.scase_id', $visibleCaseIds);
                }
            })
            ->orderBy('users.name')
            ->get();

        return apiResponse($staff);
    }

    public function show(Request $request, $case)
    {
        $user = auth()->user();
        $center = null;
        if($request->center_id && $user->isInCenter($request->center_id)) {
            $center = $request->center_id;
        }
        else if($user->centers){ 
            $center = $user->centers[0]->id;
        }

        $case = SCase::with(
            'disabilities', 
            'services', 
            'parents', 
            'teacher', 
            'psychotherapist', 
            'occupational_therapy', 
            'physiotherapist', 
            'pronunciation_speech'
        )
        ->where('scases.center_id', $center)
        ->withTrashed()
        ->find($case);

        if($case)
            $case = new SCaseResource($case);
        else
            $case = null;

        return apiResponse($case);
    }

    public function put(SCaseRequest $request, SCase $case = null){

        $type = 'add_case';
        if($case)   
            $type = 'edit_case';

        $input = $request->validated();

        $user = auth()->user();
        if($input['center_id'] && !$user->isInCenter($input['center_id']))
            return response()->json(['errors' => ['error' => [__("validation.You don't belong to this center")]]], 422);

        $center = Center::find($input['center_id']);
        $nCases = SCase::where('center_id', $center->id)->count();
        if($center->package->identifier == 'trial' && $nCases >= 10)
            return response()->json(['errors' => ['error' => [__("validation.Your are using trial package and can not create more than 10 users")]]], 422);

        if($center->package->identifier != 'trial' && $center->paid_service && $nCases >= $center->number_of_cases)
            return response()->json(['errors' => ['error' => [__("tr.You can not create more than :max", ['max'=> $center->number_of_cases])]]], 422);

        $case = SCase::updateOrCreate(['id' => $case ? $case->id : null], $request->validated());
        $case->disabilities()->sync(json_decode($input['disability_type_ids'], true));
        $case->services()->sync(json_decode($input['services_provided'], true));

        $case->parents()->syncWithPivotValues(json_decode($input['parent_id'], true), ['relationship_type' => System::USER_TYPE_PARENT]);
        $case->teacher()->syncWithPivotValues(json_decode($input['teacher_id'], true), ['relationship_type' => System::USER_TYPE_TEACHER]);

        $case->physiotherapist()->syncWithPivotValues($input['physiotherapist_id'] ? [$input['physiotherapist_id']] : [], ['relationship_type' => System::USER_TYPE_PHYSIOTHERAPIST]);
        $case->occupational_therapy()->syncWithPivotValues($input['occupational_therapy_id'] ? [$input['occupational_therapy_id']] : [], ['relationship_type' => System::USER_TYPE_OCCUPATIONAL_THERAPY]);
        $case->psychotherapist()->syncWithPivotValues($input['psychotherapist_id'] ? [$input['psychotherapist_id']] : [], ['relationship_type' => System::USER_TYPE_PSYCHOTHERAPIST]);
        $case->pronunciation_speech()->syncWithPivotValues($input['pronunciation_speech_specialist_id'] ? [$input['pronunciation_speech_specialist_id']] : [], ['relationship_type' => System::USER_TYPE_PRONUNCIATION_SPEECH]);
        
         if($request->hasFile('image')){
            $currentImage = null;
            if($request->current_image)
                $currentImage = $request->current_image;
            $case->setImage($input['image'], $currentImage);
        }

        Log::add($type, $case, $case->id, '');

        return apiResponse([]);
    }

    public function delete($id)
    {
        $record = SCase::find($id);
        if (!$record) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }
        $record->delete();
        Log::add('delete_case', $record, $record->id, '');
        return response()->json(['message' => 'Record deleted successfully', 'status' => true]);
    }

    public function restore($id)
    {
        $record = SCase::withTrashed()->find($id);
        if (!$record) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }
        $record->restore();
        Log::add('restore_case', $record, $record->id, '');
        return response()->json(['message' => 'Record restored successfully', 'status' => true]);
    }

    public function fetchImage(Request $request, SCase $case) {

        if($request->attendance_id) {
            $attendance = Attendance::find($request->attendance_id);
            $fileURL = $attendance->urlFile();
            if($fileURL)
                return apiResponse($fileURL);
        }
        else {
            $imageURL = $case->urlImage();
            if($imageURL)
                return apiResponse($imageURL);
        }

        return apiResponse(null);
    }

    public function deleteImage($id)
    {
        $record = SCase::find($id);
        if (!$record) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }
        $record->deleteImage();
        Log::add('delete_case_image', $record, $record->id, '');
        return response()->json(['message' => 'Image deleted successfully', 'status' => true]);
    }

    public function files(Request $request, SCase $case)
    {
        $keywords = getFTS($request->q);
        
        $perPage = resolvePerPage($request);

        $files = $case->fetchFiles($keywords);
        $paginate = paginate($files, $perPage, $request->page, $request->options);
        
        return apiPaginateResponse($paginate, SCaseFileResource::collection($paginate->items()));
    }

    public function addFile(Request $request, SCase $case)
    {
        if($request->hasFile('file')) {

            $file = $request->file('file');
            $fileName = $request->file_name;
            $fileName = str_replace(' ', '_', $fileName);
            $fileName .= '.'.$file->getClientOriginalExtension();
            $files = $case->fetchFiles($fileName);

            if(!$files || (is_array($files) && count($files) == 0)) {

                $case->saveFile($fileName, $file);
        
                Log::add('add_case_file', $case, $case->id, '');
            }
            else {
                return response()->json(['errors' => ['error' => [__('validation.There is already a file with the same name')]]], 422);
            }
        }
        return response()->json(['message' => 'Added successfully.', 'status' => true], 200);
    }

    public function deleteFile(Request $request, SCase $case)
    {
        $case->deleteFile($request->file_name, $request->file_extension);
        Log::add('delete_case_file', $case, $case->id, '');
        return response()->json(['message' => 'Record deleted successfully', 'status' => true]);
    }

    public function pdf(Request $request, SCase $case, $pdfType)
    {
        ini_set('max_execution_time', 30000);
        $date = Carbon::now()->toDateString();
        if($pdfType && $pdfType == 'general_data'){
            $token = PDF::savePdf('cases.general_data',[
                'center' => Center::find($case->center_id),
                'case' => $case,
                'questions' => json_decode($case->general_questions, true),
            ],'Case General Data '.$date.'.pdf');
            return success(['url'=> route('file.download', ['token'=> $token])]);
        }
        else if($pdfType && $pdfType == 'case_study'){
            $token = PDF::savePdf('cases.case_study',[
                'center' => Center::find($case->center_id),
                'case' => $case,
                'case_study' => json_decode($case->case_study, true),
            ],'Case Case Study '.$date.'.pdf');
            return success(['url'=> route('file.download', ['token'=> $token])]);
        }
        else if($pdfType && $pdfType == 'psychological_study'){
            $token = PDF::savePdf('cases.psychological_study',[
                'center' => Center::find($case->center_id),
                'case' => $case,
                'psychological_study' => json_decode($case->psychological_study, true),
            ],'Case Psychological Study '.$date.'.pdf');
            return success(['url'=> route('file.download', ['token'=> $token])]);
        }
    }

    public function fees(Request $request){
    
        $perPage = resolvePerPage($request);

        $keywords = mb_ereg_replace(" ", "%", getFTS($request->q));

        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);
        
        $fees = SCaseFee::select('scases_fees.*')
        ->with([
            'case:id,name',
            'term',
            'services',
            'createdBy:id,name',
            'payments',
        ])
        ->withSum(['payments as paid_payments_sum' => function ($query) {
            $query->where('status', SCasePayment::STATUS_PAID);
        }], 'amount')
        ->whereHas('case', function ($query) use ($center) {
            $query->where('scases.center_id', $center);
        })
        ->when(
            $request->q,
            fn ($q) => $q->where('services', 'like',"%{$keywords}%")
        )
        ->when(
            $request->case_id,
            fn ($q) => $q->where('case_id', $request->case_id)
        )
        ->when(
            $request->service_id,
            fn ($q) => $q->join('scases_fees_services', function($join) use($request) {
                $join->on('scases_fees_services.case_fee_id', '=', 'scases_fees.id');
                $join->where('scases_fees_services.service', $request->service_id);
            })
        )
        ->when(
            $request->status &&  $request->status == 'all',
            fn ($q) => $q->withTrashed()
        )
        ->when(
            $request->status &&  $request->status == 'inactive',
            fn ($q) => $q->onlyTrashed()
        )
        ->orderBy('term_id', 'DESC')
        ->orderBy('case_id', 'ASC');

        if (!Term::applyVisibleTerm($fees, $user, $center, $request->term_id)) {
            return Term::forbiddenResponse();
        }
        
        if($request->pdf) {
        
            $dateNow = Carbon::now()->toDateString();
            $data = \App\Http\Resources\Case\SCaseFeeResource::collection($fees->get());
            $items = $data->toArray($request);
        
            $case = [];
            if($request->case_id)
                $case = SCase::find($request->case_id);
        
            $title = null;
            $bladePath = null;
            $pdfData = null;
            if($request->pdf == 'fees'){
        
                $title = 'Fees';
                $bladePath = 'fees';
                $pdfData = [
                    'center' => Center::find($center),
                    'items' => $items,
                    'case' => $case,
                ];
            }
            else if($request->pdf == 'case_fees'){

                $title = 'Case Fees';
                $bladePath = 'cases.fees';
                $pdfData = [
                    'center' => Center::find($center),
                    'items' => $items,
                    'case' => $case,
                ];
            }
        
            if($title && $bladePath && $pdfData) {
        
                $token = PDF::savePdf($bladePath, $pdfData,"{$title} {$dateNow}.pdf");
                return success(['url'=> route('file.download', ['token'=> $token])]);
            }
        }

        $fees = $fees->paginate($perPage);
        return apiPaginateResponse($fees, SCaseFeeResource::collection($fees));
    }

    public function feesItems(Request $request){

        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);

        $fees = SCaseFee::with(['case', 'term', 'services', 'createdBy:id,name', 'payments'])
        ->whereHas('case', function ($query) use ($center) {
            $query->where('scases.center_id', $center);
        })
        ->when(
            $request->case_id,
            fn ($q) => $q->where('case_id', $request->case_id)
        )
        ->when(
            $request->except,
            fn ($q) => $q->whereNotIn('id', $request->except)
        );

        if (!Term::applyVisibleTerm($fees, $user, $center, $request->term_id)) {
            return Term::forbiddenResponse();
        }

        $fees = $fees->get();
        return apiResponse(SCaseFeeResource::collection($fees));
    }
    
    public function showFee(Request $request, SCaseFee $fee){

        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);

        $fee = $fee->load(['case', 'term', 'services', 'createdBy:id,name', 'payments']);
        $denied = Term::abortIfInaccessible($user, $center ?: optional($fee->case)->center_id, $fee->term_id);
        if ($denied) {
            return $denied;
        }
        if($fee->case->center_id == $center)
            $fee = new SCaseFeeResource($fee);
        else
            $fee = null;
    
        return apiResponse($fee);
    }
    
    public function putFee(Request $request){

        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);
        $denied = Term::abortIfInaccessible($user, $center, $request->term_id);
        if ($denied) {
            return $denied;
        }

        if($request->id>0)
            $fee = SCaseFee::find($request->id);
        else
            $fee = new SCaseFee();

        $services = explode(',', $request->services);
        $feeExists = SCaseFee::join('scases_fees_services', 'scases_fees_services.case_fee_id', 'scases_fees.id')
        ->when(
            $request->id>0,
            fn ($q) => $q->where('scases_fees.id', '!=', $request->id)
        )
        ->where('case_id', $request->case_id)
        ->where('term_id', $request->term_id)
        ->whereIn('service', $services)
        ->exists();
        if($feeExists)
            return response()->json(['errors' => ['error' => [__("validation.payments.This service fee already exists")]]], 422);

        $fee->case_id = $request->case_id;
        $fee->term_id = $request->term_id;
        $fee->amount = $request->amount;
        $fee->notes = ($request->notes != null && $request->notes != 'null') ? $request->notes : null;
        $fee->created_by = auth()->user()->id;
        $fee->save();
        
        ScaseFeeService::where('case_fee_id', $fee->id)->delete();
        foreach ($services as $service) {
            $feeService = new ScaseFeeService();
            $feeService->case_fee_id = $fee->id;
            $feeService->service = $service;
            $feeService->save();
        }

        $type = 'add_fee';
        if($request->id>0)
            $type = 'edit_fee';

        Log::add($type, $fee, $fee->id, '');

        if($request->id>0)
            return response()->json(['message' => 'Updated successfully.', 'status' => true], 200);
        else
            return response()->json(['message' => 'Added successfully.', 'status' => true], 200);
    }
    
    public function deleteFee($id) {
    
        $record = SCaseFee::find($id);
        if(!$record)
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        
        $record->delete();
        Log::add('delete_fee', $record, $record->id, '');
        return response()->json(['message' => 'Deleted successfully.', 'status' => true], 200);
    }

    public function restoreFee($id)
    {
        $record = SCaseFee::withTrashed()->find($id);
        if (!$record) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }

        $record->restore();
        Log::add('restore_fee', $record, $record->id, '');
        return response()->json(['message' => 'Record restored successfully', 'status' => true]);
    }

    public function payments(Request $request){

        $perPage = resolvePerPage($request);

        $keywords = mb_ereg_replace(" ", "%", getFTS($request->q));

        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);

        $payments = SCasePayment::with([
            'scase:id,name',
            'term',
            'caseFee.services',
            'createdBy:id,name',
        ])
        ->whereHas('scase', function ($query) use ($center) {
            $query->where('scases.center_id', $center);
        })
        ->when(
            $request->id,
            fn ($q) => $q->where('id', $request->id)
        )
        ->when(
            $request->scase_id,
            fn ($q) => $q->where('scase_id', $request->scase_id)
        )
        ->when(
            $request->case_fee_id,
            fn ($q) => $q->where('case_fee_id', $request->case_fee_id)
        )
        ->when(
            $request->batch,
            fn ($q) => $q->where('batch', $request->batch)
        )
        ->when(
            $request->status && $request->status!='all',
            fn ($q) => $q->where('status', $request->status)
        )
        ->when(
            $request->status==0 && $request->status!=null,
            fn ($q) => $q->where('status', 0)
        )
        ->orderBy('scase_id', 'ASC')
        ->orderBy('batch', 'ASC');

        if (!Term::applyVisibleTerm($payments, $user, $center, $request->term_id)) {
            return Term::forbiddenResponse();
        }
        
        
        if($request->pdf) {

            $dateNow = Carbon::now()->toDateString();
            $data = \App\Http\Resources\Case\SCasePaymentResource::collection($payments->get());
            $items = $data->toArray($request);

            $scase = [];
            if($request->scase_id)
                $scase = SCase::find($request->scase_id);

            $title = null;
            $bladePath = null;
            $pdfData = null;
            if($request->pdf == 'payments') {

                $title = 'Payments';
                $bladePath = 'payments';
                $pdfData = [
                    'center' => Center::find($center),
                    'items' => $items,
                    'case' => $scase,
                ];
            }
            else if($request->pdf == 'payment_statement') {

                $title = 'Payment Statment';
                $bladePath = 'payment_statement';
                $pdfData = [
                    'center' => Center::find($center),
                    'items' => $items,
                    'case' => $scase,
                    'currency' => (\App::getLocale() == 'en') ? config('motabaa.currency.en') : config('motabaa.currency.ar')
                ];
            }

            if($title && $bladePath && $pdfData) {

                $token = PDF::savePdf($bladePath, $pdfData,"{$title} {$dateNow}.pdf");
                return success(['url'=> route('file.download', ['token'=> $token])]);
            }
        }

        $payments = $payments->paginate($perPage);
        return apiPaginateResponse($payments, SCasePaymentResource::listCollection($payments));
    }

    public function showPayment(Request $request, SCasePayment $payment){

        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);

        $payment = $payment->load(['scase', 'term']);
        $denied = Term::abortIfInaccessible($user, $center ?: optional($payment->scase)->center_id, $payment->term_id);
        if ($denied) {
            return $denied;
        }
        if($payment->scase->center_id == $center && $payment->term->center_id == $center)
            $payment = new SCasePaymentResource($payment);
        else
            $payment = null;

        return apiResponse($payment);
    }

    public function putPayment(Request $request){

        if(!$request->case_fee_id)
            return response()->json(['errors' => ['error' => [__("validation.payments.You must choose fee")]]], 422);

        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);
        $denied = Term::abortIfInaccessible($user, $center, $request->term_id);
        if ($denied) {
            return $denied;
        }

        if($request->id>0) {

            $payment = SCasePayment::find($request->id);
        }
        else {

            $payment = new SCasePayment();
            $payment->status = SCasePayment::STATUS_UNPAID;
        }
        
        $paymentExists = SCasePayment::when(
            $request->id>0,
            fn ($q) => $q->where('id', '!=', $request->id)
        )
        ->where('scase_id', $request->scase_id)
        ->where('term_id', $request->term_id)
        ->where('case_fee_id', $request->case_fee_id)
        ->where('batch', $request->batch)
        ->exists();
        if($paymentExists)
            return response()->json(['errors' => ['error' => [__("validation.payments.This batch already exists")]]], 422);

        $amount = SCaseFee::find($request->case_fee_id)->amount;
        $paidAmount = $paymentExists = SCasePayment::when(
            $request->id>0,
            fn ($q) => $q->where('id', '!=', $request->id)
        )
        ->where('scase_id', $request->scase_id)
        ->where('term_id', $request->term_id)
        ->where('case_fee_id', $request->case_fee_id)
        ->get()
        ->sum('amount');
        if(($paidAmount+$request->amount) > $amount)
            return response()->json(['errors' => ['error' => [__("validation.payments.You can not exceed this amount")." {$amount}"]]], 422);

        $payment->scase_id = $request->scase_id;
        $payment->term_id = $request->term_id;
        $payment->case_fee_id = $request->case_fee_id;
        $payment->batch = $request->batch;
        $payment->amount = $request->amount;
        $payment->due_date = $request->due_date;
        $payment->created_by = auth()->user()->id;
        $payment->save();

        $type = 'add_payment';
        if($request->id>0)
            $type = 'edit_payment';

        Log::add($type, $payment, $payment->id, '');

        if($request->id>0)
            return response()->json(['message' => 'Updated successfully.', 'status' => true], 200);
        else
            return response()->json(['message' => 'Added successfully.', 'status' => true], 200);
    }

    public function actionPayment(Request $request, SCasePayment $payment) {

        if($request->action == 'pay') {

            $payment->status = SCasePayment::STATUS_PAID;
            $payment->payment_date = Carbon::now();
            $payment->paid_by = $request->paid_by;
            $payment->method = $request->method;
            $payment->notes = $request->notes;
            if($request->hasFile('file'))
                $payment->saveFile($request->file('file'));
        }
        else if($request->action == 'unpay') {

            $payment->status = SCasePayment::STATUS_UNPAID;
            $payment->payment_date = null;
            $payment->paid_by = null;
            $payment->method = null;
            $payment->notes = null;
            $payment->deleteFile();
        }

        $payment->save();

        Log::add($request->action.'_payment', $payment, $payment->id, '');

        return response()->json(['message' => 'Updated successfully.', 'status' => true], 200);
    }

    public function deletePayment($id) {

        $record = SCasePayment::find($id);
        if($record->status == 1)
            return response()->json(['errors' => ['error' => [__('validation.payments.This payment has been paid')]]], 422);

        $record->delete();
        Log::add('delete_payment', $record, $record->id, '');
        return response()->json(['message' => 'Deleted successfully.', 'status' => true], 200);
    }

    public function fetchPaymentFile(Request $request, SCasePayment $payment) {

        $fileURL = $payment->fileURL();
        if($fileURL)
            return apiResponse($fileURL);

        return apiResponse(null);
    }

    public function import(Request $request)
    {
        if($request->import == 'import_cases') {
            return $this->importCases($request);
        }
    }

    public function importCases(Request $request)
    {
        if($request->action == 'preview') {

            $nErrors = 0;
            $data = [];
            $errors = [];
            $nEmpty = 0;
            $file_mimes = array('text/x-comma-separated-values', 'text/comma-separated-values', 'application/octet-stream', 'application/vnd.ms-excel', 'application/excel', 'application/vnd.msexcel', 'text/plain', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            if(isset($_FILES['file']['name']) && in_array($_FILES['file']['type'], $file_mimes)) {

                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                $excel = $reader->load($_FILES['file']['tmp_name']);
                $sheetCount = $excel->getSheetCount();

                $genders = [];
                $genders['ذكر'] = 1; 
                $genders['انثى'] = 2; 
            
                $periods = [];
                $periods['فترة صباحية'] = 1; 
                $periods['فترة مسائية'] = 0; 
            
                $nationalities = array_flip(__('nationalities'));

                $nEmptyName = 0;
            
                for ($i = 0; $i < $sheetCount; $i++) {		
                    
                    $sheet = $excel->getSheet($i);
            
                    $rows = $sheet->toArray(null, true, true, true);
            
                    $headers = null;
            
                    foreach ($rows as $values) {

                        $errors = [];
            
                        if(empty($values)) {
                            $nEmpty++;
                            continue;
                        }
                        if(empty($headers)) {

                            if(in_array('name', $values)) {
                                $data = [];
                                $data['headers'][] = [
                                    'title'=> __("tr.errors"),
                                    'key'=> 'errors'
                                ];
                                foreach ($values as $header) {
                                    $data['headers'][] = [
                                        'title'=> __("tr.{$header}"),
                                        'key'=> $header
                                    ];
                                }
                            }

                            $headers = (object)array_flip($values); 
                            continue; 
                        }

                        if(empty($headers->name) || empty($values[$headers->name])) {
                            $nEmptyName++;
                            continue;
                        }
            
                        $case = SCase::where('id_or_residence_number', $values[$headers->id_or_residence_number])->withTrashed()->first();

                        $case = null;
                        if($values[$headers->id_or_residence_number] || $values[$headers->beneficiary_number]) {
                            $case = SCase::when(
                                $values[$headers->id_or_residence_number],
                                fn ($q) => $q->orWhere('id_or_residence_number', $values[$headers->id_or_residence_number])
                            )->when(
                                $values[$headers->beneficiary_number],
                                fn ($q) => $q->orWhere('beneficiary_number', $values[$headers->beneficiary_number])
                            )
                            ->withTrashed()
                            ->first();
                        }

                        if($case) {
                            $errors[] = __("tr.Error").": ".__("tr.Errors.Already exists");
                        }

                        $case = new SCase();
            
                        if(!array_key_exists($values[$headers->period], $periods)) {
                            $errors[] = __("tr.Error").": ".__("tr.Errors.Missing period value");
                        }
            
                        if(!array_key_exists($values[$headers->nationality], $nationalities)) {
                            $errors[] = __("tr.Error").": ".__("tr.Errors.Missing nationality value");
                        }
            
                        if(!array_key_exists($values[$headers->gender], $genders)) {
                            $errors[] = __("tr.Error").": ".__("tr.Errors.Missing gender value");
                        }

                        $disabilitiesID = [];
                        $disabilities = explode(',', $values[$headers->disabilities]);
                        if(is_array($disabilities)) {
                            foreach ($disabilities as $disabilityName) {
                                if($disabilityName) {
                                    $disability = Disability::where('name_ar', $disabilityName)->first();
                                    if(!$disability) {
                                        $errors[] = __("tr.Error").": ".__("tr.Errors.Invalid disability")." ({$disabilityName})";
                                    }else {
                                        $disabilitiesID[] = $disability->id;
                                    }
                                }
                            }
                        }

                        $servicesID = [];
                        $services = explode(',', $values[$headers->services]);
                        if(is_array($services)) {
                            foreach ($services as $serviceName) {
                                if($serviceName) {
                                    $service = Service::where('name_ar', $serviceName)->first();
                                    if(!$service) {
                                        $errors[] = __("tr.Error").": ".__("tr.Errors.Invalid service")." ({$serviceName})";
                                    }else {
                                        $servicesID[] = $service->id;
                                    }
                                }
                            }
                        }

                        $parentsID = [];
                        $parents = explode(',', $values[$headers->parents]);
                        if(is_array($parents)) {
                            foreach ($parents as $parentName) {
                                if($parentName) {
                                    $parent = User::where('name', $parentName)->first();
                                    if(!$parent) {
                                        $errors[] = __("tr.Error").": ".__("tr.Errors.Invalid parent")." ({$parentName})";
                                    }else {
                                        $parentsID[] = $parent->id;
                                    }
                                }
                            }
                        }
            
                        $case->code = $values[$headers->code];
                        $case->name = $values[$headers->name];
                        $case->beneficiary_number = $values[$headers->beneficiary_number];
                        $case->period = $values[$headers->period];
                        $case->period_id = isset($periods[$values[$headers->period]]) ? $periods[$values[$headers->period]] : null;
                        $case->nationality = $values[$headers->nationality];
                        $case->nationality_code = isset($nationalities[$values[$headers->nationality]]) ? $nationalities[$values[$headers->nationality]] : null;
                        $case->gender = $values[$headers->gender];
                        $case->gender_id = isset($genders[$values[$headers->gender]]) ? $genders[$values[$headers->gender]] : null;

                        if(empty($values[$headers->birthdate])) {
                            $case->birthdate = null;
                        }
                        else {
                            $birthdate = Carbon::parse($values[$headers->birthdate])->toDateString();
                            $case->birthdate = $birthdate;
                        }
            
                        $case->phone = $values[$headers->phone];
                        $case->emergency_contact = $values[$headers->emergency_contact];
            
                        $case->blood_type = $values[$headers->blood_type];
            
                        $case->address_number = $values[$headers->address_number];
                        $case->address_unit = $values[$headers->address_unit];
                        $case->address_building = $values[$headers->address_building];
                        $case->address_street = $values[$headers->address_street];
                        $case->address_area = $values[$headers->address_area];
                        $case->address_city = $values[$headers->address_city];
                        $case->address_zipcode = $values[$headers->address_zipcode];

                        $case->registration = $values[$headers->registration];

                        $case->disabilities_id = $disabilitiesID;
                        $case->disabilities = $values[$headers->disabilities];
                        
                        $case->services_id = $servicesID;
                        $case->services = $values[$headers->services];
                        
                        $case->parents_id = $parentsID;
                        $case->parents = $values[$headers->parents];

                        $case->errors = $errors;

                        if(count($errors)>0)
                            $nErrors++;
                        
                        $data['records'][] = $case;
                    }	    
                }
            }

            $data['n_errors'] = $nErrors;
            return apiResponse($data);
        }
        else {

            foreach ($request->data as $record) {

                if(isset($record['id']) && $record['id']>0)
                    $case = SCase::where('id', $record['id'])->withTrashed()->first();
                else
                    $case = new SCase();

                $case->center_id = $request->center_id;
                $case->name = $record['name'];
                $case->beneficiary_number = isset($record['beneficiary_number']) ? $record['beneficiary_number'] : null;
                $case->period = isset($record['period_id']) ? $record['period_id'] : null;
                $case->nationality = isset($record['nationality_code']) ? $record['nationality_code'] : null;
                $case->gender = isset($record['gender_id']) ? $record['gender_id'] : null;
                $case->id_or_residence_number = isset($record['id_or_residence_number']) ? $record['id_or_residence_number'] : null;
                $case->birthdate = isset($record['birthdate']) ? $record['birthdate'] : null;
                $case->phone = isset($record['phone']) ? $record['phone'] : null;
                $case->emergency_contact = isset($record['emergency_contact']) ? $record['emergency_contact'] : null;
                $case->blood_type = isset($record['blood_type']) ? $record['blood_type'] : null;
                $case->address_unit = isset($record['address_unit']) ? $record['address_unit'] : null;
                $case->address_building = isset($record['address_building']) ? $record['address_building'] : null;
                $case->address_street = isset($record['address_street']) ? $record['address_street'] : null;
                $case->address_area = isset($record['address_area']) ? $record['address_area'] : null;
                $case->address_city = isset($record['address_city']) ? $record['address_city'] : null;
                $case->address_zipcode = isset($record['address_zipcode']) ? $record['address_zipcode'] : null;
                $case->address_number = isset($record['address_number']) ? $record['address_number'] : null;
                $case->save();
    
                $emergencyContact = isset($record['emergency_contact']) ? $record['emergency_contact'] : null;
                $phone = isset($record['phone']) ? $record['phone'] : null;
                $parent = null;
                if($emergencyContact) $parent = User::where('phone', $emergencyContact)->first();
                if($phone && !$parent) $parent = User::where('phone', $phone)->first();


                if(!isset($record['parents_id']))
                    $record['parents_id'] = [];

                if($parent && !in_array($parent->id, $record['parents_id'])) {
                    $record['parents_id'][] = $parent->id;
                }

                foreach ($record['parents_id'] as $parentID) {
                    if(!in_array($parentID, $case->parents()->pluck('id')->toArray())) {
                        \DB::statement("INSERT INTO scase_user(scase_id, user_id, relationship_type) VALUES($case->id, $parentID, 0)");
                    }
                }

                if(isset($record['disabilities_id']) && is_array($record['disabilities_id'])) {
                    foreach ($record['disabilities_id'] as $disabilityID) {
                        if($disabilityID && !$case->disabilities()->where('disability_id', $disabilityID)->exists()) {
                            \DB::statement("INSERT INTO scase_disability(scase_id, disability_id) VALUES($case->id, {$disabilityID})");
                        }
                    }
                }

                if(isset($record['services_id']) && is_array($record['services_id'])) {
                    foreach ($record['services_id'] as $serviceID) {
                        if($serviceID && !$case->services()->where('service_id', $serviceID)->exists()) {
                            \DB::statement("INSERT INTO scase_service(scase_id, service_id) VALUES($case->id, {$serviceID})");
                        }
                    }
                }
            }
        
            return response()->json(['message' => 'saved_successfully', 'status' => true], 200);
        }
    }
}
