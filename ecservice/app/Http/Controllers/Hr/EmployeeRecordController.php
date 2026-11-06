<?php

namespace App\Http\Controllers\Hr;

use App\Http\Requests\Hr\EmployeeInsuranceRequest;
use App\Http\Requests\Hr\EmployeeSalaryRequest;
use App\Http\Requests\Hr\EmployeeShiftRequest;
use App\Http\Requests\Hr\EmployeeWorkAssignmentRequest;
use App\Http\Resources\Hr\EmployeeInsuranceResource;
use App\Http\Resources\Hr\EmployeeSalaryResource;
use App\Http\Resources\Hr\EmployeeShiftAssignmentResource;
use App\Http\Resources\Hr\EmployeeWorkAssignmentResource;
use App\Models\Hr\Employee;
use App\Support\Hr\HrEmployeeRecords;
use App\Support\Hr\HrWorkAssignments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Effective-dated employee records. Rows are immutable: a correction is a new row, and only a change that
 * has not taken effect yet can be cancelled.
 */
class EmployeeRecordController extends HrController
{
    public function salaries(Request $request, int $employee): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($employee) {
            $model = $this->findInCenter(Employee::class, $employee, $centerId);

            return success(EmployeeSalaryResource::collection(
                $model->salaries()->with('creator:id,name')->orderByDesc('effective_from')->get()
            ));
        });
    }

    public function storeSalary(EmployeeSalaryRequest $request, int $employee): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $employee) {
            $row = HrEmployeeRecords::addSalary($this->findInCenter(Employee::class, $employee, $centerId), $request->validated(), $request->user());

            return success(new EmployeeSalaryResource($row->load('creator:id,name')), null, __('validation.hr.saved'));
        });
    }

    public function cancelSalary(Request $request, int $employee, int $salary): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($employee, $salary) {
            $model = $this->findInCenter(Employee::class, $employee, $centerId);
            HrEmployeeRecords::cancelSalary($model, $model->salaries()->whereKey($salary)->firstOrFail());

            return success(null, null, __('validation.hr.change_cancelled'));
        });
    }

    public function insurances(Request $request, int $employee): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($employee) {
            $model = $this->findInCenter(Employee::class, $employee, $centerId);

            return success(EmployeeInsuranceResource::collection(
                $model->insurances()->with('creator:id,name')->orderByDesc('effective_from')->get()
            ));
        });
    }

    public function storeInsurance(EmployeeInsuranceRequest $request, int $employee): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $employee) {
            $row = HrEmployeeRecords::addInsurance($this->findInCenter(Employee::class, $employee, $centerId), $request->validated(), $request->user());

            return success(new EmployeeInsuranceResource($row->load('creator:id,name')), null, __('validation.hr.saved'));
        });
    }

    public function cancelInsurance(Request $request, int $employee, int $insurance): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($employee, $insurance) {
            $model = $this->findInCenter(Employee::class, $employee, $centerId);
            HrEmployeeRecords::cancelInsurance($model, $model->insurances()->whereKey($insurance)->firstOrFail());

            return success(null, null, __('validation.hr.change_cancelled'));
        });
    }

    public function shifts(Request $request, int $employee): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($employee) {
            $model = $this->findInCenter(Employee::class, $employee, $centerId);

            return success(EmployeeShiftAssignmentResource::collection(
                $model->shiftAssignments()->with(['shift', 'creator:id,name'])->orderByDesc('effective_from')->get()
            ));
        });
    }

    public function storeShift(EmployeeShiftRequest $request, int $employee): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $employee) {
            $row = HrEmployeeRecords::assignShift($this->findInCenter(Employee::class, $employee, $centerId), $request->validated(), $request->user());

            return success(new EmployeeShiftAssignmentResource($row->load(['shift', 'creator:id,name'])), null, __('validation.hr.saved'));
        });
    }

    public function cancelShift(Request $request, int $employee, int $assignment): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($employee, $assignment) {
            $model = $this->findInCenter(Employee::class, $employee, $centerId);
            HrEmployeeRecords::cancelShift($model, $model->shiftAssignments()->with('shift')->whereKey($assignment)->firstOrFail());

            return success(null, null, __('validation.hr.change_cancelled'));
        });
    }

    /** Branch and work location history, newest first (branch transfers included). */
    public function workAssignments(Request $request, int $employee): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($employee) {
            $model = $this->findInCenter(Employee::class, $employee, $centerId);

            return success(EmployeeWorkAssignmentResource::collection(
                $model->workAssignments()->with(['branch', 'workLocation', 'creator:id,name'])->orderByDesc('effective_from')->get()
            ));
        });
    }

    /** Branch transfer or work location change from an effective date (past, today or scheduled). */
    public function storeWorkAssignment(EmployeeWorkAssignmentRequest $request, int $employee): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $employee) {
            $row = HrWorkAssignments::transfer($this->findInCenter(Employee::class, $employee, $centerId), $request->validated(), $request->user());

            return success(new EmployeeWorkAssignmentResource($row->load(['branch', 'workLocation', 'creator:id,name'])), null, __('validation.hr.saved'));
        });
    }

    public function cancelWorkAssignment(Request $request, int $employee, int $assignment): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($employee, $assignment) {
            $model = $this->findInCenter(Employee::class, $employee, $centerId);
            HrWorkAssignments::cancel($model, $model->workAssignments()->with(['branch', 'workLocation'])->whereKey($assignment)->firstOrFail());

            return success(null, null, __('validation.hr.change_cancelled'));
        });
    }
}
