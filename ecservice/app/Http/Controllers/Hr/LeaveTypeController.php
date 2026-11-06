<?php

namespace App\Http\Controllers\Hr;

use App\Http\Requests\Hr\LeaveTypeRequest;
use App\Http\Resources\Hr\LeaveTypeResource;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\LeaveType;
use App\Support\Hr\HrException;
use App\Support\Hr\HrLeaveTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Leave types (module hr-leave). Viewing: any hr-leave permission; creating, editing and deleting: admin_hr-leave.
 * System types keep their code and cannot be deleted (deactivate them instead); a type that was used cannot be
 * deleted either.
 */
class LeaveTypeController extends HrController
{
    private const ADMIN_PERMISSION = 'admin_hr-leave';

    public function index(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            HrLeaveTypes::ensureDefaults($centerId);
            $types = LeaveType::query()->forCenter($centerId)
                ->when($request->boolean('active_only'), fn ($query) => $query->where('is_active', true))
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            return success(LeaveTypeResource::collection($types));
        });
    }

    public function store(LeaveTypeRequest $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            if (! can(self::ADMIN_PERMISSION)) {
                return $this->denied();
            }
            HrLeaveTypes::ensureDefaults($centerId);
            $data = $this->normalize($request->validated());
            $this->assertCodeFree($centerId, $data['code']);

            $type = LeaveType::create($data + [
                'center_id' => $centerId,
                'category' => $data['category'] ?? 'other',
                'is_system' => false,
                'created_by' => $request->user()->id,
            ]);

            return success(new LeaveTypeResource($type), null, __('validation.hr.saved'));
        });
    }

    public function update(LeaveTypeRequest $request, int $leaveType): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $leaveType) {
            if (! can(self::ADMIN_PERMISSION)) {
                return $this->denied();
            }
            $type = LeaveType::query()->forCenter($centerId)->findOrFail($leaveType);
            $data = $this->normalize($request->validated());
            if ($type->is_system) {
                unset($data['code'], $data['category']);
            } elseif (isset($data['code']) && $data['code'] !== $type->code) {
                $this->assertCodeFree($centerId, $data['code']);
            }

            $type->fill($data)->save();

            return success(new LeaveTypeResource($type), null, __('validation.hr.saved'));
        });
    }

    public function destroy(Request $request, int $leaveType): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($leaveType) {
            $type = LeaveType::query()->forCenter($centerId)->findOrFail($leaveType);
            if ($type->is_system) {
                throw new HrException('leave_type_system', 'error', 409);
            }
            if (LeaveRequest::query()->where('leave_type_id', $type->id)->exists()) {
                throw new HrException('leave_type_in_use', 'error', 409);
            }
            $type->delete();

            return success(null, null, __('validation.hr.deleted'));
        });
    }

    private function normalize(array $data): array
    {
        if (isset($data['code'])) {
            $data['code'] = strtolower(trim($data['code']));
        }
        if (($data['balance_mode'] ?? null) !== null && $data['balance_mode'] !== LeaveType::BALANCE_FIXED) {
            $data['days_per_year'] = null;
        }

        return $data;
    }

    private function assertCodeFree(int $centerId, string $code): void
    {
        if (LeaveType::withTrashed()->forCenter($centerId)->where('code', $code)->exists()) {
            throw new HrException('leave_type_code_taken', 'code');
        }
    }
}
