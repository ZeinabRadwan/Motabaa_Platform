<?php

namespace App\Http\Controllers\Hr;

use App\Http\Requests\Hr\AttendancePunchRequest;
use App\Http\Requests\Hr\LocationCheckRequest;
use App\Http\Resources\Hr\AttendanceRecordResource;
use App\Http\Resources\Hr\LocationCheckResource;
use App\Models\Hr\AttendanceRecord;
use App\Models\Hr\Employee;
use App\Support\Hr\HrElectronicAttendance;
use App\Support\Hr\HrException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Electronic attendance of the signed-in user (apply_hr-self-attendance, route middleware). Every action works on
 * the user's own employee profile in the current center only; there is no employee parameter to tamper with.
 * Location is read when the employee presses check-in / check-out and, during an open session, for the periodic
 * checks while the page is open: browsers do not allow reliable background tracking.
 */
class MyAttendanceController extends HrController
{
    public function status(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $employee = HrElectronicAttendance::employeeFor($request->user(), $centerId);
            if ($employee === null) {
                return success(['eligible' => false, 'ineligible_reason' => 'not_employee']);
            }

            return success($this->present((new HrElectronicAttendance($employee))->status(now())));
        });
    }

    public function checkIn(AttendancePunchRequest $request): JsonResponse
    {
        return $this->punch($request, 'checkIn', 'attendance_checked_in');
    }

    public function checkOut(AttendancePunchRequest $request): JsonResponse
    {
        return $this->punch($request, 'checkOut', 'attendance_checked_out');
    }

    /** The user's own records, newest first; optional ?from= / ?to= (Y-m-d). */
    public function history(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $employee = $this->requireEmployee($request, $centerId);
            $query = AttendanceRecord::query()
                ->where('employee_id', $employee->id)
                ->with(['employee.branch', 'shift', 'workLocation'])
                ->orderByDesc('attendance_date');
            foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->get($key))) {
                    $query->whereDate('attendance_date', $operator, $request->get($key));
                }
            }

            return $this->paginated($request, $query, AttendanceRecordResource::class);
        });
    }

    private function punch(AttendancePunchRequest $request, string $action, string $messageKey): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $action, $messageKey) {
            $service = new HrElectronicAttendance($this->requireEmployee($request, $centerId));
            $now = now();
            $result = $service->{$action}($request->punchInput(), $now);
            $record = $result['record'];
            $time = $action === 'checkIn' ? $record->check_in_at : $record->check_out_at;
            $message = __('validation.hr.'.($result['duplicate'] ? 'attendance_duplicate_ignored' : $messageKey), [
                'time' => $time?->copy()->setTimezone(HrElectronicAttendance::timezoneFor($record->employee))->format('H:i'),
            ]);

            return success($this->present($service->status($now)) + [
                'result' => [
                    'action' => $action === 'checkIn' ? 'check_in' : 'check_out',
                    'duplicate' => $result['duplicate'],
                    'verification' => $result['verification'],
                ],
            ], null, $message);
        });
    }

    /**
     * Periodic location check while the attendance page is open (only between check-in and check-out, when a check
     * is due). A retried request with the same client_id returns the stored check.
     */
    public function locationCheck(LocationCheckRequest $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $service = new HrElectronicAttendance($this->requireEmployee($request, $centerId));
            $now = now();
            $result = $service->monitor()->submitWeb($request->reading(), $now);
            $check = (new LocationCheckResource($result['check']->load(['employee.branch', 'employee.user', 'record', 'workLocation'])))->resolve();
            $message = __('validation.hr.'.($result['duplicate'] ? 'location_check_duplicate' : 'location_check_recorded'), ['time' => $check['time']]);

            return success($this->present($service->status($now)) + ['check' => $check, 'duplicate' => $result['duplicate']], null, $message);
        });
    }

    protected function requireEmployee(Request $request, int $centerId): Employee
    {
        return HrElectronicAttendance::employeeFor($request->user(), $centerId)
            ?? throw new HrException('attendance_ineligible_not_employee', 'error', 403);
    }

    protected function present(array $status): array
    {
        $record = $status['record'];
        if ($record !== null) {
            $record->loadMissing(['employee.branch', 'shift', 'workLocation']);
        }
        $status['record'] = $record ? (new AttendanceRecordResource($record))->resolve() : null;

        return $status;
    }
}
