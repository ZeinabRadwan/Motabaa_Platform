<?php

namespace App\Http\Controllers\Hr;

use App\Http\Requests\Hr\LeaveBalanceAdjustRequest;
use App\Http\Resources\Hr\EmployeeReference;
use App\Http\Resources\Hr\LeaveBalanceResource;
use App\Http\Resources\Hr\LeaveBalanceTransactionResource;
use App\Models\Hr\Employee;
use App\Models\Hr\LeaveBalance;
use App\Support\Hr\HrBranchScope;
use App\Support\Hr\HrException;
use App\Support\Hr\HrLeaveBalances;
use App\Support\Hr\HrLeaveRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Leave balances per employee and year (module hr-leave, within the user's branches). Balances are created on
 * first use; adjustments need edit_hr-leave and a reason, and are kept in the balance ledger.
 */
class LeaveBalanceController extends HrController
{
    public function index(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $year = $this->year($request);
            $q = trim((string) $request->get('q', ''));
            $query = Employee::query()->forCenter($centerId)
                ->employed()
                ->with(['user:id,name,hire_date', 'employmentPeriods'])
                ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', (int) $request->get('branch_id')))
                ->when($request->filled('employee_id'), fn ($query) => $query->whereKey((int) $request->get('employee_id')))
                ->when($q !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('employee_number', 'like', "%{$q}%")
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$q}%"))))
                ->orderBy('employee_number');
            HrBranchScope::restrict($query, $this->branchScope($request, $centerId), 'hr_employees.branch_id');

            $paginator = $query->paginate(resolvePerPage($request), ['*'], 'page', max(1, (int) data_get($request->get('options'), 'page', $request->get('page', 1))));
            $rows = collect($paginator->items())->map(fn (Employee $employee) => [
                'employee' => EmployeeReference::make($employee),
                'year' => $year,
                'balances' => LeaveBalanceResource::collection(HrLeaveBalances::overview($employee, $year))->resolve(),
            ]);

            return apiPaginateResponse($paginator, $rows->values());
        });
    }

    public function transactions(Request $request, int $balance): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $balance) {
            $model = LeaveBalance::query()->forCenter($centerId)->with(['employee', 'leaveType'])->findOrFail($balance);
            if (! HrBranchScope::allows($this->branchScope($request, $centerId), $model->employee?->branch_id)) {
                throw new HrException('not_found', 'error', 404);
            }
            $transactions = $model->transactions()->with('creator:id,name')->orderByDesc('id')->get();

            return success([
                'balance' => (new LeaveBalanceResource($model))->resolve(),
                'transactions' => LeaveBalanceTransactionResource::collection($transactions)->resolve(),
            ]);
        });
    }

    public function adjust(LeaveBalanceAdjustRequest $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            if (! canAny(['edit_hr-leave', 'admin_hr-leave'])) {
                return $this->denied();
            }
            $data = $request->validated();
            $employee = $this->findInCenter(Employee::class, (int) $data['employee_id'], $centerId);
            $type = HrLeaveRequests::leaveType($centerId, $data['leave_type_id']);
            if (! $type->tracksBalance()) {
                throw new HrException('leave_type_without_balance', 'leave_type_id');
            }

            $balance = DB::transaction(function () use ($employee, $type, $data, $request) {
                $balance = HrLeaveBalances::balanceFor($employee, $type, (int) $data['year'], true, $request->user());

                return HrLeaveBalances::adjust($balance, (float) $data['days'], trim($data['reason']), $request->user());
            });

            return success(new LeaveBalanceResource($balance->load('leaveType')), null, __('validation.hr.saved'));
        });
    }

    private function year(Request $request): int
    {
        $year = (int) $request->get('year', now()->year);

        return $year >= 2000 && $year <= 2100 ? $year : now()->year;
    }
}
