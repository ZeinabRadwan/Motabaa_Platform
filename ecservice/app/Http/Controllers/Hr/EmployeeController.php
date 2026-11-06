<?php

namespace App\Http\Controllers\Hr;

use App\Http\Requests\Hr\EmployeeLifecycleRequest;
use App\Http\Requests\Hr\EmployeeRequest;
use App\Http\Resources\Hr\EmployeeChangeResource;
use App\Http\Resources\Hr\EmployeeProfileResource;
use App\Http\Resources\Hr\EmployeeResource;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeChange;
use App\Models\Hr\EmploymentPeriod;
use App\Support\Hr\HrBranchScope;
use App\Support\Hr\HrContext;
use App\Support\Hr\HrEmployeeService;
use App\Support\Hr\HrEmploymentLifecycle;
use App\Support\Hr\HrException;
use App\Support\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EmployeeController extends HrController
{
    private const RELATIONS = [
        'user:id,name,email,phone,image_path,nationality,id_or_residence_number,job_title,contract_type,hire_date,contract_end_date,id_expiry_date,deleted_at',
        'branch:id,name_ar,name_en,deleted_at',
        'department:id,name_ar,name_en,deleted_at',
        'workLocation',
        'manager.user:id,name',
    ];

    public const PROFILE_RELATIONS = [
        'user',
        'branch:id,name_ar,name_en,deleted_at',
        'department:id,name_ar,name_en,deleted_at',
        'workLocation',
        'attendancePolicy',
        'manager.user:id,name',
    ];

    public function index(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $q = trim((string) $request->get('q', ''));
            $keywords = $q === '' ? '' : mb_ereg_replace(' ', '%', getFTS($q));
            $ids = array_map('intval', parseAutocompleteRequest($request)['ids']);

            $query = Employee::query()->forCenter($centerId)
                ->select('hr_employees.*')
                ->join('users', 'users.id', '=', 'hr_employees.user_id')
                ->with(self::RELATIONS)
                ->when($request->filled('branch_id'), fn ($query) => $query->where('hr_employees.branch_id', (int) $request->get('branch_id')))
                ->when($request->filled('work_location_id'), fn ($query) => $query->where('hr_employees.work_location_id', (int) $request->get('work_location_id')))
                ->when($request->filled('department_id'), fn ($query) => $query->where('hr_employees.department_id', (int) $request->get('department_id')))
                ->when($request->filled('employment_status'), fn ($query) => $query->where('hr_employees.employment_status', $request->get('employment_status')))
                ->when($request->filled('attendance_mode'), fn ($query) => $query->where('hr_employees.attendance_mode', $request->get('attendance_mode')))
                ->when($request->filled('job_title'), fn ($query) => $query->where('users.job_title', (string) $request->get('job_title')))
                ->when($request->filled('manager_id'), fn ($query) => $query->where('hr_employees.manager_id', (int) $request->get('manager_id')))
                ->when($request->boolean('employed_only'), fn ($query) => $query->employed())
                ->when($q !== '' || $ids !== [], fn ($query) => $query->where(function ($inner) use ($q, $keywords, $ids) {
                    if ($q !== '') {
                        $inner->where('hr_employees.employee_number', 'like', "%{$q}%")
                            ->orWhere('users.id_or_residence_number', 'like', "%{$q}%")
                            ->orWhere('users.search_text', 'like', "%{$keywords}%");
                    }
                    if ($ids !== []) {
                        $inner->orWhereIn('hr_employees.id', $ids);
                    }
                }))
                ->when(empty($request->input('options.sortBy')), fn ($query) => $query->orderBy('hr_employees.employee_number'));
            HrBranchScope::restrict($query, $this->branchScope($request, $centerId), 'hr_employees.branch_id');

            return $this->paginated($request, $query, EmployeeResource::class, [
                'employee_number' => 'hr_employees.employee_number',
                'name' => 'users.name',
                'id_or_residence_number' => 'users.id_or_residence_number',
                'job_title' => 'users.job_title',
                'hire_date' => 'users.hire_date',
                'employment_start_date' => 'hr_employees.employment_start_date',
                'attendance_mode' => 'hr_employees.attendance_mode',
                'employment_status' => 'hr_employees.employment_status',
                'created_at' => 'hr_employees.created_at',
            ]);
        });
    }

    /**
     * Staff users of the center without an HR profile, with their current personal data to prefill the form.
     */
    public function candidates(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $q = trim((string) $request->get('q', ''));
            $keywords = $q === '' ? '' : mb_ereg_replace(' ', '%', getFTS($q));
            $ids = array_map('intval', parseAutocompleteRequest($request)['ids']);
            $users = HrEmployeeService::candidatesQuery($centerId)
                ->when($q !== '', fn ($query) => $query->where('users.search_text', 'like', "%{$keywords}%"))
                ->when($q === '' && $ids !== [], fn ($query) => $query->orderByRaw('CASE WHEN users.id IN ('.implode(',', $ids).') THEN 0 ELSE 1 END'))
                ->orderBy('users.name')
                ->limit(resolveSelectLimit($request, 20, 50))
                ->get(array_merge(['users.id'], array_map(fn ($field) => "users.{$field}", HrEmployeeService::USER_FIELDS)));

            return success($users->map(fn ($user) => array_merge(
                ['id' => $user->id],
                $user->only(HrEmployeeService::USER_FIELDS),
                [
                    'birthdate' => $user->birthdate ? substr((string) $user->birthdate, 0, 10) : null,
                    'hire_date' => $user->hire_date?->toDateString(),
                    'contract_end_date' => $user->contract_end_date?->toDateString(),
                    'id_expiry_date' => $user->id_expiry_date?->toDateString(),
                ]
            ))->values());
        });
    }

    /**
     * Distinct job titles of the center's employees (filter options).
     */
    public function jobTitles(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) {
            return success(self::jobTitleOptions($centerId));
        });
    }

    public static function jobTitleOptions(int $centerId): array
    {
        return Employee::query()->forCenter($centerId)
            ->join('users', 'users.id', '=', 'hr_employees.user_id')
            ->whereNotNull('users.job_title')
            ->where('users.job_title', '!=', '')
            ->distinct()
            ->orderBy('users.job_title')
            ->pluck('users.job_title')
            ->map(fn ($title) => ['value' => $title, 'title' => $title])
            ->values()
            ->all();
    }

    public function store(EmployeeRequest $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $data = $request->validated();
            // The attendance setup of a new hire needs the same permission as changing it later.
            if (! can(PermissionCatalog::HR_ATTENDANCE_CONFIG_PERMISSION, $request->user())) {
                if (($data['attendance_mode'] ?? Employee::ATTENDANCE_MANUAL) !== Employee::ATTENDANCE_MANUAL
                    || ! empty($data['attendance_policy_id']) || isset($data['grace_minutes'])) {
                    throw new HrException('attendance_config_forbidden', 'attendance_mode', 403);
                }
                unset($data['gps_policy']);
            }
            $employee = HrEmployeeService::register($centerId, $data, $request->user());

            return success(new EmployeeProfileResource($employee->load(self::PROFILE_RELATIONS)), null, __('validation.hr.saved'));
        });
    }

    public function show(Request $request, int $employee): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($employee) {
            $model = $this->findInCenter(Employee::class, $employee, $centerId);

            return success(new EmployeeProfileResource($model->load(self::PROFILE_RELATIONS)));
        });
    }

    public function update(EmployeeRequest $request, int $employee): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $employee) {
            $model = $this->findInCenter(Employee::class, $employee, $centerId);
            $model = HrEmployeeService::update($model, $request->validated(), $request->user());

            return success(new EmployeeProfileResource($model->fresh()->load(self::PROFILE_RELATIONS)), null, __('validation.hr.saved'));
        });
    }

    public function deactivate(EmployeeLifecycleRequest $request, int $employee): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $employee) {
            $model = HrEmploymentLifecycle::deactivate($this->findInCenter(Employee::class, $employee, $centerId), $request->validated(), $request->user());

            return success(new EmployeeProfileResource($model->load(self::PROFILE_RELATIONS)), null, __('validation.hr.employee_deactivated'));
        });
    }

    public function activate(EmployeeLifecycleRequest $request, int $employee): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $employee) {
            $model = HrEmploymentLifecycle::activate($this->findInCenter(Employee::class, $employee, $centerId), $request->validated(), $request->user());

            return success(new EmployeeProfileResource($model->load(self::PROFILE_RELATIONS)), null, __('validation.hr.employee_activated'));
        });
    }

    public function updatePhoto(Request $request, int $employee): JsonResponse
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120']]);

        return $this->inCenter($request, function (int $centerId) use ($request, $employee) {
            $model = $this->findInCenter(Employee::class, $employee, $centerId);
            HrEmployeeService::updatePhoto($model, $request->file('photo'));

            return success(['photo_url' => EmployeeResource::photoUrl($model->user->fresh())], null, __('validation.hr.saved'));
        });
    }

    public function removePhoto(Request $request, int $employee): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($employee) {
            HrEmployeeService::removePhoto($this->findInCenter(Employee::class, $employee, $centerId));

            return success(['photo_url' => null], null, __('validation.hr.saved'));
        });
    }

    /**
     * Employee timeline, newest effective date first. Financial entries require hr-compensation access.
     */
    public function history(Request $request, int $employee): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $employee) {
            $model = $this->findInCenter(Employee::class, $employee, $centerId);
            $types = array_filter((array) $request->get('change_types', $request->filled('change_type') ? [$request->get('change_type')] : []));

            $query = EmployeeChange::query()->forCenter($centerId)
                ->where('employee_id', $model->id)
                ->with('changer:id,name')
                ->when(! HrContext::canViewCompensation($request->user()), fn ($query) => $query->whereNotIn('change_type', EmployeeChange::FINANCIAL_TYPES))
                ->when($types !== [], fn ($query) => $query->whereIn('change_type', $types))
                ->orderByDesc('effective_date')
                ->orderByDesc('id');

            return $this->paginated($request, $query, EmployeeChangeResource::class);
        });
    }

    /**
     * Employees with at least one employment day in the payroll period, with the overlapping days.
     * Eligibility only: no amounts are calculated here.
     */
    public function payrollEligibility(Request $request): JsonResponse
    {
        $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        return $this->inCenter($request, function (int $centerId) use ($request) {
            $start = Carbon::parse($request->get('period_start'))->startOfDay();
            $end = Carbon::parse($request->get('period_end'))->startOfDay();

            $query = Employee::query()->forCenter($centerId)
                ->eligibleForPayrollPeriod($start, $end)
                ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', (int) $request->get('branch_id')))
                ->with(['user:id,name', 'employmentPeriods' => fn ($query) => $query->overlapping($start->toDateString(), $end->toDateString())->orderBy('start_date')])
                ->orderBy('employee_number');
            $employees = HrBranchScope::restrict($query, $this->branchScope($request, $centerId), 'hr_employees.branch_id')->get();

            return success($employees->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'name' => $employee->user?->name,
                'employment_status' => $employee->employment_status,
                'intervals' => $employee->employmentPeriods->map(fn (EmploymentPeriod $period) => [
                    'from' => $period->start_date->max($start)->toDateString(),
                    'to' => ($period->end_date === null ? $end : $period->end_date->min($end))->toDateString(),
                ])->values(),
            ])->values());
        });
    }
}
