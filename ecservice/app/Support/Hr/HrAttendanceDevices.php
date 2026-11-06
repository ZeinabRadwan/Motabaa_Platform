<?php

namespace App\Support\Hr;

use App\Models\Hr\AttendanceDevice;
use App\Models\Hr\Employee;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mobile devices that send location updates for an employee (foundation for the future mobile application).
 *
 *  - Registration needs the employee's normal (Sanctum) session and returns a device secret once; only its SHA-256
 *    hash is stored. Registering the same installation again rotates the secret.
 *  - Mobile location requests need both the employee's session and the device credentials (X-Device-Id with the
 *    installation id, X-Device-Token with the secret): a leaked session token alone cannot send locations, and a
 *    device secret only works for the employee it was registered to.
 *  - When the HR setting requires approval, a new device stays pending until HR approves it. An employee has at
 *    most one active device: activating one revokes the others. A revoked device cannot register again.
 */
final class HrAttendanceDevices
{
    public const DEVICE_ID_HEADER = 'X-Device-Id';
    public const DEVICE_TOKEN_HEADER = 'X-Device-Token';

    public const MAX_PENDING_PER_EMPLOYEE = 3;

    /**
     * @param  array{device_uuid: string, platform: string, device_name?: ?string, device_model?: ?string, os_version?: ?string, app_version?: ?string}  $data
     * @return array{device: AttendanceDevice, token: string, created: bool}
     *
     * @throws HrException
     */
    public static function register(Employee $employee, User $user, array $data, ?string $ip, CarbonInterface $now): array
    {
        return DB::transaction(function () use ($employee, $user, $data, $ip, $now) {
            Employee::query()->whereKey($employee->id)->lockForUpdate()->first();
            if (! $employee->isEmployed()) {
                throw new HrException('attendance_ineligible_not_employed', 'error', 403);
            }

            $token = Str::random(64);
            $attributes = [
                'platform' => $data['platform'],
                'device_name' => $data['device_name'] ?? null,
                'device_model' => $data['device_model'] ?? null,
                'os_version' => $data['os_version'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'token_hash' => AttendanceDevice::hashToken($token),
                'token_issued_at' => $now,
                'last_seen_at' => $now,
                'last_ip' => $ip,
            ];

            $device = AttendanceDevice::query()
                ->forCenter($employee->center_id)
                ->where('device_uuid', $data['device_uuid'])
                ->lockForUpdate()
                ->first();
            if ($device !== null) {
                if ((int) $device->employee_id !== (int) $employee->id) {
                    throw new HrException('device_registered_to_another', 'device_uuid', 409);
                }
                if ($device->status === AttendanceDevice::STATUS_REVOKED) {
                    throw new HrException('device_revoked', 'error', 403);
                }
                $device->fill($attributes)->save();

                return ['device' => $device, 'token' => $token, 'created' => false];
            }

            $approval = (bool) HrSettings::get($employee->center_id, 'attendance.mobile_device_approval_required');
            $pending = AttendanceDevice::query()
                ->where('employee_id', $employee->id)
                ->where('status', AttendanceDevice::STATUS_PENDING)
                ->count();
            if ($approval && $pending >= self::MAX_PENDING_PER_EMPLOYEE) {
                throw new HrException('device_limit_reached', 'error', 422, ['max' => self::MAX_PENDING_PER_EMPLOYEE]);
            }

            $device = AttendanceDevice::create($attributes + [
                'center_id' => $employee->center_id,
                'employee_id' => $employee->id,
                'user_id' => $user->id,
                'device_uuid' => $data['device_uuid'],
                'status' => $approval ? AttendanceDevice::STATUS_PENDING : AttendanceDevice::STATUS_ACTIVE,
                'approved_at' => $approval ? null : $now,
            ]);
            if (! $approval) {
                self::revokeOthers($device, null, $now);
            }

            return ['device' => $device, 'token' => $token, 'created' => true];
        });
    }

    /**
     * The device the request's credentials belong to, for this employee. Pending and revoked devices are refused
     * unless $allowInactive (status and unregister endpoints).
     *
     * @throws HrException
     */
    public static function authenticate(Request $request, Employee $employee, bool $allowInactive = false): AttendanceDevice
    {
        $uuid = (string) $request->header(self::DEVICE_ID_HEADER, '');
        $token = (string) $request->header(self::DEVICE_TOKEN_HEADER, '');
        $device = $uuid !== '' && $token !== '' && strlen($token) <= 255
            ? AttendanceDevice::query()->where('token_hash', AttendanceDevice::hashToken($token))->first()
            : null;

        if ($device === null
            || ! hash_equals((string) $device->device_uuid, $uuid)
            || (int) $device->employee_id !== (int) $employee->id
            || (int) $device->center_id !== (int) $employee->center_id) {
            throw new HrException('device_unauthenticated', 'device', 401);
        }
        if (! $allowInactive && $device->status === AttendanceDevice::STATUS_PENDING) {
            throw new HrException('device_pending', 'device', 403);
        }
        if (! $allowInactive && $device->status === AttendanceDevice::STATUS_REVOKED) {
            throw new HrException('device_revoked', 'device', 403);
        }

        $device->forceFill(['last_seen_at' => now(), 'last_ip' => $request->ip()])->save();

        return $device;
    }

    /** @throws HrException */
    public static function approve(AttendanceDevice $device, User $actor, CarbonInterface $now): AttendanceDevice
    {
        return DB::transaction(function () use ($device, $actor, $now) {
            $device = AttendanceDevice::query()->whereKey($device->id)->lockForUpdate()->firstOrFail();
            if ($device->status !== AttendanceDevice::STATUS_PENDING) {
                throw new HrException('device_not_pending');
            }
            $device->fill(['status' => AttendanceDevice::STATUS_ACTIVE, 'approved_by' => $actor->id, 'approved_at' => $now])->save();
            self::revokeOthers($device, $actor, $now);

            return $device;
        });
    }

    public static function revoke(AttendanceDevice $device, ?User $actor, string $reason, CarbonInterface $now): AttendanceDevice
    {
        if ($device->status !== AttendanceDevice::STATUS_REVOKED) {
            $device->fill([
                'status' => AttendanceDevice::STATUS_REVOKED,
                'revoked_by' => $actor?->id,
                'revoked_at' => $now,
                'revoke_reason' => $reason,
            ])->save();
        }

        return $device;
    }

    /** An employee keeps one active device: the newly active one replaces the others. */
    private static function revokeOthers(AttendanceDevice $device, ?User $actor, CarbonInterface $now): void
    {
        AttendanceDevice::query()
            ->where('employee_id', $device->employee_id)
            ->whereKeyNot($device->id)
            ->where('status', AttendanceDevice::STATUS_ACTIVE)
            ->get()
            ->each(fn (AttendanceDevice $other) => self::revoke($other, $actor, AttendanceDevice::REVOKED_REPLACED, $now));
    }
}
