<?php

namespace App\Support\Hr;

use App\Models\Hr\Department;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Bootstraps the HR module for a center from existing data, without modifying users:
 *  - one hr_departments row per legacy users.department key (User::departments()), code = key;
 *  - one hr_employees profile per staff user that has none, linked to the matching department.
 */
class HrStaffSync
{
    /**
     * @return array{center_id: int, departments_to_create: array<int, string>, employees_to_create: array<int, array{user_id: int, name: string, department: ?string}>}
     */
    public static function plan(int $centerId): array
    {
        $existingCodes = Department::withTrashed()->forCenter($centerId)->pluck('code')->all();
        $departments = array_values(array_diff(array_keys(User::departments()), $existingCodes));

        $employees = HrEmployeeService::candidatesQuery($centerId)
            ->orderBy('users.id')
            ->get(['users.id', 'users.name', 'users.department'])
            ->map(fn (User $user) => [
                'user_id' => (int) $user->id,
                'name' => (string) $user->name,
                'department' => $user->department ?: null,
            ])
            ->values()
            ->all();

        return [
            'center_id' => $centerId,
            'departments_to_create' => $departments,
            'employees_to_create' => $employees,
        ];
    }

    /**
     * @return array{departments_created: int, employees_created: int}
     */
    public static function apply(array $plan): array
    {
        $centerId = $plan['center_id'];

        return DB::transaction(function () use ($centerId, $plan) {
            foreach ($plan['departments_to_create'] as $index => $code) {
                Department::create([
                    'center_id' => $centerId,
                    'code' => $code,
                    'name_ar' => __("tr.department.{$code}", [], 'ar'),
                    'name_en' => __("tr.department.{$code}", [], 'en'),
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
            }

            $departmentIds = Department::query()->forCenter($centerId)->pluck('id', 'code');
            foreach ($plan['employees_to_create'] as $row) {
                HrEmployeeService::create($centerId, [
                    'user_id' => $row['user_id'],
                    'department_id' => $row['department'] ? ($departmentIds[$row['department']] ?? null) : null,
                ], null);
            }

            return [
                'departments_created' => count($plan['departments_to_create']),
                'employees_created' => count($plan['employees_to_create']),
            ];
        });
    }
}
