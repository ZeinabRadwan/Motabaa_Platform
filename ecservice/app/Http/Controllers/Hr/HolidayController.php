<?php

namespace App\Http\Controllers\Hr;

use App\Http\Requests\Hr\HolidayRequest;
use App\Http\Resources\Hr\HolidayResource;
use App\Models\Hr\Branch;
use App\Models\Hr\Holiday;
use App\Support\Hr\HrAttendanceProcessor;
use App\Support\Hr\HrBranchScope;
use App\Support\Hr\HrException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Official and company holidays (module hr-attendance-setup). Center-wide holidays need access to every branch;
 * branch holidays need access to that branch. Attendance days already processed in the holiday's range are
 * reprocessed after every change.
 */
class HolidayController extends HrController
{
    private const RELATIONS = ['branch:id,name_ar,name_en,deleted_at', 'creator:id,name'];

    public function index(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $q = trim((string) $request->get('q', ''));
            $query = Holiday::query()->forCenter($centerId)
                ->with(self::RELATIONS)
                ->when($request->filled('year'), fn ($query) => $query->where(fn ($inner) => $inner
                    ->whereYear('date_from', (int) $request->get('year'))
                    ->orWhereYear('date_to', (int) $request->get('year'))))
                ->when($request->filled('from'), fn ($query) => $query->whereDate('date_to', '>=', (string) $request->get('from')))
                ->when($request->filled('to'), fn ($query) => $query->whereDate('date_from', '<=', (string) $request->get('to')))
                ->when($request->filled('holiday_type'), fn ($query) => $query->where('holiday_type', (string) $request->get('holiday_type')))
                ->when($request->filled('branch_id'), fn ($query) => $query->where(fn ($inner) => $inner
                    ->whereNull('branch_id')
                    ->orWhere('branch_id', (int) $request->get('branch_id'))))
                ->when($q !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('name_ar', 'like', "%{$q}%")
                    ->orWhere('name_en', 'like', "%{$q}%")))
                ->orderBy('date_from')
                ->orderBy('id');

            $scope = $this->branchScope($request, $centerId);
            if ($scope !== null) {
                $query->where(fn ($inner) => $inner->whereNull('branch_id')->orWhereIn('branch_id', $scope));
            }

            return $this->paginated($request, $query, HolidayResource::class, [
                'date_from' => 'date_from', 'holiday_type' => 'holiday_type',
            ]);
        });
    }

    public function store(HolidayRequest $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $data = $request->validated();
            $this->assertValid($request, $centerId, $data);

            $holiday = DB::transaction(function () use ($centerId, $data, $request) {
                $holiday = Holiday::create($data + [
                    'center_id' => $centerId,
                    'is_paid' => $data['is_paid'] ?? true,
                    'created_by' => $request->user()->id,
                ]);
                HrAttendanceProcessor::refreshProcessed($centerId, $holiday->date_from, $holiday->date_to);

                return $holiday;
            });

            return success(new HolidayResource($holiday->load(self::RELATIONS)), null, __('validation.hr.saved'));
        });
    }

    public function update(HolidayRequest $request, int $holiday): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $holiday) {
            $model = $this->editable($request, $centerId, $holiday);
            $data = $request->validated();
            $merged = array_merge([
                'branch_id' => $model->branch_id,
                'date_from' => $model->date_from->toDateString(),
                'date_to' => $model->date_to->toDateString(),
            ], $data);
            $this->assertValid($request, $centerId, $merged);

            DB::transaction(function () use ($model, $data, $centerId) {
                $from = $model->date_from->copy();
                $to = $model->date_to->copy();
                $model->fill($data)->save();
                HrAttendanceProcessor::refreshProcessed($centerId, $from->min($model->date_from), $to->max($model->date_to));
            });

            return success(new HolidayResource($model->load(self::RELATIONS)), null, __('validation.hr.saved'));
        });
    }

    public function destroy(Request $request, int $holiday): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $holiday) {
            $model = $this->editable($request, $centerId, $holiday);

            DB::transaction(function () use ($model, $centerId) {
                $model->delete();
                HrAttendanceProcessor::refreshProcessed($centerId, $model->date_from, $model->date_to);
            });

            return success(null, null, __('validation.hr.deleted'));
        });
    }

    private function editable(Request $request, int $centerId, int $id): Holiday
    {
        $model = Holiday::query()->forCenter($centerId)->findOrFail($id);
        $scope = $this->branchScope($request, $centerId);
        $model->branch_id === null
            ? HrBranchScope::assertUnrestricted($scope)
            : HrBranchScope::assertAllowed($scope, $model->branch_id, 'error');

        return $model;
    }

    private function assertValid(Request $request, int $centerId, array $data): void
    {
        $scope = $this->branchScope($request, $centerId);
        $branchId = $data['branch_id'] ?? null;
        if ($branchId === null) {
            HrBranchScope::assertUnrestricted($scope);
        } else {
            if (! Branch::query()->forCenter($centerId)->whereKey($branchId)->exists()) {
                throw new HrException('branch_not_found', 'branch_id');
            }
            HrBranchScope::assertAllowed($scope, $branchId);
        }

        if (Carbon::parse($data['date_from'])->diffInDays(Carbon::parse($data['date_to'])) + 1 > Holiday::MAX_DAYS) {
            throw new HrException('holiday_too_long', 'date_to', 422, ['days' => Holiday::MAX_DAYS]);
        }
    }
}
