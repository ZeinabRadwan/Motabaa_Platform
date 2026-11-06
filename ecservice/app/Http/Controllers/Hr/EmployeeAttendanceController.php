<?php

namespace App\Http\Controllers\Hr;

use App\Http\Requests\Hr\EmployeeAttendanceRequest;
use App\Http\Resources\Hr\EmployeeAttendanceConfigResource;
use App\Http\Resources\Hr\EmployeeProfileResource;
use App\Models\Hr\Employee;
use App\Support\Hr\HrAttendanceRules;
use App\Support\Hr\HrEmployeeAttendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Employee attendance configuration (method, GPS verification, policy, grace period, with the shift and work
 * location from the same date). Changing it requires edit_hr-attendance-config (route middleware); viewing
 * the history and the resolved rules requires access to employee profiles.
 */
class EmployeeAttendanceController extends HrController
{
    public function index(Request $request, int $employee): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($employee) {
            $model = $this->findInCenter(Employee::class, $employee, $centerId);

            return success(EmployeeAttendanceConfigResource::collection(
                $model->attendanceConfigs()->with(['attendancePolicy', 'creator:id,name'])->orderByDesc('effective_from')->get()
            ));
        });
    }

    public function update(EmployeeAttendanceRequest $request, int $employee): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $employee) {
            $model = $this->findInCenter(Employee::class, $employee, $centerId);
            $data = $request->configuration();
            ['scheduled' => $scheduled] = HrEmployeeAttendance::configure($model, $data, $request->user());

            $message = $scheduled
                ? __('validation.hr.attendance_config_scheduled', ['date' => $data['effective_from']])
                : __('validation.hr.saved');

            return success(new EmployeeProfileResource($model->fresh()->load(EmployeeController::PROFILE_RELATIONS)), null, $message);
        });
    }

    public function cancel(Request $request, int $employee, int $config): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($employee, $config) {
            $model = $this->findInCenter(Employee::class, $employee, $centerId);
            HrEmployeeAttendance::cancel($model, $model->attendanceConfigs()->whereKey($config)->firstOrFail());

            return success(null, null, __('validation.hr.change_cancelled'));
        });
    }

    /** Attendance rules that apply to the employee on ?date= (default today). */
    public function rules(Request $request, int $employee): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $employee) {
            $model = $this->findInCenter(Employee::class, $employee, $centerId);
            $date = $request->filled('date') && strtotime((string) $request->get('date')) !== false
                ? Carbon::parse((string) $request->get('date'))
                : today();

            return success(HrAttendanceRules::on($model, $date)->toArray());
        });
    }
}
