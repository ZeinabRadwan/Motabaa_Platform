<?php

namespace App\Http\Controllers\Hr;

use App\Http\Requests\Hr\HrReasonRequest;
use App\Http\Requests\Hr\LeaveRequestRequest;
use App\Http\Resources\Hr\LeaveRequestResource;
use App\Models\Hr\Employee;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\LeaveRequestAttachment;
use App\Support\Hr\Approvals\ApprovalService;
use App\Support\Hr\HrBranchScope;
use App\Support\Hr\HrException;
use App\Support\Hr\HrLeaveRequests;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Leave requests managed by HR (module hr-leave), within the user's branches:
 *  - viewing: any hr-leave permission;
 *  - requesting on behalf of an employee and cancelling pending requests: edit_hr-leave (or admin);
 *  - recording leave as already approved and cancelling approved leave: admin_hr-leave.
 * Decisions go through the approval inbox (direct manager, then apply_hr-leave when the type requires HR).
 */
class LeaveRequestController extends HrController
{
    public const RELATIONS = [
        'employee.user:id,name',
        'leaveType',
        'requester:id,name',
        'decider:id,name',
        'canceller:id,name',
        'attachments',
        'approvalRequest.steps.actor:id,name',
    ];

    private const EDIT_PERMISSIONS = ['edit_hr-leave', 'admin_hr-leave'];
    private const ADMIN_PERMISSION = 'admin_hr-leave';

    public function index(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $query = self::filtered(LeaveRequest::query()->forCenter($centerId), $request)
                ->with(self::RELATIONS)
                ->orderByDesc('start_date')
                ->orderByDesc('id');
            $scope = $this->branchScope($request, $centerId);
            if ($scope !== null) {
                $query->whereHas('employee', fn (Builder $employee) => $employee->whereIn('branch_id', $scope));
            }

            return $this->paginated($request, $query, LeaveRequestResource::class, [
                'start_date' => 'start_date', 'days' => 'days', 'status' => 'status',
            ]);
        });
    }

    public function employees(Request $request): JsonResponse
    {
        return $this->inCenter($request, fn (int $centerId) => $this->employeeOptions($request, $centerId));
    }

    public function show(Request $request, int $leaveRequest): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $leaveRequest) {
            return success(new LeaveRequestResource($this->scoped($request, $centerId, $leaveRequest)->load(self::RELATIONS)));
        });
    }

    public function preview(LeaveRequestRequest $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $employee = $this->findInCenter(Employee::class, (int) $request->validated('employee_id'), $centerId, true);

            return success(HrLeaveRequests::preview($employee->load(['user', 'employmentPeriods']), $request->validated(), false));
        });
    }

    public function store(LeaveRequestRequest $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            if (! canAny(self::EDIT_PERMISSIONS)) {
                return $this->denied();
            }
            $recorded = $request->boolean('record_as_approved');
            if ($recorded && ! can(self::ADMIN_PERMISSION)) {
                return $this->denied();
            }

            $employee = $this->findInCenter(Employee::class, (int) $request->validated('employee_id'), $centerId);
            $own = (int) $employee->user_id === (int) $request->user()->id;
            if ($recorded && $own) {
                throw new HrException('leave_own_record', 'employee_id', 403);
            }

            $source = match (true) {
                $recorded => LeaveRequest::SOURCE_RECORDED,
                $own => LeaveRequest::SOURCE_SELF,
                default => LeaveRequest::SOURCE_HR,
            };
            $leave = HrLeaveRequests::submit(
                $employee->load(['user', 'employmentPeriods']),
                $request->validated(),
                $request->user(),
                $source,
                array_values((array) $request->file('attachments', [])),
            );

            return success(new LeaveRequestResource($leave->fresh()->load(self::RELATIONS)), null, __('validation.hr.'.($recorded ? 'leave_recorded' : 'leave_submitted')));
        });
    }

    public function cancel(HrReasonRequest $request, int $leaveRequest): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $leaveRequest) {
            $model = $this->scoped($request, $centerId, $leaveRequest);
            if (! canAny(self::EDIT_PERMISSIONS)) {
                return $this->denied();
            }
            $leave = HrLeaveRequests::cancel($model, $request->user(), (string) $request->validated('reason'), can(self::ADMIN_PERMISSION));

            return success(new LeaveRequestResource($leave->fresh()->load(self::RELATIONS)), null, __('validation.hr.leave_cancelled'));
        });
    }

    /**
     * Supporting document download (no module middleware): HR users of the employee's branch, the employee, the
     * requester and the approvers of the request.
     */
    public function attachment(Request $request, int $leaveRequest, int $attachment): Response
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $leaveRequest, $attachment) {
            $leave = LeaveRequest::query()->forCenter($centerId)->with(['employee', 'approvalRequest.steps', 'approvalRequest.employee'])->findOrFail($leaveRequest);
            $file = LeaveRequestAttachment::query()->where('leave_request_id', $leave->id)->findOrFail($attachment);
            if (! $this->canDownload($request, $centerId, $leave)) {
                return $this->denied();
            }

            return HrLeaveRequests::download($file);
        });
    }

    public static function filtered(Builder $query, Request $request): Builder
    {
        $q = trim((string) $request->get('q', ''));

        return $query
            ->when($request->filled('status'), fn ($query) => $query->where('status', (string) $request->get('status')))
            ->when($request->filled('leave_type_id'), fn ($query) => $query->where('leave_type_id', (int) $request->get('leave_type_id')))
            ->when($request->filled('employee_id'), fn ($query) => $query->where('employee_id', (int) $request->get('employee_id')))
            ->when($request->filled('year'), fn ($query) => $query->where('balance_year', (int) $request->get('year')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('end_date', '>=', (string) $request->get('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('start_date', '<=', (string) $request->get('to')))
            ->when($request->filled('branch_id'), fn ($query) => $query->whereHas('employee', fn ($employee) => $employee->where('branch_id', (int) $request->get('branch_id'))))
            ->when($q !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('reason', 'like', "%{$q}%")
                ->orWhereHas('employee', fn ($employee) => $employee->where('employee_number', 'like', "%{$q}%")
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$q}%")))));
    }

    private function scoped(Request $request, int $centerId, int $id): LeaveRequest
    {
        $model = LeaveRequest::query()->forCenter($centerId)->with('employee')->findOrFail($id);
        if (! HrBranchScope::allows($this->branchScope($request, $centerId), $model->employee?->branch_id)) {
            throw new HrException('not_found', 'error', 404);
        }

        return $model;
    }

    private function canDownload(Request $request, int $centerId, LeaveRequest $leave): bool
    {
        $user = $request->user();
        if ((int) $leave->employee?->user_id === (int) $user->id || (int) $leave->requested_by === (int) $user->id) {
            return true;
        }
        if (canAny(['access_hr-leave', 'edit_hr-leave', 'admin_hr-leave', 'apply_hr-leave'])
            && HrBranchScope::allows($this->branchScope($request, $centerId), $leave->employee?->branch_id)) {
            return true;
        }

        $approval = $leave->approvalRequest;

        return $approval !== null && (ApprovalService::canAct($approval, $user)
            || $approval->steps->contains(fn ($step) => (int) $step->acted_by === (int) $user->id));
    }
}
