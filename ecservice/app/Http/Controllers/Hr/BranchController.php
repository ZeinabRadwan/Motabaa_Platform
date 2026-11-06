<?php

namespace App\Http\Controllers\Hr;

use App\Http\Requests\Hr\BranchRequest;
use App\Http\Resources\Hr\BranchResource;
use App\Http\Resources\Hr\PlacedEmployeeResource;
use App\Models\Hr\Branch;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeWorkAssignment;
use App\Support\Hr\HrBranchScope;
use App\Support\Hr\HrOrganizationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends HrController
{
    public function index(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $q = trim((string) $request->get('q', ''));
            $query = Branch::query()->forCenter($centerId)
                ->withCount(['workLocations', 'employees' => fn ($query) => $query->employed()])
                ->with(['manager.user:id,name', 'defaultShift'])
                ->when($request->boolean('trashed'), fn ($query) => $query->onlyTrashed())
                ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
                ->when($request->filled('city'), fn ($query) => $query->where('city', (string) $request->get('city')))
                ->when($request->filled('country'), fn ($query) => $query->where('country', strtoupper((string) $request->get('country'))))
                ->when($request->filled('has_manager'), fn ($query) => $request->boolean('has_manager')
                    ? $query->whereNotNull('manager_employee_id')
                    : $query->whereNull('manager_employee_id'))
                ->when($q !== '', fn ($query) => $query->where(function ($inner) use ($q) {
                    $inner->where('code', 'like', "%{$q}%")
                        ->orWhere('name_ar', 'like', "%{$q}%")
                        ->orWhere('name_en', 'like', "%{$q}%")
                        ->orWhere('city', 'like', "%{$q}%")
                        ->orWhere('address', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                }))
                ->when(empty($request->input('options.sortBy')), fn ($query) => $query->orderByDesc('is_headquarters')->orderBy('name_ar'));
            HrBranchScope::restrict($query, $this->branchScope($request, $centerId), 'hr_branches.id');

            return $this->paginated($request, $query, BranchResource::class, [
                'code' => 'code', 'name' => 'name_ar', 'city' => 'city', 'country' => 'country',
                'employees_count' => 'employees_count', 'work_locations_count' => 'work_locations_count', 'created_at' => 'created_at',
            ]);
        });
    }

    /** Distinct cities of the visible branches (filter options). */
    public function cities(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $query = Branch::query()->forCenter($centerId)->whereNotNull('city')->where('city', '!=', '');
            HrBranchScope::restrict($query, $this->branchScope($request, $centerId), 'id');

            return success($query->distinct()->orderBy('city')->pluck('city')->values());
        });
    }

    public function store(BranchRequest $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            HrBranchScope::assertUnrestricted($this->branchScope($request, $centerId));
            $branch = HrOrganizationService::saveBranch(new Branch(), $request->validated(), $centerId, $request->user());

            return success(new BranchResource($this->withSummary($branch)), null, __('validation.hr.saved'));
        });
    }

    public function show(Request $request, int $branch): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($branch) {
            $model = $this->findInCenter(Branch::class, $branch, $centerId, true);

            return success(new BranchResource($this->withSummary($model)));
        });
    }

    public function update(BranchRequest $request, int $branch): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $branch) {
            $model = $this->findInCenter(Branch::class, $branch, $centerId);
            $data = $request->validated();

            // Branch managers may edit their branch details, but not who manages it or the headquarters flag.
            $managerChanged = array_key_exists('manager_employee_id', $data) && (int) $data['manager_employee_id'] !== (int) $model->manager_employee_id;
            $hqChanged = array_key_exists('is_headquarters', $data) && (bool) $data['is_headquarters'] !== (bool) $model->is_headquarters;
            if ($managerChanged || $hqChanged) {
                HrBranchScope::assertUnrestricted($this->branchScope($request, $centerId));
            }

            $model = HrOrganizationService::saveBranch($model, $data, $centerId, $request->user());

            return success(new BranchResource($this->withSummary($model)), null, __('validation.hr.saved'));
        });
    }

    public function activate(Request $request, int $branch): JsonResponse
    {
        return $this->setActive($request, $branch, true);
    }

    public function deactivate(Request $request, int $branch): JsonResponse
    {
        return $this->setActive($request, $branch, false);
    }

    /**
     * Employed staff currently assigned to the branch, with the date their current assignment started.
     */
    public function employees(Request $request, int $branch): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $branch) {
            $model = $this->findInCenter(Branch::class, $branch, $centerId, true);

            return $this->paginated(
                $request,
                self::placedEmployees($request, $centerId)->where('hr_employees.branch_id', $model->id)
                    ->when($request->filled('work_location_id'), fn ($query) => $query->where('hr_employees.work_location_id', (int) $request->get('work_location_id'))),
                PlacedEmployeeResource::class,
                ['employee_number' => 'hr_employees.employee_number', 'name' => 'users.name', 'assigned_since' => 'assigned_since']
            );
        });
    }

    public function destroy(Request $request, int $branch): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $branch) {
            HrBranchScope::assertUnrestricted($this->branchScope($request, $centerId));
            HrOrganizationService::deleteBranch($this->findInCenter(Branch::class, $branch, $centerId));

            return success(null, null, __('validation.hr.deleted'));
        });
    }

    public function restore(Request $request, int $branch): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $branch) {
            HrBranchScope::assertUnrestricted($this->branchScope($request, $centerId));
            $model = $this->findInCenter(Branch::query()->onlyTrashed(), $branch, $centerId);
            $model->restore();

            return success(new BranchResource($this->withSummary($model)), null, __('validation.hr.restored'));
        });
    }

    /**
     * Employed staff with their user, department and the start of their current work assignment. Shared by the
     * branch and work location employee lists.
     */
    public static function placedEmployees(Request $request, int $centerId): Builder
    {
        $q = trim((string) $request->get('q', ''));
        $keywords = $q === '' ? '' : mb_ereg_replace(' ', '%', getFTS($q));
        $assignments = (new EmployeeWorkAssignment())->getTable();
        $today = today()->toDateString();

        return Employee::query()->forCenter($centerId)
            ->employed()
            ->select('hr_employees.*')
            ->selectSub(
                EmployeeWorkAssignment::query()
                    ->select('effective_from')
                    ->whereColumn("{$assignments}.employee_id", 'hr_employees.id')
                    ->where('effective_from', '<=', $today)
                    ->orderByDesc('effective_from')
                    ->limit(1),
                'assigned_since'
            )
            ->join('users', 'users.id', '=', 'hr_employees.user_id')
            ->with(['user:id,name,image_path,job_title,deleted_at', 'department:id,name_ar,name_en,deleted_at', 'workLocation:id,name_ar,name_en,deleted_at'])
            ->when($q !== '', fn ($query) => $query->where(function ($inner) use ($q, $keywords) {
                $inner->where('hr_employees.employee_number', 'like', "%{$q}%")
                    ->orWhere('users.search_text', 'like', "%{$keywords}%");
            }))
            ->when(empty($request->input('options.sortBy')), fn ($query) => $query->orderBy('hr_employees.employee_number'));
    }

    private function setActive(Request $request, int $branch, bool $active): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $branch, $active) {
            HrBranchScope::assertUnrestricted($this->branchScope($request, $centerId));
            $model = HrOrganizationService::setBranchActive($this->findInCenter(Branch::class, $branch, $centerId), $active);

            return success(
                new BranchResource($this->withSummary($model)),
                null,
                __($active ? 'validation.hr.branch_activated' : 'validation.hr.branch_deactivated')
            );
        });
    }

    private function withSummary(Branch $branch): Branch
    {
        return $branch->load(['manager.user:id,name', 'defaultShift'])
            ->loadCount(['workLocations', 'employees' => fn ($query) => $query->employed()]);
    }
}
