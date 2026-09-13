<?php

namespace App\Http\Controllers\General;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\SCase;
use App\Models\Attendance;
use App\Models\Center;
use App\Http\Resources\Case\SCasesResource;
use Carbon\Carbon;
use App\Models\Log;
use App\Models\System\System;
use App\Models\System\PDF;
use App\Http\Resources\AttendanceResource;

use App\Models\System\ExportExcel;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $perPage = resolvePerPage($request);

        $attendance_at = $request->attendance_at;
        $teacher_id = $request->teacher_id;
        $specialist_id = $request->specialist_id;
        $keywords = mb_ereg_replace(" ", "%", getFTS($request->q));

        $center = null;
        if($request->center_id && $user->isInCenter($request->center_id)) {
            $center = $request->center_id;
        }
        else if($user->centers){ 
            $center = $user->centers[0]->id;
        }
        
        $cases = SCase::select(['id', 'name', 'image_path', 'deleted_at'])
        ->with(['attendances' => function ($query) use ($attendance_at) {
            $query->where('attendance_at', $attendance_at)->with('createdBy:id,name');
        }])
        ->where('center_id', $center)
        ->when(
            $request->q,
            fn ($q) => $q->where('search_text', 'like',"%{$keywords}%")
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
        );

        $cases = $cases->paginate($perPage);
        return apiPaginateResponse($cases, SCasesResource::collection($cases));
    }

    public function export(Request $request)
    {
        $attendance_at = $request->attendance_at;
        $teacher_id = $request->teacher_id;
        $specialist_id = $request->specialist_id;
        $keywords = mb_ereg_replace(" ", "%", getFTS($request->q));

        $user = auth()->user();
        $center = null;
        if($request->center_id && $user->isInCenter($request->center_id)) {
            $center = $request->center_id;
        }
        else if($user->centers){ 
            $center = $user->centers[0]->id;
        }
        
        $cases = SCase::join('attendances', 'attendances.case_id', 'scases.id')
        ->leftJoin('users', 'users.id', 'attendances.created_by')
        ->where('center_id', $center)
        ->when(
            $request->q,
            fn ($q) => $q->where('search_text', 'like',"%{$keywords}%")
        )
        ->when(
            $request->attendance_at,
            fn ($q) => $q->where('attendances.attendance_at', $request->attendance_at)
        )
        ->when(
            $teacher_id,
            fn ($q) => $q->whereHas('teacher', function ($query) use ($teacher_id) {
                $query->where('users.id', $teacher_id);
            })
        )
        ->when(
            $request->date_from && $request->date_to,
            fn ($q) => $q ->whereBetween('attendance_at', [$request->date_from, $request->date_to])
        )
        ->when(
            $specialist_id,
            fn ($q) => $q->whereHas('specialists', function ($query) use ($specialist_id) {
                $query->where('users.id', $specialist_id);
            })
        )
        ->orderBy('scases.name', 'ASC');

        $statusLabels = \App\Models\Attendance::statusLabels();
        $date = Carbon::now()->toDateString();
        $pdfPath = null;
        $data = [];
        if($request->export == 'term_attendances') {
            
            $pdfPath = 'cases.term_attendances';
            $cases = $cases->selectRaw("scases.name, sum(attendances.status = 1) as attend, sum(attendances.status = 0) as absent")
            ->groupBy('scases.name')
            ->get();
            $data = [
                'center' => Center::find($center),
                'cases' => $cases,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'statusLabels' => $statusLabels,
            ];
        }
        else if($request->export == 'attendances') {

            $pdfPath = 'cases.attendance';
            $cases = $cases->select('attendances.id', 'scases.name', 'attendances.attendance_at', 'attendances.status', 'users.name as created_by_name')
            ->get();
            $data = [
                'center' => Center::find($center),
                'cases' => $cases,
                'statusLabels' => $statusLabels,
            ];
        }

        if($pdfPath) {

            $token = PDF::savePdf($pdfPath, $data, 'Cases Attendance '.$date.'.pdf');
            return success(['url'=> route('file.download', ['token'=> $token])]);
        }
    }

    public function put(Request $request, $id = null){

        $input = $request->all();
        if($request->status == 'null' || (!$request->status && !($request->status === 0)))
            $input['created_by'] = null;
        else
            $input['created_by'] = auth()->user()->id;

        $attendance = Attendance::updateOrCreate(['id' => $id], $input);

        if($attendance->status !== 0 && $attendance->status !== '0') {
            $attendance->deleteFile();
        }
        else {
            if($request->hasFile('file')) {
                $attendance->deleteFile();
                $attendance->saveFile($input['file']);
            }
        }

        $type = 'absent_case';
        if($attendance->status == 1)
            $type = 'attend_case';

        Log::add($type, $attendance, $attendance->id, '');
        $attendance->loadMissing('createdBy');
        return success(new AttendanceResource($attendance));
    }

    public function index_for_case(Request $request)
    {
        $user = auth()->user();
        $center = null;
        if($request->center_id && $user->isInCenter($request->center_id)) {
            $center = $request->center_id;
        }
        else if($user->centers){ 
            $center = $user->centers[0]->id;
        }

        $perPage = resolvePerPage($request);
        
        $attendances = Attendance::with('createdBy:id,name')->when(
            $request->case_id,
            fn ($q) => $q->join('scases', 'scases.id', 'attendances.case_id')
                ->where('case_id', $request->case_id)
                ->where('scases.center_id', $center)
        )
        ->when(
            $request->date_from && $request->date_to,
            fn ($q) => $q ->whereBetween('attendance_at', [$request->date_from, $request->date_to])
        );

        if($request->export == 'export_attendances') {

            $statusLabels = \App\Models\Attendance::statusLabels();
            $date = Carbon::now()->toDateString();
            $token = PDF::savePdf('cases.case_attendances',[
                'center' => Center::find($center),
                'case' => SCase::find($request->case_id),
                'attendances' => $attendances->get(),
                'statusLabels' => $statusLabels,
            ],'Case Attendances '.$date.'.pdf');
            return success(['url'=> route('file.download', ['token'=> $token])]);
        }

        $attendances = $attendances->paginate($perPage);

        return apiPaginateResponse($attendances, AttendanceResource::collection($attendances));
    }
}
