<?php

namespace App\Http\Controllers\Hr;

use App\Http\Requests\Hr\DepartmentRequest;
use App\Http\Resources\Hr\DepartmentResource;
use App\Models\Hr\Department;
use App\Support\Hr\HrOrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends HrController
{
    public function index(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $q = trim((string) $request->get('q', ''));
            $query = Department::query()->forCenter($centerId)
                ->withCount('employees')
                ->with(['parent:id,name_ar,name_en', 'manager.user:id,name'])
                ->when($request->boolean('trashed'), fn ($query) => $query->onlyTrashed())
                ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
                ->when($q !== '', fn ($query) => $query->where(function ($inner) use ($q) {
                    $inner->where('code', 'like', "%{$q}%")
                        ->orWhere('name_ar', 'like', "%{$q}%")
                        ->orWhere('name_en', 'like', "%{$q}%");
                }))
                ->orderBy('sort_order');

            return $this->paginated($request, $query, DepartmentResource::class, [
                'code' => 'code', 'name' => 'name_ar', 'sort_order' => 'sort_order',
            ]);
        });
    }

    public function store(DepartmentRequest $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $department = HrOrganizationService::saveDepartment(new Department(), $request->validated(), $centerId, $request->user());

            return success(new DepartmentResource($department), null, __('validation.hr.saved'));
        });
    }

    public function show(Request $request, int $department): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($department) {
            $model = $this->findInCenter(Department::class, $department, $centerId, true);
            $model->load(['parent:id,name_ar,name_en', 'manager.user:id,name'])->loadCount('employees');

            return success(new DepartmentResource($model));
        });
    }

    public function update(DepartmentRequest $request, int $department): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $department) {
            $model = $this->findInCenter(Department::class, $department, $centerId);
            $model = HrOrganizationService::saveDepartment($model, $request->validated(), $centerId, $request->user());

            return success(new DepartmentResource($model), null, __('validation.hr.saved'));
        });
    }

    public function destroy(Request $request, int $department): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($department) {
            HrOrganizationService::deleteDepartment($this->findInCenter(Department::class, $department, $centerId));

            return success(null, null, __('validation.hr.deleted'));
        });
    }

    public function restore(Request $request, int $department): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($department) {
            $model = $this->findInCenter(Department::query()->onlyTrashed(), $department, $centerId);
            $model->restore();

            return success(new DepartmentResource($model), null, __('validation.hr.restored'));
        });
    }
}
