<?php

namespace App\Http\Controllers\Hr;

use App\Http\Requests\Hr\HrReasonRequest;
use App\Http\Requests\Hr\LeaveRequestRequest;
use App\Http\Resources\Hr\EmployeeReference;
use App\Http\Resources\Hr\LeaveBalanceResource;
use App\Http\Resources\Hr\LeaveRequestResource;
use App\Http\Resources\Hr\LeaveTypeResource;
use App\Models\Hr\Employee;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\LeaveType;
use App\Support\Hr\HrElectronicAttendance;
use App\Support\Hr\HrException;
use App\Support\Hr\HrLeaveBalances;
use App\Support\Hr\HrLeaveRequests;
use App\Support\Hr\HrLeaveTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Leave of the signed-in user (apply_hr-self-leave, route middleware): own employee profile only. Employees
 * request leave, follow its approval, cancel pending requests and see their balances and leave history.
 */
class MyLeaveController extends HrController
{
    public function overview(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $employee = HrElectronicAttendance::employeeFor($request->user(), $centerId);
            if ($employee === null) {
                return success(['eligible' => false]);
            }
            HrLeaveTypes::ensureDefaults($centerId);
            $year = $this->year($request);

            return success([
                'eligible' => true,
                'employee' => EmployeeReference::make($employee),
                'year' => $year,
                'balances' => LeaveBalanceResource::collection(HrLeaveBalances::overview($employee, $year)),
                'leave_types' => LeaveTypeResource::collection(LeaveType::query()->forCenter($centerId)
                    ->where('is_active', true)
                    ->where('allow_self_request', true)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()),
                'pending_count' => LeaveRequest::query()->where('employee_id', $employee->id)->where('status', LeaveRequest::STATUS_PENDING)->count(),
            ]);
        });
    }

    public function requests(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $employee = $this->requireEmployee($request, $centerId);
            $query = LeaveRequestController::filtered(LeaveRequest::query()->where('employee_id', $employee->id), $request)
                ->with(LeaveRequestController::RELATIONS)
                ->orderByDesc('start_date')
                ->orderByDesc('id');

            return $this->paginated($request, $query, LeaveRequestResource::class, ['start_date' => 'start_date', 'status' => 'status']);
        });
    }

    public function show(Request $request, int $leaveRequest): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $leaveRequest) {
            return success(new LeaveRequestResource($this->own($request, $centerId, $leaveRequest)->load(LeaveRequestController::RELATIONS)));
        });
    }

    public function preview(LeaveRequestRequest $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $employee = $this->requireEmployee($request, $centerId);

            return success(HrLeaveRequests::preview($employee, $request->validated(), true));
        });
    }

    public function store(LeaveRequestRequest $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $employee = $this->requireEmployee($request, $centerId);
            if (! $employee->isEmployed()) {
                throw new HrException('leave_not_employed', 'error', 403);
            }
            $leave = HrLeaveRequests::submit(
                $employee,
                $request->validated(),
                $request->user(),
                LeaveRequest::SOURCE_SELF,
                array_values((array) $request->file('attachments', [])),
            );

            return success(new LeaveRequestResource($leave->fresh()->load(LeaveRequestController::RELATIONS)), null, __('validation.hr.leave_submitted'));
        });
    }

    public function cancel(HrReasonRequest $request, int $leaveRequest): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $leaveRequest) {
            $model = $this->own($request, $centerId, $leaveRequest);
            if (! $model->isPending()) {
                throw new HrException('leave_not_cancellable', 'error', 409);
            }
            $leave = HrLeaveRequests::cancel($model, $request->user(), (string) $request->validated('reason'), false);

            return success(new LeaveRequestResource($leave->fresh()->load(LeaveRequestController::RELATIONS)), null, __('validation.hr.leave_cancelled'));
        });
    }

    private function requireEmployee(Request $request, int $centerId): Employee
    {
        $employee = HrElectronicAttendance::employeeFor($request->user(), $centerId)
            ?? throw new HrException('leave_not_employee', 'error', 403);

        return $employee->load('employmentPeriods');
    }

    private function own(Request $request, int $centerId, int $id): LeaveRequest
    {
        $employee = $this->requireEmployee($request, $centerId);

        return LeaveRequest::query()->forCenter($centerId)->where('employee_id', $employee->id)->findOrFail($id);
    }

    private function year(Request $request): int
    {
        $year = (int) $request->get('year', now()->year);

        return $year >= 2000 && $year <= 2100 ? $year : now()->year;
    }
}
