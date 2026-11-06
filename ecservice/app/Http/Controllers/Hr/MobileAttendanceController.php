<?php

namespace App\Http\Controllers\Hr;

use App\Http\Requests\Hr\MobileDeviceRequest;
use App\Http\Requests\Hr\MobileLocationBatchRequest;
use App\Http\Resources\Hr\AttendanceDeviceResource;
use App\Models\Hr\AttendanceDevice;
use App\Support\Hr\HrAttendanceDevices;
use App\Support\Hr\HrElectronicAttendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Integration foundation for the future mobile application (no mobile application is part of this module).
 *
 * Every endpoint needs the employee's Sanctum session and apply_hr-self-attendance (route middleware) and works on
 * the signed-in user's own employee profile only. Location endpoints additionally need the registered device's
 * credentials (X-Device-Id, X-Device-Token). Samples use the same work locations, geofence, sessions and location
 * records as the website; they are accepted only inside an attendance session.
 */
class MobileAttendanceController extends MyAttendanceController
{
    /** Registers (or re-registers) the installation; the device secret is returned only in this response. */
    public function register(MobileDeviceRequest $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $employee = $this->requireEmployee($request, $centerId);
            $result = HrAttendanceDevices::register($employee, $request->user(), $request->validated(), $request->ip(), now());
            $device = $result['device'];
            $message = __('validation.hr.'.($device->isActive() ? 'device_registered_active' : 'device_registered_pending'));

            return success([
                'device' => (new AttendanceDeviceResource($device))->resolve(),
                'device_token' => $result['token'],
                'headers' => [HrAttendanceDevices::DEVICE_ID_HEADER, HrAttendanceDevices::DEVICE_TOKEN_HEADER],
            ], null, $message);
        });
    }

    /** Device status plus what the application needs: server time, session, work location geofence, schedule. */
    public function device(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $employee = $this->requireEmployee($request, $centerId);
            $device = HrAttendanceDevices::authenticate($request, $employee, true);
            $status = $this->present((new HrElectronicAttendance($employee))->status(now()));

            return success([
                'device' => (new AttendanceDeviceResource($device))->resolve(),
                'session' => [
                    'server_time' => $status['server_time'],
                    'timezone' => $status['timezone'],
                    'eligible' => $status['eligible'],
                    'ineligible_reason' => $status['ineligible_reason'],
                    'attendance_mode' => $status['attendance_mode'] ?? null,
                    'gps_requirement' => $status['gps_requirement'] ?? null,
                    'max_accuracy_meters' => $status['max_accuracy_meters'] ?? null,
                    'work_location' => $status['work_location'] ?? null,
                    'record' => $status['record'] ?? null,
                    'location_monitoring' => $status['location_monitoring'] ?? null,
                ],
            ]);
        });
    }

    /** A batch of queued location samples from an active device. */
    public function locations(MobileLocationBatchRequest $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $employee = $this->requireEmployee($request, $centerId);
            $device = HrAttendanceDevices::authenticate($request, $employee);
            $service = new HrElectronicAttendance($employee);
            $now = now();
            $results = $service->monitor()->submitMobile($request->readings($device->id), $now);

            $accepted = collect($results)->where('result', 'accepted');
            if ($accepted->isNotEmpty()) {
                $device->forceFill(['last_location_at' => $now])->save();
            }
            $status = $this->present($service->status($now));

            return success([
                'results' => array_map(fn (array $result) => [
                    'client_id' => $result['client_id'],
                    'result' => $result['result'],
                    'reason' => $result['reason'],
                    'check_id' => $result['check']?->id,
                    'status' => $result['check']?->status,
                ], $results),
                'accepted' => $accepted->count(),
                'duplicates' => collect($results)->where('result', 'duplicate')->count(),
                'rejected' => collect($results)->where('result', 'rejected')->count(),
                'location_monitoring' => $status['location_monitoring'] ?? null,
            ]);
        });
    }

    /** The application signs the device out of location updates (the secret stops working). */
    public function unregister(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $employee = $this->requireEmployee($request, $centerId);
            $device = HrAttendanceDevices::authenticate($request, $employee, true);
            HrAttendanceDevices::revoke($device, $request->user(), AttendanceDevice::REVOKED_UNREGISTERED, now());

            return success(['device' => (new AttendanceDeviceResource($device))->resolve()], null, __('validation.hr.device_unregistered'));
        });
    }
}
