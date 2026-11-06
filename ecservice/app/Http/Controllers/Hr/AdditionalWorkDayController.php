<?php

namespace App\Http\Controllers\Hr;

use App\Http\Requests\Hr\AdditionalWorkDayRequest;
use App\Http\Requests\Hr\AdditionalWorkDaySubmitRequest;
use App\Http\Requests\Hr\HrReasonRequest;
use App\Http\Resources\Hr\AdditionalWorkDayResource;
use App\Http\Resources\Hr\AuditLogResource;
use App\Models\Hr\AdditionalWorkDay;
use App\Models\Hr\AttendancePeriod;
use App\Models\Hr\AuditLog;
use App\Models\Hr\Employee;
use App\Support\Hr\HrAdditionalWorkDays;
use App\Support\Hr\HrBranchScope;
use App\Support\Hr\HrException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Additional working days (module hr-additional-days), within the user's branches:
 *  - viewing, history and summary: any hr-additional-days permission;
 *  - adding, editing draft / pending records, submitting and cancelling unapproved records: edit (or admin);
 *  - cancelling approved records not paid yet: admin.
 * Decisions go through the approval inbox (workflow, else direct manager then apply_hr-additional-days).
 * Nobody adds, edits, submits or cancels their own additional days.
 */
class AdditionalWorkDayController extends HrController
{
    public const RELATIONS = [
        'employee.user:id,name',
        'employee.salaries',
        'branch:id,name_ar,name_en,deleted_at',
        'attendancePeriod:id,name,status,period_start,period_end',
        'paidPeriod:id,name,status,period_start,period_end',
        'creator:id,name',
        'submitter:id,name',
        'approver:id,name',
        'rejecter:id,name',
        'canceller:id,name',
        'approvalRequest.steps.actor:id,name',
    ];

    private const EDIT_PERMISSIONS = ['edit_hr-additional-days', 'admin_hr-additional-days'];
    private const ADMIN_PERMISSION = 'admin_hr-additional-days';

    public function index(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $query = $this->filtered($request, $centerId)
                ->with(self::RELATIONS)
                ->orderByDesc('work_date')
                ->orderByDesc('id');

            return $this->paginated($request, $query, AdditionalWorkDayResource::class, [
                'work_date' => 'work_date', 'days' => 'days', 'status' => 'status', 'created_at' => 'created_at',
            ]);
        });
    }

    /** Counts and days per status (pending, approved and unpaid, rejected, paid with amounts) for the same filters. */
    public function summary(Request $request): JsonResponse
    {
        return $this->inCenter($request, fn (int $centerId) => success(HrAdditionalWorkDays::summary($this->filtered($request, $centerId))));
    }

    public function employees(Request $request): JsonResponse
    {
        return $this->inCenter($request, fn (int $centerId) => $this->employeeOptions($request, $centerId));
    }

    /** Payroll periods for the filter (center-wide ones and those of the user's branches), newest first. */
    public function periods(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $scope = $this->branchScope($request, $centerId);
            $periods = AttendancePeriod::query()->forCenter($centerId)
                ->when($scope !== null, fn ($query) => $query->where(fn ($inner) => $inner->whereNull('branch_id')->orWhereIn('branch_id', $scope)))
                ->orderByDesc('period_start')
                ->limit(60)
                ->get(['id', 'name', 'branch_id', 'period_start', 'period_end', 'status']);

            return success($periods->map(fn (AttendancePeriod $period) => [
                'value' => $period->id,
                'title' => $period->name.' ('.$period->period_start->toDateString().' - '.$period->period_end->toDateString().')',
                'status' => $period->status,
                'period_start' => $period->period_start->toDateString(),
                'period_end' => $period->period_end->toDateString(),
            ])->values());
        });
    }

    public function show(Request $request, int $record): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $record) {
            return success(new AdditionalWorkDayResource($this->scoped($request, $centerId, $record)->load(self::RELATIONS)));
        });
    }

    /** Audit trail of the record (every change with old / new values) and its approval steps. */
    public function history(Request $request, int $record): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $record) {
            $model = $this->scoped($request, $centerId, $record)->load(self::RELATIONS);
            $logs = AuditLog::query()
                ->where('center_id', $centerId)
                ->where('auditable_type', $model->getMorphClass())
                ->where('auditable_id', $model->id)
                ->with('user:id,name')
                ->orderBy('id')
                ->get();

            return success([
                'record' => (new AdditionalWorkDayResource($model))->resolve(),
                'audit' => AuditLogResource::collection($logs)->resolve(),
            ]);
        });
    }

    public function store(AdditionalWorkDayRequest $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            if (! canAny(self::EDIT_PERMISSIONS)) {
                return $this->denied();
            }
            $ids = array_values(array_unique(array_map('intval', $request->validated('employee_ids'))));
            $employees = collect($ids)->map(fn (int $id) => $this->findInCenter(Employee::class, $id, $centerId));
            $submit = $request->boolean('submit');

            $records = HrAdditionalWorkDays::create($employees, $request->validated(), $request->user(), $submit);
            $records = AdditionalWorkDay::query()->whereIn('id', $records->pluck('id'))->with(self::RELATIONS)->orderBy('id')->get();

            return success(
                AdditionalWorkDayResource::collection($records)->resolve(),
                null,
                __('validation.hr.'.($submit ? 'additional_day_submitted' : 'additional_day_saved'), ['count' => $records->count()]),
            );
        });
    }

    public function update(AdditionalWorkDayRequest $request, int $record): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $record) {
            $model = $this->scoped($request, $centerId, $record);
            if (! canAny(self::EDIT_PERMISSIONS)) {
                return $this->denied();
            }
            $model = HrAdditionalWorkDays::update($model, $request->validated(), $request->user());

            return success(new AdditionalWorkDayResource($model->fresh()->load(self::RELATIONS)), null, __('validation.hr.saved'));
        });
    }

    public function submit(Request $request, int $record): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $record) {
            $model = $this->scoped($request, $centerId, $record);
            if (! canAny(self::EDIT_PERMISSIONS)) {
                return $this->denied();
            }
            $model = HrAdditionalWorkDays::submit($model, $request->user());

            return success(new AdditionalWorkDayResource($model->fresh()->load(self::RELATIONS)), null, __('validation.hr.additional_day_submitted', ['count' => 1]));
        });
    }

    /** Submits several drafts at once (all or nothing). */
    public function submitMany(AdditionalWorkDaySubmitRequest $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            if (! canAny(self::EDIT_PERMISSIONS)) {
                return $this->denied();
            }
            $ids = array_values(array_unique(array_map('intval', $request->validated('ids'))));
            $models = collect($ids)->map(fn (int $id) => $this->scoped($request, $centerId, $id));

            DB::transaction(fn () => $models->each(fn (AdditionalWorkDay $model) => HrAdditionalWorkDays::submit($model, $request->user())));

            $records = AdditionalWorkDay::query()->whereIn('id', $ids)->with(self::RELATIONS)->orderBy('id')->get();

            return success(
                AdditionalWorkDayResource::collection($records)->resolve(),
                null,
                __('validation.hr.additional_day_submitted', ['count' => $records->count()]),
            );
        });
    }

    public function cancel(HrReasonRequest $request, int $record): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $record) {
            $model = $this->scoped($request, $centerId, $record);
            if (! canAny(self::EDIT_PERMISSIONS)) {
                return $this->denied();
            }
            if ((int) $model->employee?->user_id === (int) $request->user()->id) {
                throw new HrException('additional_day_own_record', 'error', 403);
            }
            $model = HrAdditionalWorkDays::cancel($model, $request->user(), (string) $request->validated('reason'), can(self::ADMIN_PERMISSION));

            return success(new AdditionalWorkDayResource($model->fresh()->load(self::RELATIONS)), null, __('validation.hr.additional_day_cancelled'));
        });
    }

    /**
     * Filters: employee_id, branch_id (branch on the work date), from / to (work date), attendance_period_id (the
     * period of the work date or the period that paid it), status, q (employee name / number or reason).
     *
     * @return Builder<AdditionalWorkDay>
     */
    private function filtered(Request $request, int $centerId): Builder
    {
        $q = trim((string) $request->get('q', ''));
        $query = AdditionalWorkDay::query()
            ->forCenter($centerId)
            ->when($request->filled('employee_id'), fn ($query) => $query->where('employee_id', (int) $request->get('employee_id')))
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', (int) $request->get('branch_id')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('work_date', '>=', (string) $request->get('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('work_date', '<=', (string) $request->get('to')))
            ->when($request->filled('attendance_period_id'), fn ($query) => $query->where(fn ($inner) => $inner
                ->where('attendance_period_id', (int) $request->get('attendance_period_id'))
                ->orWhere('paid_period_id', (int) $request->get('attendance_period_id'))))
            ->when($request->filled('status'), fn ($query) => $query->whereIn('status', array_filter((array) $request->get('status'))))
            ->when($q !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('reason', 'like', "%{$q}%")
                ->orWhereHas('employee', fn ($employee) => $employee->where('employee_number', 'like', "%{$q}%")
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$q}%")))));

        $scope = $this->branchScope($request, $centerId);
        if ($scope !== null) {
            $query->where(fn (Builder $inner) => $inner
                ->whereIn('branch_id', $scope)
                ->orWhereHas('employee', fn (Builder $employee) => $employee->whereIn('branch_id', $scope)));
        }

        return $query;
    }

    private function scoped(Request $request, int $centerId, int $id): AdditionalWorkDay
    {
        $model = AdditionalWorkDay::query()->forCenter($centerId)->with('employee')->findOrFail($id);
        $scope = $this->branchScope($request, $centerId);
        if (! HrBranchScope::allows($scope, $model->branch_id) && ! HrBranchScope::allows($scope, $model->employee?->branch_id)) {
            throw new HrException('not_found', 'error', 404);
        }

        return $model;
    }
}
