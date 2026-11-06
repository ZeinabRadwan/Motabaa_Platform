<?php

namespace App\Http\Controllers\Hr;

use App\Http\Requests\Hr\ShiftAssignRequest;
use App\Http\Requests\Hr\ShiftRequest;
use App\Http\Resources\Hr\ShiftResource;
use App\Http\Resources\Hr\ShiftVersionResource;
use App\Models\Hr\Branch;
use App\Models\Hr\Employee;
use App\Models\Hr\Shift;
use App\Support\Hr\HrBranchScope;
use App\Support\Hr\HrException;
use App\Support\Hr\HrOrganizationService;
use App\Support\Hr\HrShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShiftController extends HrController
{
    public function index(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $q = trim((string) $request->get('q', ''));
            $query = Shift::query()->forCenter($centerId)
                ->with(['branch:id,name_ar,name_en,deleted_at', 'scheduledVersion'])
                ->withCount('currentAssignments')
                ->when($request->boolean('trashed'), fn ($query) => $query->onlyTrashed())
                ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', (int) $request->get('branch_id')))
                ->when($request->filled('shift_type'), fn ($query) => $query->where('shift_type', (string) $request->get('shift_type')))
                ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
                ->when($q !== '', fn ($query) => $query->where(function ($inner) use ($q) {
                    $inner->where('code', 'like', "%{$q}%")
                        ->orWhere('name_ar', 'like', "%{$q}%")
                        ->orWhere('name_en', 'like', "%{$q}%");
                }));
            HrBranchScope::restrict($query, $this->branchScope($request, $centerId), 'hr_shifts.branch_id', true);

            return $this->paginated($request, $query, ShiftResource::class, [
                'code' => 'code', 'name' => 'name_ar', 'shift_type' => 'shift_type', 'start_time' => 'start_time', 'created_at' => 'created_at',
            ]);
        });
    }

    public function store(ShiftRequest $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            // Shifts without a branch apply center-wide, so only unrestricted users can create them.
            HrBranchScope::assertAllowed($this->branchScope($request, $centerId), $request->validated('branch_id'));
            $shift = HrShiftService::create($request->validated(), $centerId, $request->user());

            return success($this->present($shift), null, __('validation.hr.saved'));
        });
    }

    public function show(Request $request, int $shift): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($shift) {
            return success($this->present($this->findInCenter(Shift::class, $shift, $centerId, true)));
        });
    }

    public function update(ShiftRequest $request, int $shift): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $shift) {
            $model = $this->findInCenter(Shift::class, $shift, $centerId);
            $scope = $this->branchScope($request, $centerId);
            HrBranchScope::assertAllowed($scope, $model->branch_id);
            if ($request->has('branch_id')) {
                HrBranchScope::assertAllowed($scope, $request->validated('branch_id'));
            }
            [$model, $scheduled] = HrShiftService::update($model, $request->validated(), $request->user());

            $message = $scheduled !== null
                ? __('validation.hr.shift_change_scheduled', ['date' => $scheduled->effective_from->toDateString()])
                : __('validation.hr.saved');

            return success($this->present($model), null, $message);
        });
    }

    /** PATCH: requires admin_hr-attendance-setup. */
    public function activate(Request $request, int $shift): JsonResponse
    {
        return $this->setActive($request, $shift, true);
    }

    /** PATCH: requires admin_hr-attendance-setup. Refused while employees, rotations or branches use it. */
    public function deactivate(Request $request, int $shift): JsonResponse
    {
        return $this->setActive($request, $shift, false);
    }

    public function versions(Request $request, int $shift): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($shift) {
            $model = $this->findInCenter(Shift::class, $shift, $centerId, true);

            return success(ShiftVersionResource::collection(
                $model->versions()->with('creator:id,name')->orderByDesc('effective_from')->get()
            ));
        });
    }

    public function cancelVersion(Request $request, int $shift, int $version): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $shift, $version) {
            $model = $this->findInCenter(Shift::class, $shift, $centerId);
            HrBranchScope::assertAllowed($this->branchScope($request, $centerId), $model->branch_id);
            HrShiftService::cancelVersion($model, $model->versions()->whereKey($version)->firstOrFail());

            return success(null, null, __('validation.hr.change_cancelled'));
        });
    }

    /**
     * Assigns the shift to the listed employees or to every employed member of a branch (optionally making
     * it the branch default for new hires). Each employee succeeds or fails on its own.
     */
    public function assign(ShiftAssignRequest $request, int $shift): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $shift) {
            if (! can('edit_hr-employees', $request->user())) {
                return $this->denied();
            }
            $model = $this->findInCenter(Shift::class, $shift, $centerId);
            $scope = $this->branchScope($request, $centerId);
            $data = $request->validated();

            $employees = Employee::query()->forCenter($centerId)->employed()->with('user:id,name');
            if (! empty($data['branch_id'])) {
                HrOrganizationService::assertBranchInCenter($data['branch_id'], $centerId);
                HrBranchScope::assertAllowed($scope, $data['branch_id']);
                $employees->where('branch_id', (int) $data['branch_id']);
            } else {
                $employees->whereIn('id', $data['employee_ids']);
                HrBranchScope::restrict($employees, $scope, 'hr_employees.branch_id');
            }
            $employees = $employees->orderBy('id')->get();
            if (empty($data['branch_id']) && $employees->count() !== count($data['employee_ids'])) {
                throw new HrException('reference_not_in_center', 'employee_ids');
            }

            $result = HrShiftService::assignMany($model, $employees, $data, $request->user());

            if (! empty($data['branch_id']) && ($data['set_as_branch_default'] ?? false)) {
                HrShiftService::assertBranchDefault($centerId, $model->id, (int) $data['branch_id']);
                DB::transaction(fn () => Branch::query()->forCenter($centerId)->whereKey((int) $data['branch_id'])->firstOrFail()
                    ->update(['default_shift_id' => $model->id]));
            }

            return success($result, null, __('validation.hr.shift_assigned', ['count' => $result['assigned']]));
        });
    }

    public function destroy(Request $request, int $shift): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $shift) {
            $model = $this->findInCenter(Shift::class, $shift, $centerId);
            HrBranchScope::assertAllowed($this->branchScope($request, $centerId), $model->branch_id);
            HrShiftService::delete($model);

            return success(null, null, __('validation.hr.deleted'));
        });
    }

    public function restore(Request $request, int $shift): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $shift) {
            $model = $this->findInCenter(Shift::query()->onlyTrashed(), $shift, $centerId);
            HrBranchScope::assertAllowed($this->branchScope($request, $centerId), $model->branch_id);
            $model->restore();

            return success($this->present($model), null, __('validation.hr.restored'));
        });
    }

    private function setActive(Request $request, int $shift, bool $active): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $shift, $active) {
            $model = $this->findInCenter(Shift::class, $shift, $centerId);
            HrBranchScope::assertAllowed($this->branchScope($request, $centerId), $model->branch_id);
            HrShiftService::setActive($model, $active);

            return success($this->present($model), null, __($active ? 'validation.hr.shift_activated' : 'validation.hr.shift_deactivated'));
        });
    }

    private function present(Shift $shift): ShiftResource
    {
        return new ShiftResource($shift->load(['branch', 'scheduledVersion'])->loadCount('currentAssignments'));
    }
}
