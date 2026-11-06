<?php

namespace App\Support\Hr;

use App\Models\Hr\Branch;
use App\Models\Hr\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which branches' HR data a user may see. Holders of access_hr-all-branches see the whole center; everyone
 * else is limited to the branches they manage (hr_branches.manager_employee_id = their employee profile).
 * The scope narrows what HR permissions allow, it never grants access by itself.
 */
final class HrBranchScope
{
    public const ALL_BRANCHES_PERMISSION = 'access_hr-all-branches';

    /**
     * @return int[]|null null = every branch of the center
     */
    public static function branchIds(?User $user, int $centerId): ?array
    {
        // System callers (console commands, backfills) are not scoped.
        if ($user === null || can(self::ALL_BRANCHES_PERMISSION, $user)) {
            return null;
        }

        $employeeId = Employee::query()->forCenter($centerId)->where('user_id', $user->id)->employed()->value('id');
        if ($employeeId === null) {
            return [];
        }

        return Branch::query()->forCenter($centerId)
            ->where('manager_employee_id', $employeeId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** Limits a query to the allowed branches through the given branch column. */
    public static function restrict(Builder $query, ?array $branchIds, string $column, bool $includeUnassigned = false): Builder
    {
        if ($branchIds === null) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($branchIds, $column, $includeUnassigned) {
            $inner->whereIn($column, $branchIds === [] ? [0] : $branchIds);
            if ($includeUnassigned) {
                $inner->orWhereNull($column);
            }
        });
    }

    public static function allows(?array $branchIds, mixed $branchId): bool
    {
        return $branchIds === null || ($branchId !== null && in_array((int) $branchId, $branchIds, true));
    }

    public static function assertAllowed(?array $branchIds, mixed $branchId, string $field = 'branch_id'): void
    {
        if (! self::allows($branchIds, $branchId)) {
            throw new HrException('branch_not_authorized', $field, 403);
        }
    }

    /** Center-wide actions (creating branches, assigning managers, center-wide shifts…). */
    public static function assertUnrestricted(?array $branchIds): void
    {
        if ($branchIds !== null) {
            throw new HrException('branch_scope_required', 'error', 403);
        }
    }
}
