<?php

namespace App\Http\Controllers\Hr;

use App\Http\Requests\Hr\AttendanceDayCorrectionRequest;
use App\Http\Requests\Hr\AttendanceProcessRequest;
use App\Http\Requests\Hr\HrReasonRequest;
use App\Http\Resources\Hr\AttendanceDayResource;
use App\Models\Hr\AttendanceDay;
use App\Models\Hr\Employee;
use App\Support\Hr\HrAttendanceCorrections;
use App\Support\Hr\HrAttendanceProcessor;
use App\Support\Hr\HrBranchScope;
use App\Support\Hr\HrException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Processed attendance (module hr-attendance-processing), within the user's branches: daily results, absences,
 * lateness and exceptions; processing on demand and HR corrections (edit_hr-attendance-processing).
 */
class AttendanceDayController extends HrController
{
    private const RELATIONS = [
        'employee.user:id,name',
        'branch:id,name_ar,name_en,timezone,deleted_at',
        'shift:id,code,name_ar,name_en,color,deleted_at',
        'leaveType',
        'holiday',
        'overrider:id,name',
    ];

    private const MAX_PROCESS_DAYS = 62;

    public function index(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $query = $this->filtered($request, $centerId)
                ->with(self::RELATIONS)
                ->orderByDesc('attendance_date')
                ->orderBy('employee_id');

            return $this->paginated($request, $query, AttendanceDayResource::class, [
                'attendance_date' => 'attendance_date', 'status' => 'status', 'late_minutes' => 'late_minutes',
            ]);
        });
    }

    /** Totals of the filtered days: per status, absence days, late / early minutes, open exceptions. */
    public function summary(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $base = $this->filtered($request, $centerId);
            $statuses = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

            return success([
                'statuses' => collect(AttendanceDay::STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($statuses[$status] ?? 0)]),
                'absence_days' => round((float) (clone $base)->sum('absence_days'), 2),
                'late_minutes' => (int) (clone $base)->sum('late_minutes'),
                'early_departure_minutes' => (int) (clone $base)->sum('early_departure_minutes'),
                'unresolved_exceptions' => (clone $base)->where('review_status', AttendanceDay::REVIEW_PENDING)->count(),
            ]);
        });
    }

    public function employees(Request $request): JsonResponse
    {
        return $this->inCenter($request, fn (int $centerId) => $this->employeeOptions($request, $centerId));
    }

    public function show(Request $request, int $day): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $day) {
            $model = $this->scoped($request, $centerId, $day)->load(array_merge(self::RELATIONS, [
                'corrections' => fn ($query) => $query->with('corrector:id,name')->orderByDesc('id'),
            ]));

            return success(new AttendanceDayResource($model));
        });
    }

    public function process(AttendanceProcessRequest $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $data = $request->validated();
            if (Carbon::parse($data['from'])->diffInDays(Carbon::parse($data['to'])) + 1 > self::MAX_PROCESS_DAYS) {
                throw new HrException('attendance_process_range_too_long', 'to', 422, ['days' => self::MAX_PROCESS_DAYS]);
            }

            $query = Employee::query()->forCenter($centerId)
                ->where(fn ($q) => $q->employed()->orWhereDate('terminated_at', '>=', $data['from']))
                ->when(! empty($data['employee_id']), fn ($q) => $q->whereKey((int) $data['employee_id']))
                ->when(! empty($data['branch_id']), fn ($q) => $q->where('branch_id', (int) $data['branch_id']));
            HrBranchScope::restrict($query, $this->branchScope($request, $centerId), 'hr_employees.branch_id');

            $stats = ['processed' => 0, 'locked' => 0];
            $query->orderBy('id')->chunkById(200, function ($employees) use ($centerId, $data, &$stats) {
                $result = HrAttendanceProcessor::process($centerId, $employees, $data['from'], $data['to']);
                $stats['processed'] += $result['processed'];
                $stats['locked'] += $result['locked'];
            });

            return success($stats, null, __('validation.hr.attendance_processed', ['count' => $stats['processed']]));
        });
    }

    public function correct(AttendanceDayCorrectionRequest $request, int $day): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $day) {
            $model = $this->scoped($request, $centerId, $day);
            $service = new HrAttendanceCorrections($centerId, $request->user(), $this->branchScope($request, $centerId));
            $model = $service->correct($model, $request->validated());

            return success(new AttendanceDayResource($model->load(self::RELATIONS)), null, __('validation.hr.attendance_day_corrected'));
        });
    }

    public function reset(HrReasonRequest $request, int $day): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $day) {
            $model = $this->scoped($request, $centerId, $day);
            $service = new HrAttendanceCorrections($centerId, $request->user(), $this->branchScope($request, $centerId));
            $model = $service->reset($model, (string) $request->validated('reason'));

            return success(new AttendanceDayResource($model->load(self::RELATIONS)), null, __('validation.hr.attendance_day_reset'));
        });
    }

    private function filtered(Request $request, int $centerId): Builder
    {
        $q = trim((string) $request->get('q', ''));
        $status = (string) $request->get('status', '');
        $query = AttendanceDay::query()->forCenter($centerId)
            ->when($request->filled('from'), fn ($query) => $query->whereDate('attendance_date', '>=', (string) $request->get('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('attendance_date', '<=', (string) $request->get('to')))
            ->when($request->filled('employee_id'), fn ($query) => $query->where('employee_id', (int) $request->get('employee_id')))
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', (int) $request->get('branch_id')))
            ->when($request->filled('attendance_period_id'), fn ($query) => $query->where('attendance_period_id', (int) $request->get('attendance_period_id')))
            ->when($status === 'exceptions', fn ($query) => $query->whereNotNull('review_status'))
            ->when($status === 'present_any', fn ($query) => $query->whereIn('status', AttendanceDay::PRESENT_STATUSES))
            ->when(in_array($status, AttendanceDay::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when($request->filled('review_status'), fn ($query) => $query->where('review_status', (string) $request->get('review_status')))
            ->when($request->boolean('late_only'), fn ($query) => $query->where('late_minutes', '>', 0))
            ->when($request->boolean('hide_non_working'), fn ($query) => $query->whereNotIn('status', [
                AttendanceDay::STATUS_REST_DAY, AttendanceDay::STATUS_DAY_OFF, AttendanceDay::STATUS_NOT_EMPLOYED, AttendanceDay::STATUS_UNSCHEDULED,
            ]))
            ->when($q !== '', fn ($query) => $query->whereHas('employee', fn ($employee) => $employee
                ->where('employee_number', 'like', "%{$q}%")
                ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$q}%"))));

        $scope = $this->branchScope($request, $centerId);
        if ($scope !== null) {
            $query->whereIn('branch_id', $scope);
        }

        return $query;
    }

    private function scoped(Request $request, int $centerId, int $id): AttendanceDay
    {
        $model = AttendanceDay::query()->forCenter($centerId)->findOrFail($id);
        if (! HrBranchScope::allows($this->branchScope($request, $centerId), $model->branch_id)) {
            throw new HrException('not_found', 'error', 404);
        }

        return $model;
    }
}
