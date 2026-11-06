<?php

namespace App\Http\Controllers\Hr;

use App\Http\Requests\Hr\ShiftExceptionRequest;
use App\Http\Resources\Hr\ShiftExceptionResource;
use App\Models\Hr\ShiftException;
use App\Support\Hr\HrBranchScope;
use App\Support\Hr\HrShiftExceptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shift exceptions and temporary schedule changes. Users limited to some branches see exceptions of their
 * branches and employees plus center-wide ones, and can only manage the former.
 */
class ShiftExceptionController extends HrController
{
    private const RELATIONS = ['employee.user:id,name', 'branch:id,name_ar,name_en,deleted_at', 'shift', 'creator:id,name'];

    public function index(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $q = trim((string) $request->get('q', ''));
            $query = ShiftException::query()->forCenter($centerId)
                ->with(self::RELATIONS)
                ->when($request->filled('from'), fn ($query) => $query->where('date_to', '>=', (string) $request->get('from')))
                ->when($request->filled('to'), fn ($query) => $query->where('date_from', '<=', $request->get('to').' 23:59:59'))
                ->when($request->filled('exception_type'), fn ($query) => $query->where('exception_type', (string) $request->get('exception_type')))
                ->when($request->filled('employee_id'), fn ($query) => $query->where('employee_id', (int) $request->get('employee_id')))
                ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', (int) $request->get('branch_id')))
                ->when($request->filled('scope'), fn ($query) => match ((string) $request->get('scope')) {
                    ShiftException::SCOPE_EMPLOYEE => $query->whereNotNull('employee_id'),
                    ShiftException::SCOPE_BRANCH => $query->whereNull('employee_id')->whereNotNull('branch_id'),
                    default => $query->whereNull('employee_id')->whereNull('branch_id'),
                })
                ->when($request->get('period') === 'upcoming', fn ($query) => $query->where('date_to', '>=', today()->toDateString()))
                ->when($request->get('period') === 'past', fn ($query) => $query->where('date_to', '<', today()->toDateString()))
                ->when($q !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('reason', 'like', "%{$q}%")
                    ->orWhereHas('employee', fn ($employee) => $employee->where('employee_number', 'like', "%{$q}%")
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$q}%")))))
                ->orderByDesc('date_from')
                ->orderByDesc('id');
            $this->restrictToScope($query, $this->branchScope($request, $centerId));

            return $this->paginated($request, $query, ShiftExceptionResource::class, [
                'date_from' => 'date_from', 'date_to' => 'date_to', 'exception_type' => 'exception_type',
            ]);
        });
    }

    public function store(ShiftExceptionRequest $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $exception = HrShiftExceptions::create($centerId, $request->validated(), $request->user());

            return success(new ShiftExceptionResource($exception->load(self::RELATIONS)), null, __('validation.hr.saved'));
        });
    }

    public function cancel(Request $request, int $exception): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $exception) {
            $model = ShiftException::query()->forCenter($centerId)->with('employee')->whereKey($exception)->firstOrFail();
            $scope = $this->branchScope($request, $centerId);
            match ($model->targetScope()) {
                ShiftException::SCOPE_EMPLOYEE => HrBranchScope::assertAllowed($scope, $model->employee?->branch_id, 'error'),
                ShiftException::SCOPE_BRANCH => HrBranchScope::assertAllowed($scope, $model->branch_id, 'error'),
                default => HrBranchScope::assertUnrestricted($scope),
            };
            HrShiftExceptions::cancel($model);

            return success(null, null, __('validation.hr.change_cancelled'));
        });
    }

    private function restrictToScope(Builder $query, ?array $branchIds): void
    {
        if ($branchIds === null) {
            return;
        }
        $query->where(fn ($inner) => $inner
            ->whereIn('branch_id', $branchIds)
            ->orWhereHas('employee', fn ($employee) => $employee->whereIn('branch_id', $branchIds))
            ->orWhere(fn ($center) => $center->whereNull('employee_id')->whereNull('branch_id')));
    }
}
