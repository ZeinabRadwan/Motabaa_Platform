<?php

namespace App\Http\Controllers\Hr;

use App\Http\Requests\Hr\AttendancePolicyRequest;
use App\Http\Resources\Hr\AttendancePolicyResource;
use App\Http\Resources\Hr\AttendancePolicyVersionResource;
use App\Models\Hr\AttendancePolicy;
use App\Models\Hr\Employee;
use App\Support\Hr\HrAttendancePolicyService;
use App\Support\Hr\HrBranchScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendancePolicyController extends HrController
{
    public function index(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $q = trim((string) $request->get('q', ''));
            $query = AttendancePolicy::query()->forCenter($centerId)
                ->with('scheduledVersion.creator:id,name')
                ->withCount('currentConfigs')
                ->withExists('attendanceConfigs')
                ->when($request->boolean('trashed'), fn ($query) => $query->onlyTrashed())
                ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
                ->when($request->filled('gps_requirement'), fn ($query) => $query->where('gps_requirement', (string) $request->get('gps_requirement')))
                ->when($q !== '', fn ($query) => $query->where(function ($inner) use ($q) {
                    $inner->where('code', 'like', "%{$q}%")
                        ->orWhere('name_ar', 'like', "%{$q}%")
                        ->orWhere('name_en', 'like', "%{$q}%");
                }));

            return $this->paginated($request, $query, AttendancePolicyResource::class, [
                'code' => 'code',
                'name' => 'name_ar',
                'late_grace_minutes' => 'late_grace_minutes',
                'gps_requirement' => 'gps_requirement',
                'employees_count' => 'current_configs_count',
                'created_at' => 'created_at',
            ]);
        });
    }

    public function store(AttendancePolicyRequest $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $policy = HrAttendancePolicyService::create($request->validated(), $centerId, $request->user());

            return success($this->present($policy), null, __('validation.hr.saved'));
        });
    }

    public function show(Request $request, int $policy): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($policy) {
            return success($this->present($this->findInCenter(AttendancePolicy::class, $policy, $centerId, true)));
        });
    }

    public function update(AttendancePolicyRequest $request, int $policy): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $policy) {
            [$model, $scheduled] = HrAttendancePolicyService::update(
                $this->findInCenter(AttendancePolicy::class, $policy, $centerId),
                $request->validated(),
                $request->user()
            );

            $message = $scheduled !== null
                ? __('validation.hr.policy_change_scheduled', ['date' => $scheduled->effective_from->toDateString()])
                : __('validation.hr.saved');

            return success($this->present($model), null, $message);
        });
    }

    /** PATCH: requires admin_hr-attendance-setup. */
    public function activate(Request $request, int $policy): JsonResponse
    {
        return $this->setActive($request, $policy, true);
    }

    /** PATCH: requires admin_hr-attendance-setup. Refused while employees use the policy. */
    public function deactivate(Request $request, int $policy): JsonResponse
    {
        return $this->setActive($request, $policy, false);
    }

    /** Rule history: every version, latest first. */
    public function versions(Request $request, int $policy): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($policy) {
            $model = $this->findInCenter(AttendancePolicy::class, $policy, $centerId, true);

            return success(AttendancePolicyVersionResource::collection(
                $model->versions()->with('creator:id,name')->orderByDesc('effective_from')->get()
            ));
        });
    }

    public function cancelVersion(Request $request, int $policy, int $version): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($policy, $version) {
            $model = $this->findInCenter(AttendancePolicy::class, $policy, $centerId);
            HrAttendancePolicyService::cancelVersion($model, $model->versions()->whereKey($version)->firstOrFail());

            return success(null, null, __('validation.hr.change_cancelled'));
        });
    }

    /**
     * Employed employees (within the user's branches) whose attendance configuration uses the policy today or
     * from a scheduled date.
     */
    public function employees(Request $request, int $policy): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $policy) {
            $model = $this->findInCenter(AttendancePolicy::class, $policy, $centerId, true);
            $today = today()->toDateString();
            $search = trim((string) $request->get('q', ''));

            $configs = $model->attendanceConfigs()
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today))
                ->whereHas('employee', function ($q) use ($request, $centerId, $search) {
                    $q->employed();
                    HrBranchScope::restrict($q, $this->branchScope($request, $centerId), 'hr_employees.branch_id');
                    $q->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search) {
                        $inner->where('employee_number', 'like', "%{$search}%")
                            ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%"));
                    }));
                })
                ->with(['employee.user:id,name', 'employee.branch:id,name_ar,name_en,deleted_at'])
                ->orderBy('effective_from')
                ->paginate(resolvePerPage($request), ['*'], 'page', max(1, (int) $request->input('options.page', $request->get('page', 1))));

            $rows = $configs->getCollection()->map(function ($config) use ($today) {
                /** @var Employee $employee */
                $employee = $config->employee;

                return [
                    'config_id' => $config->id,
                    'employee_id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'name' => $employee->user?->name,
                    'branch' => $employee->branch?->name,
                    'attendance_mode' => $config->attendance_mode,
                    'attendance_mode_label' => Employee::attendanceModeLabels()[$config->attendance_mode] ?? $config->attendance_mode,
                    'effective_from' => $config->effective_from->toDateString(),
                    'effective_to' => $config->effective_to?->toDateString(),
                    'is_scheduled' => $config->effective_from->toDateString() > $today,
                ];
            });

            return apiPaginateResponse($configs, $rows->values());
        });
    }

    public function destroy(Request $request, int $policy): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($policy) {
            HrAttendancePolicyService::delete($this->findInCenter(AttendancePolicy::class, $policy, $centerId));

            return success(null, null, __('validation.hr.deleted'));
        });
    }

    public function restore(Request $request, int $policy): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($policy) {
            $model = $this->findInCenter(AttendancePolicy::query()->onlyTrashed(), $policy, $centerId);
            $model->restore();

            return success($this->present($model), null, __('validation.hr.restored'));
        });
    }

    private function setActive(Request $request, int $policy, bool $active): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($policy, $active) {
            $model = HrAttendancePolicyService::setActive($this->findInCenter(AttendancePolicy::class, $policy, $centerId), $active);

            return success($this->present($model), null, __($active ? 'validation.hr.policy_activated' : 'validation.hr.policy_deactivated'));
        });
    }

    private function present(AttendancePolicy $policy): AttendancePolicyResource
    {
        return new AttendancePolicyResource($policy->load('scheduledVersion.creator:id,name')->loadCount('currentConfigs')->loadExists('attendanceConfigs'));
    }
}
