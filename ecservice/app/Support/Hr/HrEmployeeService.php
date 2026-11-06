<?php

namespace App\Support\Hr;

use App\Models\CenterUser;
use App\Models\Hr\Department;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeShiftAssignment;
use App\Models\Hr\EmploymentPeriod;
use App\Models\Hr\Shift;
use App\Models\Hr\WorkLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class HrEmployeeService
{
    /** Request keys stored on users (identity, contact and the legacy employment columns). */
    public const USER_FIELDS = [
        'name', 'email', 'phone', 'nationality', 'gender', 'birthdate', 'id_or_residence_number', 'id_expiry_date',
        'address_city', 'address_area', 'address_street', 'address_building', 'address_number', 'address_unit',
        'address_zipcode', 'job_title', 'hire_date', 'contract_type', 'contract_end_date',
    ];

    /**
     * Employment fields whose changes are written to the employee timeline. Branch and work location changes
     * are logged by HrWorkAssignments together with their effective-dated row.
     */
    private const TRACKED_REFERENCES = [
        'department_id' => ['department', Department::class],
        'manager_id' => ['manager', Employee::class],
    ];

    /**
     * Staff users of the center (same definition as the existing staff modules) without an HR profile.
     */
    public static function candidatesQuery(int $centerId): Builder
    {
        return User::staffInCenter($centerId)->whereNotIn(
            'users.id',
            Employee::withTrashed()->forCenter($centerId)->select('user_id')
        );
    }

    /** Roles a new staff user may receive (same rule as User::staffInCenter: not parent, not admin). */
    public static function staffRolesQuery(): Builder
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->where(fn ($query) => $query->whereNotIn('default_name', ['parent', 'admin'])->orWhereNull('default_name'));
    }

    /**
     * API entry point: links an existing staff user (mode "existing") or creates a new staff user who cannot
     * log in yet (mode "new"), writes the personal data on users and creates the HR profile, atomically.
     */
    public static function register(int $centerId, array $data, User $actor): Employee
    {
        HrBranchScope::assertAllowed(HrBranchScope::branchIds($actor, $centerId), $data['branch_id'] ?? null);
        if (isset($data['branch_id'])) {
            HrWorkAssignments::assignable($centerId, (int) $data['branch_id'], isset($data['work_location_id']) ? (int) $data['work_location_id'] : null);
        }

        return DB::transaction(function () use ($centerId, $data, $actor) {
            if (($data['mode'] ?? 'existing') === 'new') {
                $user = self::createStaffUser($centerId, $data);
            } else {
                $user = User::query()->whereKey((int) ($data['user_id'] ?? 0))->first();
                if ($user === null || ! User::staffInCenter($centerId)->whereKey($user->id)->exists()) {
                    throw new HrException('employee_not_staff', 'user_id');
                }
            }

            if (Employee::withTrashed()->forCenter($centerId)->where('user_id', $user->id)->exists()) {
                throw new HrException('employee_exists', 'user_id');
            }

            self::writePersonalData($user, $data, $centerId);

            return self::create($centerId, array_merge($data, ['user_id' => $user->id]), $actor);
        });
    }

    /**
     * Creates the HR profile for a staff user. Also used by hr:sync-employees, which imports legacy staff with
     * whatever data they have, so field completeness is enforced by the API request, not here.
     */
    public static function create(int $centerId, array $data, ?User $actor): Employee
    {
        $userId = (int) $data['user_id'];
        if (! User::staffInCenter($centerId)->whereKey($userId)->exists()) {
            throw new HrException('employee_not_staff', 'user_id');
        }
        if (Employee::withTrashed()->forCenter($centerId)->where('user_id', $userId)->exists()) {
            throw new HrException('employee_exists', 'user_id');
        }

        self::assertReferences($centerId, $data, null);
        HrEmployeeAttendance::assertValidForNewEmployee(
            $centerId,
            $data,
            isset($data['work_location_id']) ? (int) $data['work_location_id'] : null,
            Carbon::parse($data['employment_start_date'] ?? today())->startOfDay()
        );
        $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;
        if (isset($data['shift_id'])) {
            $shift = self::findShift($centerId, (int) $data['shift_id']);
            HrShiftService::assertFitsBranch($shift, $branchId);
        } else {
            $shift = HrShiftService::branchDefaultShift($centerId, $branchId);
        }

        return DB::transaction(function () use ($centerId, $data, $actor, $userId, $shift) {
            $user = User::query()->findOrFail($userId);

            $employee = new Employee(Arr::except($data, ['user_id', 'center_id', 'terminated_at', 'termination_reason']));
            $employee->user_id = $userId;
            $employee->center_id = $centerId;
            $employee->employee_number = $data['employee_number'] ?? self::nextEmployeeNumber($centerId);
            $employee->employment_status = in_array($data['employment_status'] ?? null, Employee::EMPLOYED_STATUSES, true)
                ? $data['employment_status']
                : Employee::STATUS_ACTIVE;
            $employee->attendance_mode = $data['attendance_mode'] ?? Employee::ATTENDANCE_MANUAL;
            $employee->gps_policy = HrEmployeeAttendance::normalizedGps($employee->attendance_mode, $data['gps_policy'] ?? null);
            $employee->employment_start_date = $data['employment_start_date']
                ?? $user->hire_date?->toDateString()
                ?? today()->toDateString();
            $employee->created_by = $actor?->id;
            $employee->updated_by = $actor?->id;
            $employee->save();

            EmploymentPeriod::create([
                'center_id' => $centerId,
                'employee_id' => $employee->id,
                'start_date' => $employee->employment_start_date->toDateString(),
                'created_by' => $actor?->id,
            ]);
            HrEmployeeChangeLog::record($employee, 'hired', null, $employee->employment_start_date, $employee->employment_start_date);
            HrWorkAssignments::open($employee, $actor);
            HrEmployeeAttendance::open($employee, $actor);

            if ($shift !== null) {
                $assignment = EmployeeShiftAssignment::create([
                    'center_id' => $centerId,
                    'employee_id' => $employee->id,
                    'shift_id' => $shift->id,
                    'effective_from' => $employee->employment_start_date->toDateString(),
                    'created_by' => $actor?->id,
                ]);
                HrEmployeeChangeLog::record($employee, 'shift', null, $shift->id, $assignment->effective_from, null, $assignment, [
                    'new' => HrEmployeeChangeLog::snapshot($shift),
                ]);
            }

            return $employee;
        });
    }

    /**
     * Personal + employment edit. Organizational changes are written to the timeline with the given effective
     * date (default today). Ending employment goes through HrEmploymentLifecycle, never through an edit.
     */
    public static function update(Employee $employee, array $data, User $actor): Employee
    {
        unset($data['user_id'], $data['center_id'], $data['mode']);
        self::assertReferences($employee->center_id, $data, $employee);

        if (isset($data['employment_status']) && $data['employment_status'] !== $employee->employment_status
            && ! $employee->isEmployed()) {
            throw new HrException('status_change_requires_activation', 'employment_status');
        }

        $effective = Carbon::parse($data['effective_date'] ?? today())->startOfDay();
        $reason = $data['change_reason'] ?? null;
        $before = $employee->replicate();
        $user = $employee->user;
        $userBefore = $user->only(['job_title', 'contract_type', 'contract_end_date']);

        if (self::employmentChanged($employee, $data, $userBefore)) {
            HrEffectiveDating::assertNotLocked($employee->center_id, $effective, 'effective_date');
        }

        return DB::transaction(function () use ($employee, $data, $actor, $effective, $reason, $before, $user, $userBefore) {
            Employee::query()->whereKey($employee->id)->lockForUpdate()->first();

            self::writePersonalData($user, $data, $employee->center_id, $employee);

            // A new branch without an explicit work location clears the location of the old branch.
            $branchId = $data['branch_id'] ?? $employee->branch_id;
            $locationId = array_key_exists('work_location_id', $data)
                ? $data['work_location_id']
                : ((int) $branchId === (int) $employee->branch_id ? $employee->work_location_id : null);
            $placementChanged = $branchId !== null
                && ((int) $branchId !== (int) $employee->branch_id || (int) $locationId !== (int) $employee->work_location_id);

            $employee->fill(Arr::only($data, [
                'employee_number', 'department_id', 'manager_id',
                'employment_start_date', 'contract_start_date', 'employment_status', 'notes',
            ]));

            if ($employee->isDirty('employment_start_date')) {
                self::syncFirstPeriodStart($employee);
            }

            $employee->updated_by = $actor->id;
            $employee->save();

            self::logEmploymentChanges($before, $employee, $userBefore, $user->fresh(), $effective, $reason);

            if ($placementChanged) {
                HrWorkAssignments::transfer($employee, [
                    'branch_id' => $branchId,
                    'work_location_id' => $locationId,
                    'effective_from' => $effective->toDateString(),
                    'reason' => $reason,
                ], $actor);
            }

            return $employee;
        });
    }

    public static function updatePhoto(Employee $employee, UploadedFile $file): void
    {
        $employee->user->setImage($file);
        HrAuditLogger::record($employee, HrAuditLogger::EVENT_UPDATED, null, ['photo' => 'updated']);
    }

    public static function removePhoto(Employee $employee): void
    {
        $employee->user->deleteImage();
        HrAuditLogger::record($employee, HrAuditLogger::EVENT_UPDATED, ['photo' => 'removed'], null);
    }

    /**
     * Late-arrival grace period today: employee override, then the shift's, then the policy version in force,
     * then the center setting. Same order as HrShiftSchedule, which applies it per scheduled day.
     */
    public static function effectiveGraceMinutes(Employee $employee, ?Shift $shift = null, ?AttendanceRuleSet $rules = null): int
    {
        $rules ??= HrAttendanceRules::on($employee, today());
        $shiftGrace = $shift !== null && ! $shift->isRotating() && $shift->grace_minutes !== null ? (int) $shift->grace_minutes : null;

        return $rules->lateGrace($shiftGrace);
    }

    /**
     * Next free "{prefix}{0001}" number for the center. The unique index is the final guard against
     * two concurrent creations picking the same number.
     */
    public static function nextEmployeeNumber(int $centerId): string
    {
        $prefix = (string) HrSettings::get($centerId, 'employees.number_prefix');
        $sequence = Employee::withTrashed()->forCenter($centerId)->count() + 1;
        do {
            $number = $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (Employee::withTrashed()->forCenter($centerId)->where('employee_number', $number)->exists());

        return $number;
    }

    public static function findShift(int $centerId, int $shiftId): Shift
    {
        $shift = Shift::query()->forCenter($centerId)->whereKey($shiftId)->first();
        if ($shift === null) {
            throw new HrException('reference_not_in_center', 'shift_id');
        }
        if (! $shift->is_active) {
            throw new HrException('reference_inactive', 'shift_id');
        }

        return $shift;
    }

    private static function createStaffUser(int $centerId, array $data): User
    {
        $roleId = (int) ($data['role_id'] ?? 0);
        if (! self::staffRolesQuery()->whereKey($roleId)->exists()) {
            throw new HrException('role_not_staff', 'role_id');
        }

        $user = User::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'status' => User::STATUS_CANNT_LOGIN,
        ]);
        CenterUser::create(['center_id' => $centerId, 'user_id' => $user->id]);
        $user->syncRoles([$roleId]);
        HrAuditLogger::record($user, HrAuditLogger::EVENT_CREATED, null, $user->only(['name', 'email', 'phone']), $centerId);

        return $user;
    }

    /**
     * Writes the personal fields present in $data to users and audits the difference (against the employee
     * when there is one, so it shows in the employee's audit trail). The ID number must not belong to another
     * user.
     */
    private static function writePersonalData(User $user, array $data, int $centerId, ?Employee $auditSubject = null): void
    {
        $values = Arr::only($data, self::USER_FIELDS);
        if (array_key_exists('id_or_residence_number', $values) && $values['id_or_residence_number'] !== null) {
            $values['id_or_residence_number'] = SaudiIdentity::normalize($values['id_or_residence_number']);
            $taken = User::query()
                ->where('id_or_residence_number', $values['id_or_residence_number'])
                ->whereKeyNot($user->id)
                ->exists();
            if ($taken) {
                throw new HrException('id_number_taken', 'id_or_residence_number');
            }
        }
        if (isset($values['nationality'])) {
            $values['nationality'] = strtoupper((string) $values['nationality']);
        }
        if ($values === []) {
            return;
        }

        $user->fill($values);
        if (! $user->isDirty()) {
            return;
        }

        [$old, $new] = HrAuditLogger::diff($user->getOriginal(), $user->getDirty(), ['search_text']);
        $user->save();
        if ($new !== []) {
            HrAuditLogger::record($auditSubject ?? $user, HrAuditLogger::EVENT_UPDATED, $old, $new, $centerId);
        }
    }

    private static function employmentChanged(Employee $employee, array $data, array $userBefore): bool
    {
        foreach (['branch_id', 'department_id', 'work_location_id', 'manager_id', 'employment_status', 'employee_number'] as $field) {
            if (array_key_exists($field, $data) && (string) ($data[$field] ?? '') !== (string) ($employee->{$field} ?? '')) {
                return true;
            }
        }

        return array_key_exists('job_title', $data) && (string) $data['job_title'] !== (string) ($userBefore['job_title'] ?? '');
    }

    private static function logEmploymentChanges(Employee $before, Employee $after, array $userBefore, User $userAfter, Carbon $effective, ?string $reason): void
    {
        foreach (self::TRACKED_REFERENCES as $field => [$type, $class]) {
            if ((int) $before->{$field} === (int) $after->{$field}) {
                continue;
            }
            $find = fn ($id) => $id ? $class::query()->when(method_exists($class, 'withTrashed'), fn ($q) => $q->withTrashed())->find($id) : null;
            HrEmployeeChangeLog::record($after, $type, $before->{$field}, $after->{$field}, $effective, $reason, null, [
                'old' => HrEmployeeChangeLog::snapshot($find($before->{$field})),
                'new' => HrEmployeeChangeLog::snapshot($find($after->{$field})),
            ]);
        }

        if ($before->employment_status !== $after->employment_status) {
            HrEmployeeChangeLog::record($after, 'status', $before->employment_status, $after->employment_status, $effective, $reason);
        }
        if ($before->employee_number !== $after->employee_number) {
            HrEmployeeChangeLog::record($after, 'employee_number', $before->employee_number, $after->employee_number, $effective, $reason);
        }
        if ((string) ($userBefore['job_title'] ?? '') !== (string) $userAfter->job_title) {
            HrEmployeeChangeLog::record($after, 'job_title', $userBefore['job_title'] ?? null, $userAfter->job_title, $effective, $reason);
        }

        $contractBefore = self::contractSummary($userBefore['contract_type'] ?? null, $before->contract_start_date, $userBefore['contract_end_date'] ?? null);
        $contractAfter = self::contractSummary($userAfter->contract_type, $after->contract_start_date, $userAfter->contract_end_date);
        if ($contractBefore !== $contractAfter) {
            HrEmployeeChangeLog::record($after, 'contract', $contractBefore, $contractAfter, $effective, $reason, null, [
                'old' => self::contractLabel($contractBefore),
                'new' => self::contractLabel($contractAfter),
            ]);
        }
    }

    /** @return array{type: ?string, start: ?string, end: ?string}|null */
    private static function contractSummary(?string $type, mixed $start, mixed $end): ?array
    {
        if (! $type && ! $start && ! $end) {
            return null;
        }
        $date = fn ($value) => $value ? Carbon::parse($value)->toDateString() : null;

        return ['type' => $type ?: null, 'start' => $date($start), 'end' => $date($end)];
    }

    private static function contractLabel(?array $contract): ?array
    {
        if ($contract === null) {
            return null;
        }
        $label = [];
        foreach (['ar', 'en'] as $locale) {
            $type = $contract['type'] ? __("tr.employee_affairs.contract_types.{$contract['type']}", [], $locale) : '';
            $label[$locale] = trim($type.' '.($contract['start'] ?? '…').' - '.($contract['end'] ?? '…'));
        }

        return $label;
    }

    /** Keeps the first employment period aligned with a corrected employment start date. */
    private static function syncFirstPeriodStart(Employee $employee): void
    {
        $periods = $employee->employmentPeriods()->orderBy('start_date')->get();
        if ($periods->count() !== 1) {
            throw new HrException('employment_start_locked', 'employment_start_date');
        }
        $period = $periods->first();
        $newStart = Carbon::parse($employee->employment_start_date)->startOfDay();
        HrEffectiveDating::assertNotLocked($employee->center_id, $newStart->min($period->start_date), 'employment_start_date');
        if ($period->end_date !== null && $newStart->gt($period->end_date)) {
            throw new HrException('deactivation_before_start', 'employment_start_date', 422, ['date' => $newStart->toDateString()]);
        }
        $period->start_date = $newStart->toDateString();
        $period->save();
    }

    private static function assertReferences(int $centerId, array $data, ?Employee $employee): void
    {
        if (array_key_exists('branch_id', $data)) {
            HrOrganizationService::assertBranchInCenter($data['branch_id'], $centerId);
        }

        if (isset($data['department_id'])
            && ! Department::query()->forCenter($centerId)->whereKey((int) $data['department_id'])->exists()) {
            throw new HrException('reference_not_in_center', 'department_id');
        }

        if (isset($data['work_location_id'])) {
            $branchId = array_key_exists('branch_id', $data) ? $data['branch_id'] : $employee?->branch_id;
            $location = WorkLocation::query()->forCenter($centerId)->whereKey((int) $data['work_location_id'])->first();
            if (! $location) {
                throw new HrException('reference_not_in_center', 'work_location_id');
            }
            if ($branchId !== null && (int) $location->branch_id !== (int) $branchId) {
                throw new HrException('work_location_branch_mismatch', 'work_location_id');
            }
        }

        if (isset($data['manager_id'])) {
            $managerId = (int) $data['manager_id'];
            if ($employee && $managerId === (int) $employee->id) {
                throw new HrException('manager_cycle', 'manager_id');
            }
            HrOrganizationService::assertEmployeeInCenter($managerId, $centerId, 'manager_id');
            if ($employee && HrHierarchy::createsCycle(
                $employee->id,
                $managerId,
                fn (int $id) => Employee::query()->whereKey($id)->value('manager_id')
            )) {
                throw new HrException('manager_cycle', 'manager_id');
            }
        }

        if (isset($data['employee_number'])) {
            $taken = Employee::withTrashed()->forCenter($centerId)
                ->where('employee_number', $data['employee_number'])
                ->when($employee, fn ($q) => $q->whereKeyNot($employee->id))
                ->exists();
            if ($taken) {
                throw new HrException('employee_number_taken', 'employee_number');
            }
        }
    }
}
