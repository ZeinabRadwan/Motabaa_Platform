<?php

namespace App\Http\Controllers\Hr;

use App\Http\Requests\Hr\WorkLocationRequest;
use App\Http\Resources\Hr\PlacedEmployeeResource;
use App\Http\Resources\Hr\WorkLocationResource;
use App\Models\Hr\WorkLocation;
use App\Support\Hr\HrBranchScope;
use App\Support\Hr\HrOrganizationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkLocationController extends HrController
{
    /** Upper bound for the map endpoint (all locations at once, no pagination). */
    private const MAP_LIMIT = 1000;

    public function index(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            return $this->paginated($request, $this->filtered($request, $centerId), WorkLocationResource::class, [
                'name' => 'name_ar', 'radius_meters' => 'radius_meters', 'employees_count' => 'employees_count', 'created_at' => 'created_at',
            ]);
        });
    }

    /**
     * Every visible location matching the list filters, for the multi-location map.
     */
    public function map(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $locations = $this->filtered($request, $centerId)
                ->when(! $request->boolean('include_without_coordinates'), fn ($query) => $query->whereNotNull('latitude')->whereNotNull('longitude'))
                ->orderBy('name_ar')
                ->limit(self::MAP_LIMIT)
                ->get();

            return success(WorkLocationResource::collection($locations));
        });
    }

    public function store(WorkLocationRequest $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            HrBranchScope::assertAllowed($this->branchScope($request, $centerId), $request->validated('branch_id'));
            $location = HrOrganizationService::saveWorkLocation(new WorkLocation(), $request->validated(), $centerId, $request->user());

            return success(new WorkLocationResource($this->withSummary($location)), null, __('validation.hr.saved'));
        });
    }

    public function show(Request $request, int $workLocation): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($workLocation) {
            $model = $this->findInCenter(WorkLocation::class, $workLocation, $centerId, true);

            return success(new WorkLocationResource($this->withSummary($model)));
        });
    }

    public function update(WorkLocationRequest $request, int $workLocation): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $workLocation) {
            $model = $this->findInCenter(WorkLocation::class, $workLocation, $centerId);
            if ($request->has('branch_id')) {
                HrBranchScope::assertAllowed($this->branchScope($request, $centerId), $request->validated('branch_id'));
            }
            $model = HrOrganizationService::saveWorkLocation($model, $request->validated(), $centerId, $request->user());

            return success(new WorkLocationResource($this->withSummary($model)), null, __('validation.hr.saved'));
        });
    }

    public function activate(Request $request, int $workLocation): JsonResponse
    {
        return $this->setActive($request, $workLocation, true);
    }

    public function deactivate(Request $request, int $workLocation): JsonResponse
    {
        return $this->setActive($request, $workLocation, false);
    }

    /** Employed staff whose current work assignment is this location. */
    public function employees(Request $request, int $workLocation): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $workLocation) {
            $model = $this->findInCenter(WorkLocation::class, $workLocation, $centerId, true);

            return $this->paginated(
                $request,
                BranchController::placedEmployees($request, $centerId)->where('hr_employees.work_location_id', $model->id),
                PlacedEmployeeResource::class,
                ['employee_number' => 'hr_employees.employee_number', 'name' => 'users.name', 'assigned_since' => 'assigned_since']
            );
        });
    }

    public function destroy(Request $request, int $workLocation): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($workLocation) {
            HrOrganizationService::deleteWorkLocation($this->findInCenter(WorkLocation::class, $workLocation, $centerId));

            return success(null, null, __('validation.hr.deleted'));
        });
    }

    public function restore(Request $request, int $workLocation): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($workLocation) {
            $model = $this->findInCenter(WorkLocation::query()->onlyTrashed(), $workLocation, $centerId);
            if ($model->branch()->doesntExist()) {
                return response()->json(['errors' => ['error' => [__('validation.hr.branch_deleted')]]], 422);
            }
            $model->restore();

            return success(new WorkLocationResource($this->withSummary($model)), null, __('validation.hr.restored'));
        });
    }

    private function filtered(Request $request, int $centerId): Builder
    {
        $q = trim((string) $request->get('q', ''));
        $query = WorkLocation::query()->forCenter($centerId)
            ->with('branch:id,name_ar,name_en,is_active,deleted_at')
            ->withCount(['employees' => fn ($query) => $query->employed()])
            ->when($request->boolean('trashed'), fn ($query) => $query->onlyTrashed())
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', (int) $request->get('branch_id')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('has_coordinates'), fn ($query) => $request->boolean('has_coordinates')
                ? $query->whereNotNull('latitude')->whereNotNull('longitude')
                : $query->where(fn ($inner) => $inner->whereNull('latitude')->orWhereNull('longitude')))
            ->when($q !== '', fn ($query) => $query->where(function ($inner) use ($q) {
                $inner->where('name_ar', 'like', "%{$q}%")
                    ->orWhere('name_en', 'like', "%{$q}%")
                    ->orWhere('address', 'like', "%{$q}%");
            }));

        return HrBranchScope::restrict($query, $this->branchScope($request, $centerId), 'hr_work_locations.branch_id');
    }

    private function setActive(Request $request, int $workLocation, bool $active): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($workLocation, $active) {
            $model = HrOrganizationService::setWorkLocationActive($this->findInCenter(WorkLocation::class, $workLocation, $centerId), $active);

            return success(
                new WorkLocationResource($this->withSummary($model)),
                null,
                __($active ? 'validation.hr.work_location_activated' : 'validation.hr.work_location_deactivated')
            );
        });
    }

    private function withSummary(WorkLocation $location): WorkLocation
    {
        return $location->load('branch:id,name_ar,name_en,is_active,deleted_at')
            ->loadCount(['employees' => fn ($query) => $query->employed()]);
    }
}
