<?php

namespace App\Support\Hr;

use App\Models\Hr\Branch;
use App\Models\Hr\Department;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeWorkAssignment;
use App\Models\Hr\WorkLocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class HrOrganizationService
{
    public static function saveBranch(Branch $branch, array $data, int $centerId, User $actor): Branch
    {
        if (array_key_exists('manager_employee_id', $data)) {
            self::assertEmployeeInCenter($data['manager_employee_id'], $centerId, 'manager_employee_id');
        }
        if (isset($data['default_shift_id']) && (int) $data['default_shift_id'] !== (int) $branch->default_shift_id) {
            HrShiftService::assertBranchDefault($centerId, (int) $data['default_shift_id'], $branch->exists ? $branch->id : null);
        }

        return DB::transaction(function () use ($branch, $data, $centerId, $actor) {
            $branch->fill($data);
            $branch->center_id = $centerId;
            if (! $branch->exists) {
                $branch->created_by = $actor->id;
            }
            $branch->save();

            if ($branch->is_headquarters) {
                Branch::query()->forCenter($centerId)
                    ->whereKeyNot($branch->id)
                    ->where('is_headquarters', true)
                    ->get()
                    ->each(fn (Branch $other) => $other->update(['is_headquarters' => false]));
            }

            return $branch;
        });
    }

    public static function deleteBranch(Branch $branch): void
    {
        if (self::branchInUse($branch)) {
            throw new HrException('branch_has_employees');
        }

        DB::transaction(function () use ($branch) {
            $branch->workLocations()->get()->each->delete();
            $branch->delete();
        });
    }

    /**
     * Inactive branches accept no new hires or transfers. Deactivation is refused while employed staff work
     * there or are scheduled to move there; their history keeps pointing at the branch.
     */
    public static function setBranchActive(Branch $branch, bool $active): Branch
    {
        if (! $active && self::branchInUse($branch)) {
            throw new HrException('branch_has_employees');
        }
        $branch->is_active = $active;
        $branch->save();

        return $branch;
    }

    public static function setWorkLocationActive(WorkLocation $location, bool $active): WorkLocation
    {
        if ($active && ! Branch::query()->whereKey($location->branch_id)->where('is_active', true)->exists()) {
            throw new HrException('reference_inactive', 'branch_id');
        }
        if (! $active && self::workLocationInUse($location)) {
            throw new HrException('work_location_has_employees');
        }
        $location->is_active = $active;
        $location->save();

        return $location;
    }

    public static function branchInUse(Branch $branch): bool
    {
        return $branch->employees()->employed()->exists()
            || self::scheduledAssignments('branch_id', $branch->id)->exists();
    }

    public static function workLocationInUse(WorkLocation $location): bool
    {
        return Employee::query()->where('work_location_id', $location->id)->employed()->exists()
            || self::scheduledAssignments('work_location_id', $location->id)->exists();
    }

    /** Assignments of employed staff that have not taken effect yet. */
    private static function scheduledAssignments(string $column, int $id)
    {
        return EmployeeWorkAssignment::query()
            ->where($column, $id)
            ->where('effective_from', '>', today()->toDateString())
            ->whereHas('employee', fn ($query) => $query->employed());
    }

    public static function saveWorkLocation(WorkLocation $location, array $data, int $centerId, User $actor): WorkLocation
    {
        $branchId = $data['branch_id'] ?? $location->branch_id;
        self::assertBranchInCenter($branchId, $centerId);

        if (! $location->exists && ! Branch::query()->whereKey((int) $branchId)->where('is_active', true)->exists()) {
            throw new HrException('reference_inactive', 'branch_id');
        }
        // Moving a location to another branch would leave its employees in a location outside their branch.
        if ($location->exists && (int) $branchId !== (int) $location->branch_id && self::workLocationInUse($location)) {
            throw new HrException('work_location_has_employees', 'branch_id');
        }

        if (! $location->exists && ! array_key_exists('radius_meters', $data)) {
            $data['radius_meters'] = HrSettings::get($centerId, 'attendance.default_gps_radius_meters');
        }

        $location->fill($data);
        $location->center_id = $centerId;
        if (! $location->exists) {
            $location->created_by = $actor->id;
        }
        $location->save();

        return $location;
    }

    public static function deleteWorkLocation(WorkLocation $location): void
    {
        if (self::workLocationInUse($location)) {
            throw new HrException('work_location_has_employees');
        }

        $location->delete();
    }

    public static function saveDepartment(Department $department, array $data, int $centerId, User $actor): Department
    {
        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
            $parentId = (int) $data['parent_id'];
            if (! Department::query()->forCenter($centerId)->whereKey($parentId)->exists()) {
                throw new HrException('department_parent_invalid', 'parent_id');
            }
            if ($department->exists && HrHierarchy::createsCycle(
                $department->id,
                $parentId,
                fn (int $id) => Department::query()->whereKey($id)->value('parent_id')
            )) {
                throw new HrException('department_parent_cycle', 'parent_id');
            }
        }

        if (array_key_exists('manager_employee_id', $data)) {
            self::assertEmployeeInCenter($data['manager_employee_id'], $centerId, 'manager_employee_id');
        }

        $department->fill($data);
        $department->center_id = $centerId;
        if (! $department->exists) {
            $department->created_by = $actor->id;
        }
        $department->save();

        return $department;
    }

    public static function deleteDepartment(Department $department): void
    {
        if ($department->employees()->employed()->exists()) {
            throw new HrException('department_has_employees');
        }
        if ($department->children()->exists()) {
            throw new HrException('department_has_children');
        }

        $department->delete();
    }

    public static function assertBranchInCenter($branchId, int $centerId, string $field = 'branch_id'): void
    {
        if ($branchId === null) {
            return;
        }
        if (! Branch::query()->forCenter($centerId)->whereKey((int) $branchId)->exists()) {
            throw new HrException('reference_not_in_center', $field);
        }
    }

    public static function assertEmployeeInCenter($employeeId, int $centerId, string $field): void
    {
        if ($employeeId === null) {
            return;
        }
        if (! Employee::query()->forCenter($centerId)->whereKey((int) $employeeId)->employed()->exists()) {
            throw new HrException('reference_not_in_center', $field);
        }
    }
}
