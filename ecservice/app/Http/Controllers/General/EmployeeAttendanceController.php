<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Log;
use App\Models\User;
use App\Models\EmployeeAttendance;
use App\Models\Center;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\EmployeeAttendanceResource;
use App\Http\Resources\Admin\User\UserResource;
use Carbon\Carbon;
use App\Models\System\System;
use App\Models\System\PDF;

use App\Models\System\ExportExcel;

class EmployeeAttendanceController extends Controller
{

    public function index(Request $request){

        $perPage = resolvePerPage($request);

        $attendanceAt = $request->attendance_at;
        $keywords = mb_ereg_replace(" ", "%", getFTS($request->q));

        $user = auth()->user();
        $center = null;
        if($request->center_id && $user->isInCenter($request->center_id)) {
            $center = $request->center_id;
        }
        else if($user->centers){ 
            $center = $user->centers[0]->id;
        }

        $users = User::with('centers')
        ->select('employees_attendance.*', 'employees_attendance.id as employees_attendance_id', 'users.id as id', 'users.name', 'users.image_path')
        ->whereHas('centers', function ($query) use ($center) {
            $query->where('centers.id', $center);
        })
        ->when(
            !$request->user_id && $request->export == 'export_attendance',
            fn ($q) => $q->join('employees_attendance', function($join) use($attendanceAt) {
                $join->on('employees_attendance.user_id', '=', 'users.id');
                $join->when(
                    $attendanceAt,
                    fn ($q) => $q->where('employees_attendance.attendance_at', $attendanceAt)
                );
            })
            ->leftJoin('users as employees', 'employees.id', 'employees_attendance.created_by')
            ->addSelect('employees.name as created_by_name')
        )
        ->when(
            !$request->user_id && !$request->export,
            fn ($q) => $q->leftJoin('employees_attendance', function($join) use($attendanceAt) {
                $join->on('employees_attendance.user_id', '=', 'users.id');
                $join->when(
                    $attendanceAt,
                    fn ($q) => $q->where('employees_attendance.attendance_at', $attendanceAt)
                );
            })
            ->leftJoin('users as employees', 'employees.id', 'employees_attendance.created_by')
            ->addSelect('employees.name as created_by_name')
        )
        ->when(
            $request->user_id,
            fn ($q) => $q->join('employees_attendance', function($join) use($request) {
                $join->on('employees_attendance.user_id', '=', 'users.id');
                $join->when(
                    $request->from_date,
                    fn ($q) => $q->where('employees_attendance.attendance_at', '>=', $request->from_date)
                )
                ->when(
                    $request->to_date,
                    fn ($q) => $q->where('employees_attendance.attendance_at', '<=', $request->to_date)
                );
            })
            ->where('employees_attendance.user_id', $request->user_id)
            ->whereNotNull('employees_attendance.status')
            ->leftJoin('users as employees', 'employees.id', 'employees_attendance.created_by')
            ->addSelect('employees.name as created_by_name')
        )
        ->whereHas('roles', function ($query) use ($request) {
            $query->whereNotIn('default_name', ['parent', 'admin'])
            ->orWhereNull('default_name');
        })
        ->when(
            $request->q,
            fn ($q) => $q->where('search_text', 'like',"%{$keywords}%")
        )
        ->orderBy('users.name', 'ASC')
        ->orderBy('employees_attendance.attendance_at', 'DESC');
        
        if($request->export == 'export_attendance') {

            $users = $users->get();
            $statusLabels = \App\Models\Attendance::statusLabels();
            $date = Carbon::now()->toDateString();
            $token = PDF::savePdf('employees_attendance',[
                'center' => Center::find($center),
                'users' => $users,
                'status_labels' => $statusLabels,
            ],'Employees Attendance '.$date.'.pdf');
            return success(['url'=> route('file.download', ['token'=> $token])]);
        }

        $users = $users->paginate($perPage);

        return apiPaginateResponse($users, EmployeeAttendanceResource::collection($users));
    }

    public function show(Request $request, EmployeeAttendance $attendance){

        return apiResponse(new EmployeeAttendanceResource($attendance));
    }

    public function put(Request $request){

        $message = 'Updated successfully.';
        $attendance = EmployeeAttendance::where('user_id', $request->user_id)
        ->where('attendance_at', $request->attendance_at)
        ->first();

        if(!$attendance) {
            $message = 'Added successfully.';
            $attendance = new EmployeeAttendance();
            $attendance->user_id = $request->user_id;
            $attendance->attendance_at = $request->attendance_at;
        }

        $attendance->created_by = auth()->user()->id;
        $attendance->status = $request->status;

        if($request->status == null || $request->status == 'null') {
            $attendance->created_by = null;
            $attendance->status = null;
        }

        if($attendance->status == 1) {
            if($request->from_or_to == 'from') {
                $attendance->from = $request->time;
            }
            else if($request->from_or_to == 'to') {
                $attendance->to = $request->time;
            }
        }
        else if($attendance->status === 0 || $attendance->status === '0') {
            if($request->from_or_to == 'from') {
                $attendance->from = null;
                $attendance->to = null;
            }
            else if($request->from_or_to == 'to') {
                $attendance->to = null;
            }
        }

        $attendance->save();
        
        if($attendance->status !== 0 && $attendance->status !== '0') {
            $fileName = "absent_file_{$attendance->attendance_at}";
            $attendance->user->deleteAttendanceFile($fileName);
        }
        else {

            if($request->hasFile('file') && $attendance->user) {
                $fileName = "absent_file_{$attendance->attendance_at}";
                $attendance->user->deleteAttendanceFile($fileName);
                $attendance->user->setAttendanceFile($fileName, $request->file('file'));
            }
        }

        $type = 'absent_employee';
        if($attendance->status == 1)
            $type = 'attend_employee';

        $description = '';
        if($attendance->from)
            $description .= "من {$attendance->from} ";
        if($attendance->to)
            $description .= "الى {$attendance->to}";

        Log::add($type, $attendance, $attendance->id, $description);

        return response()->json(['message' => $message, 'status' => true], 200);
    }
}
