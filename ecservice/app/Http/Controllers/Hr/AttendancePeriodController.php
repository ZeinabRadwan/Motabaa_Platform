<?php

namespace App\Http\Controllers\Hr;

use App\Http\Requests\Hr\AttendancePeriodRequest;
use App\Http\Requests\Hr\HrReasonRequest;
use App\Http\Resources\Hr\AttendancePeriodResource;
use App\Http\Resources\Hr\AttendancePeriodSummaryResource;
use App\Http\Resources\Hr\EmployeeReference;
use App\Models\Hr\AttendancePeriod;
use App\Models\Hr\AttendancePeriodSummary;
use App\Models\Hr\Branch;
use App\Support\Hr\HrAdditionalWorkDays;
use App\Support\Hr\HrAttendancePeriods;
use App\Support\Hr\HrBranchScope;
use App\Support\Hr\HrException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Payroll attendance periods (module hr-attendance-processing). Preparing and processing: edit; approving,
 * reopening and deleting: admin (PATCH / DELETE). Center-wide periods need access to every branch; users limited
 * to some branches see and manage the periods of their branches only. Payroll reads approved periods only.
 */
class AttendancePeriodController extends HrController
{
    private const RELATIONS = [
        'branch:id,name_ar,name_en,deleted_at',
        'processor:id,name',
        'approver:id,name',
        'reopener:id,name',
        'creator:id,name',
    ];

    public function index(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $query = AttendancePeriod::query()->forCenter($centerId)
                ->with(self::RELATIONS)
                ->when($request->filled('status'), fn ($query) => $query->where('status', (string) $request->get('status')))
                ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', (int) $request->get('branch_id')))
                ->when($request->filled('year'), fn ($query) => $query->whereYear('period_start', (int) $request->get('year')))
                ->orderByDesc('period_start')
                ->orderByDesc('id');
            $scope = $this->branchScope($request, $centerId);
            if ($scope !== null) {
                $query->whereIn('branch_id', $scope);
            }

            return $this->paginated($request, $query, AttendancePeriodResource::class, ['period_start' => 'period_start', 'status' => 'status']);
        });
    }

    public function show(Request $request, int $period): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $period) {
            return success(new AttendancePeriodResource($this->scoped($request, $centerId, $period)->load(self::RELATIONS)));
        });
    }

    public function store(AttendancePeriodRequest $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $data = $request->validated();
            $this->assertBranch($request, $centerId, $data['branch_id'] ?? null);
            HrAttendancePeriods::assertDates($data['period_start'], $data['period_end']);
            HrAttendancePeriods::assertNoConflict($centerId, $data['period_start'], $data['period_end'], $data['branch_id'] ?? null);

            $period = AttendancePeriod::create($data + [
                'center_id' => $centerId,
                'status' => AttendancePeriod::STATUS_DRAFT,
                'created_by' => $request->user()->id,
            ]);
            HrAdditionalWorkDays::relink($centerId);

            return success(new AttendancePeriodResource($period->load(self::RELATIONS)), null, __('validation.hr.saved'));
        });
    }

    public function update(AttendancePeriodRequest $request, int $period): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $period) {
            $model = $this->scoped($request, $centerId, $period);
            if ($model->isApproved()) {
                throw new HrException('attendance_period_approved', 'error', 409);
            }
            $data = $request->validated();
            $branchId = array_key_exists('branch_id', $data) ? $data['branch_id'] : $model->branch_id;
            $start = $data['period_start'] ?? $model->period_start->toDateString();
            $end = $data['period_end'] ?? $model->period_end->toDateString();
            $this->assertBranch($request, $centerId, $branchId);
            HrAttendancePeriods::assertDates($start, $end);
            HrAttendancePeriods::assertNoConflict($centerId, $start, $end, $branchId !== null ? (int) $branchId : null, $model->id);

            $model->fill($data);
            $moved = $model->isDirty(['period_start', 'period_end', 'branch_id']);
            if ($moved) {
                $model->status = AttendancePeriod::STATUS_DRAFT;
                $model->summaries()->delete();
                $model->employees_count = 0;
                $model->totals = null;
            }
            $model->save();
            if ($moved) {
                HrAdditionalWorkDays::relink($centerId);
            }

            return success(new AttendancePeriodResource($model->load(self::RELATIONS)), null, __('validation.hr.saved'));
        });
    }

    public function destroy(Request $request, int $period): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $period) {
            $model = $this->scoped($request, $centerId, $period);
            $this->assertBranch($request, $centerId, $model->branch_id);
            if ($model->isApproved()) {
                throw new HrException('attendance_period_approved', 'error', 409);
            }
            $model->summaries()->delete();
            $model->delete();
            HrAdditionalWorkDays::relink($centerId);

            return success(null, null, __('validation.hr.deleted'));
        });
    }

    public function process(Request $request, int $period): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $period) {
            $model = $this->scoped($request, $centerId, $period);
            $this->assertBranch($request, $centerId, $model->branch_id);
            $model = HrAttendancePeriods::process($model, $request->user());

            return success(new AttendancePeriodResource($model->load(self::RELATIONS)), null, __('validation.hr.attendance_period_processed'));
        });
    }

    public function approve(Request $request, int $period): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $period) {
            $model = $this->scoped($request, $centerId, $period);
            $this->assertBranch($request, $centerId, $model->branch_id);
            $model = HrAttendancePeriods::approve($model, $request->user());

            return success(new AttendancePeriodResource($model->load(self::RELATIONS)), null, __('validation.hr.attendance_period_approved_message'));
        });
    }

    public function reopen(HrReasonRequest $request, int $period): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $period) {
            $model = $this->scoped($request, $centerId, $period);
            $this->assertBranch($request, $centerId, $model->branch_id);
            $model = HrAttendancePeriods::reopen($model, $request->user(), (string) $request->validated('reason'));

            return success(new AttendancePeriodResource($model->load(self::RELATIONS)), null, __('validation.hr.attendance_period_reopened'));
        });
    }

    public function summaries(Request $request, int $period): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $period) {
            $model = $this->scoped($request, $centerId, $period);

            return $this->paginated($request, $this->summaryQuery($request, $model), AttendancePeriodSummaryResource::class, [
                'absence_days' => 'absence_days', 'late_minutes' => 'late_minutes', 'unresolved_exceptions' => 'unresolved_exceptions',
            ]);
        });
    }

    /**
     * Approved attendance for payroll: per employee absence days, late minutes, exceptions, approved leave and the
     * additional working days this period pays (amount = gross salary / 30 × days, snapshot taken at approval).
     * Only approved periods (their days are locked), so payroll never reads figures that can still change.
     * `additional_work_days` lists every paid additional day, including employees without attendance in the period.
     */
    public function payroll(Request $request, int $period): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $period) {
            $model = $this->scoped($request, $centerId, $period)->load(self::RELATIONS);
            if (! $model->isApproved()) {
                throw new HrException('attendance_period_not_approved', 'error', 409);
            }
            $summaries = $this->summaryQuery($request, $model)->get();
            $additional = HrAdditionalWorkDays::paidBy($model);

            $employees = $summaries->map(function (AttendancePeriodSummary $summary) use ($additional) {
                $records = $additional->get($summary->employee_id, collect());

                return (new AttendancePeriodSummaryResource($summary))->resolve() + [
                    'additional_days' => round((float) $records->sum('days'), 2),
                    'additional_amount' => round($records->sum(fn ($record) => (int) round(((float) $record->amount) * 100)) / 100, 2),
                ];
            })->values();
            $records = $additional->flatten(1)->values();

            return success([
                'period' => (new AttendancePeriodResource($model))->resolve(),
                'employees' => $employees,
                'additional_work_days' => $records->map(fn ($record) => [
                    'id' => $record->id,
                    'employee' => EmployeeReference::make($record->employee),
                    'work_date' => $record->work_date->toDateString(),
                    'days' => $record->days,
                    'day_type' => $record->day_type,
                    'gross_salary' => $record->gross_salary,
                    'daily_rate' => $record->daily_rate,
                    'amount' => $record->amount,
                    'attendance_period_id' => $record->attendance_period_id,
                    'carried_forward' => HrAdditionalWorkDays::isCarriedForward($record),
                    'approved_at' => $record->approved_at,
                ])->values(),
                'additional_totals' => [
                    'records' => $records->count(),
                    'days' => round((float) $records->sum('days'), 2),
                    'amount' => round($records->sum(fn ($record) => (int) round(((float) $record->amount) * 100)) / 100, 2),
                ],
            ]);
        });
    }

    private function summaryQuery(Request $request, AttendancePeriod $period)
    {
        $q = trim((string) $request->get('q', ''));

        return AttendancePeriodSummary::query()
            ->where('attendance_period_id', $period->id)
            ->with(['employee.user:id,name', 'branch:id,name_ar,name_en,deleted_at'])
            ->when($request->boolean('exceptions_only'), fn ($query) => $query->where('exceptions_count', '>', 0))
            ->when($q !== '', fn ($query) => $query->whereHas('employee', fn ($employee) => $employee
                ->where('employee_number', 'like', "%{$q}%")
                ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$q}%"))))
            ->orderBy('employee_id');
    }

    private function scoped(Request $request, int $centerId, int $id): AttendancePeriod
    {
        $model = AttendancePeriod::query()->forCenter($centerId)->findOrFail($id);
        $scope = $this->branchScope($request, $centerId);
        if ($scope !== null && ($model->branch_id === null || ! in_array((int) $model->branch_id, $scope, true))) {
            throw new HrException('not_found', 'error', 404);
        }

        return $model;
    }

    private function assertBranch(Request $request, int $centerId, mixed $branchId): void
    {
        $scope = $this->branchScope($request, $centerId);
        if ($branchId === null) {
            HrBranchScope::assertUnrestricted($scope);

            return;
        }
        if (! Branch::query()->forCenter($centerId)->whereKey((int) $branchId)->exists()) {
            throw new HrException('branch_not_found', 'branch_id');
        }
        HrBranchScope::assertAllowed($scope, $branchId);
    }
}
