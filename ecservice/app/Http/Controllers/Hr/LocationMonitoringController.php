<?php

namespace App\Http\Controllers\Hr;

use App\Http\Requests\Hr\LocationExceptionReviewRequest;
use App\Http\Resources\Hr\AttendanceDeviceResource;
use App\Http\Resources\Hr\LocationCheckResource;
use App\Http\Resources\Hr\LocationExceptionResource;
use App\Http\Resources\Hr\LocationSessionResource;
use App\Models\Hr\AttendanceDevice;
use App\Models\Hr\AttendanceRecord;
use App\Models\Hr\Employee;
use App\Models\Hr\LocationCheck;
use App\Models\Hr\LocationException;
use App\Support\Hr\HrAttendanceDevices;
use App\Support\Hr\HrBranchScope;
use App\Support\Hr\HrLocationMonitor;
use App\Support\Hr\HrLocationSchedule;
use App\Support\Hr\HrSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HR location monitoring dashboard (module hr-location-monitoring): attendance sessions with their latest location
 * and verification status, the location history, outside-radius exceptions (review: edit_) and mobile devices
 * (approve / revoke: edit_). Branch-scoped through the employee's branch. Only data recorded during attendance
 * sessions exists; nothing is tracked outside them.
 */
class LocationMonitoringController extends HrController
{
    private const MAX_SESSIONS = 500;

    /** Sessions of ?date= (default today; today also lists every open session), with ?branch_id, ?employee_id, ?status. */
    public function sessions(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $now = CarbonImmutable::now();
            HrLocationMonitor::catchUpAll($now, $centerId);

            $timezone = (string) (HrSettings::get($centerId, 'general.timezone') ?: config('app.timezone'));
            $today = $now->setTimezone($timezone)->toDateString();
            $date = $this->date($request->get('date')) ?? $today;

            $base = $this->scoped($request, AttendanceRecord::query()->forCenter($centerId), $centerId)
                ->where('attendance_method', AttendanceRecord::METHOD_ELECTRONIC)
                ->where(fn (Builder $query) => $query
                    ->whereDate('attendance_date', $date)
                    ->when($date === $today, fn (Builder $open) => $open->orWhere('status', AttendanceRecord::STATUS_CHECKED_IN)));

            $summary = $this->summary(clone $base, $centerId, $request);

            $status = (string) $request->get('status', '');
            $sessions = (clone $base)
                ->when($status === 'not_monitored', fn (Builder $query) => $query->whereNull('location_check_interval_minutes'))
                ->when(in_array($status, LocationCheck::STATUSES, true), fn (Builder $query) => $query
                    ->whereNotNull('location_check_interval_minutes')
                    ->where('location_status', $status))
                ->with(['employee.user', 'employee.branch', 'workLocation', 'lastLocationCheck'])
                ->withCount([
                    'locationChecks',
                    'locationChecks as missed_checks_count' => fn (Builder $query) => $query
                        ->where('status', LocationCheck::STATUS_PENDING)
                        ->where('due_at', '<', $now->subSeconds(HrLocationSchedule::OVERDUE_AFTER_SECONDS)),
                    'locationExceptions as open_exceptions_count' => fn (Builder $query) => $query->where('status', LocationException::STATUS_OPEN),
                ])
                ->withMax(['locationChecks as latest_pending_due_at' => fn (Builder $query) => $query->where('status', LocationCheck::STATUS_PENDING)], 'due_at')
                ->orderByRaw("CASE WHEN status = 'checked_in' THEN 0 ELSE 1 END")
                ->orderByDesc('check_in_at')
                ->limit(self::MAX_SESSIONS)
                ->get();

            return success([
                'date' => $date,
                'today' => $today,
                'server_time' => $now->setTimezone($timezone)->toIso8601String(),
                'summary' => $summary,
                'sessions' => LocationSessionResource::collection($sessions)->resolve(),
            ]);
        });
    }

    /** Filter options: employees in the user's branches that have electronic attendance records (?branch_id). */
    public function employees(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $query = Employee::query()->forCenter($centerId)->with('user')
                ->whereHas('attendanceRecords', fn (Builder $records) => $records->where('attendance_method', AttendanceRecord::METHOD_ELECTRONIC));
            HrBranchScope::restrict($query, $this->branchScope($request, $centerId), 'hr_employees.branch_id');
            if ((int) $request->get('branch_id') > 0) {
                $query->where('hr_employees.branch_id', (int) $request->get('branch_id'));
            }

            return success($query->orderBy('employee_number')->limit(1000)->get()->map(fn (Employee $employee) => [
                'value' => $employee->id,
                'title' => trim(($employee->user?->name ?? '').' ('.$employee->employee_number.')'),
                'branch_id' => $employee->branch_id,
            ])->values());
        });
    }

    /** Location history: ?from / ?to (Y-m-d), ?branch_id, ?employee_id, ?status, ?source, ?kind, ?attendance_record_id. */
    public function checks(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $query = $this->scoped($request, LocationCheck::query()->forCenter($centerId), $centerId)
                ->with(['employee.user', 'employee.branch', 'record', 'workLocation', 'device'])
                ->when(in_array($request->get('status'), LocationCheck::STATUSES, true), fn (Builder $q) => $q->where('status', $request->get('status')))
                ->when(in_array($request->get('source'), LocationCheck::SOURCES, true), fn (Builder $q) => $q->where('source', $request->get('source')))
                ->when(in_array($request->get('kind'), [LocationCheck::KIND_CHECK_IN, LocationCheck::KIND_PERIODIC, LocationCheck::KIND_CHECK_OUT], true), fn (Builder $q) => $q->where('kind', $request->get('kind')))
                ->when((int) $request->get('attendance_record_id') > 0, fn (Builder $q) => $q->where('attendance_record_id', (int) $request->get('attendance_record_id')));
            foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
                $date = $this->date($request->get($key));
                if ($date !== null) {
                    $query->whereRaw("DATE(COALESCE(checked_at, due_at)) {$operator} ?", [$date]);
                }
            }
            $query->orderByRaw('COALESCE(checked_at, due_at) DESC')->orderByDesc('id');

            return $this->paginated($request, $query, LocationCheckResource::class);
        });
    }

    /** Outside-radius exceptions: ?from / ?to, ?branch_id, ?employee_id, ?review_status, ?status. */
    public function exceptions(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $query = $this->scoped($request, LocationException::query()->forCenter($centerId), $centerId)
                ->with(['employee.user', 'employee.branch', 'workLocation', 'lastCheck', 'reviewer'])
                ->when(in_array($request->get('review_status'), [LocationException::REVIEW_PENDING, LocationException::REVIEW_ACKNOWLEDGED, LocationException::REVIEW_DISMISSED], true), fn (Builder $q) => $q->where('review_status', $request->get('review_status')))
                ->when(in_array($request->get('status'), [LocationException::STATUS_OPEN, LocationException::STATUS_CLOSED], true), fn (Builder $q) => $q->where('status', $request->get('status')));
            foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
                $date = $this->date($request->get($key));
                if ($date !== null) {
                    $query->whereDate('started_at', $operator, $date);
                }
            }
            $query->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")->orderByDesc('started_at');

            return $this->paginated($request, $query, LocationExceptionResource::class);
        });
    }

    public function reviewException(LocationExceptionReviewRequest $request, int $exception): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $exception) {
            $model = $this->scoped($request, LocationException::query()->forCenter($centerId), $centerId)->whereKey($exception)->firstOrFail();
            $model->fill([
                'review_status' => $request->validated('review_status'),
                'review_notes' => $request->validated('notes'),
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ])->save();
            $model->load(['employee.user', 'employee.branch', 'workLocation', 'lastCheck', 'reviewer']);

            return success((new LocationExceptionResource($model))->resolve(), null, __('validation.hr.location_exception_reviewed'));
        });
    }

    /** Registered mobile devices: ?status, ?employee_id, ?branch_id. */
    public function devices(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $query = $this->scoped($request, AttendanceDevice::query()->forCenter($centerId), $centerId)
                ->with('employee.user')
                ->when(in_array($request->get('status'), [AttendanceDevice::STATUS_PENDING, AttendanceDevice::STATUS_ACTIVE, AttendanceDevice::STATUS_REVOKED], true), fn (Builder $q) => $q->where('status', $request->get('status')))
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 WHEN status = 'active' THEN 1 ELSE 2 END")
                ->orderByDesc('created_at');

            return $this->paginated($request, $query, AttendanceDeviceResource::class);
        });
    }

    public function approveDevice(Request $request, int $device): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $device) {
            $model = $this->scoped($request, AttendanceDevice::query()->forCenter($centerId), $centerId)->whereKey($device)->firstOrFail();
            $model = HrAttendanceDevices::approve($model, $request->user(), now())->load('employee.user');

            return success((new AttendanceDeviceResource($model))->resolve(), null, __('validation.hr.device_approved'));
        });
    }

    public function revokeDevice(Request $request, int $device): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $device) {
            $model = $this->scoped($request, AttendanceDevice::query()->forCenter($centerId), $centerId)->whereKey($device)->firstOrFail();
            $model = HrAttendanceDevices::revoke($model, $request->user(), AttendanceDevice::REVOKED_BY_HR, now())->load('employee.user');

            return success((new AttendanceDeviceResource($model))->resolve(), null, __('validation.hr.device_revoked_by_hr'));
        });
    }

    /** Limits a query on a table with employee_id to the user's branches and the ?branch_id / ?employee_id filters. */
    private function scoped(Request $request, Builder $query, int $centerId): Builder
    {
        $scope = $this->branchScope($request, $centerId);
        $branchId = (int) $request->get('branch_id');

        return $query
            ->whereHas('employee', function (Builder $employee) use ($scope, $branchId) {
                HrBranchScope::restrict($employee, $scope, $employee->qualifyColumn('branch_id'));
                if ($branchId > 0) {
                    $employee->where($employee->qualifyColumn('branch_id'), $branchId);
                }
            })
            ->when((int) $request->get('employee_id') > 0, fn (Builder $q) => $q->where($q->qualifyColumn('employee_id'), (int) $request->get('employee_id')));
    }

    /** Counts for the dashboard header: sessions, open sessions per verification status, pending alerts. */
    private function summary(Builder $sessions, int $centerId, Request $request): array
    {
        $open = (clone $sessions)->where('status', AttendanceRecord::STATUS_CHECKED_IN);
        $byStatus = (clone $open)
            ->whereNotNull('location_check_interval_minutes')
            ->selectRaw('location_status, COUNT(*) as total')
            ->groupBy('location_status')
            ->pluck('total', 'location_status');

        return [
            'sessions' => (clone $sessions)->count(),
            'open_sessions' => (clone $open)->count(),
            'monitored_open_sessions' => (int) $byStatus->sum(),
            'not_monitored_open_sessions' => (clone $open)->whereNull('location_check_interval_minutes')->count(),
            'by_status' => collect(LocationCheck::STATUSES)->mapWithKeys(fn (string $status) => [$status => (int) ($byStatus[$status] ?? 0)])->all(),
            'pending_alerts' => $this->scoped($request, LocationException::query()->forCenter($centerId), $centerId)
                ->where('review_status', LocationException::REVIEW_PENDING)
                ->count(),
            'open_alerts' => $this->scoped($request, LocationException::query()->forCenter($centerId), $centerId)
                ->where('status', LocationException::STATUS_OPEN)
                ->count(),
        ];
    }

    private function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }
}
