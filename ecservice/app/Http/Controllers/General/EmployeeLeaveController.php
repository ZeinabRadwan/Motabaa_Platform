<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeLeaveResource;
use App\Models\EmployeeLeave;
use App\Models\Log;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EmployeeLeaveController extends Controller
{
    public function index(Request $request)
    {
        $perPage = resolvePerPage($request);

        $center = $this->centerId($request);
        $today = Carbon::now()->toDateString();

        $leaves = EmployeeLeave::with(['user', 'createdBy', 'reviewedBy'])
            ->whereHas('user.centers', function ($query) use ($center) {
                $query->where('centers.id', $center);
            })
            ->when(
                $request->user_id,
                fn ($q) => $q->where('user_id', $request->user_id)
            )
            ->when(
                $request->type,
                fn ($q) => $q->where('type', $request->type)
            )
            ->when(
                $request->status,
                fn ($q) => $q->where('status', $request->status)
            )
            ->when(
                $request->current == '1' || $request->current == 1,
                fn ($q) => $q->where('status', 'approved')
                    ->whereDate('date_from', '<=', $today)
                    ->whereDate('date_to', '>=', $today)
            )
            ->when(
                $request->date_from && $request->date_to,
                fn ($q) => $q->where(function ($query) use ($request) {
                    $query->whereBetween('date_from', [$request->date_from, $request->date_to])
                        ->orWhereBetween('date_to', [$request->date_from, $request->date_to])
                        ->orWhere(function ($inner) use ($request) {
                            $inner->where('date_from', '<=', $request->date_from)
                                ->where('date_to', '>=', $request->date_to);
                        });
                })
            )
            ->orderByDesc('date_from')
            ->orderByDesc('id');

        $leaves = $leaves->paginate($perPage);

        $payload = apiPaginateResponse($leaves, EmployeeLeaveResource::collection($leaves));

        if ($request->user_id) {
            $employee = User::find($request->user_id);
            if ($employee) {
                $data = $payload->getData(true);
                $data['balance'] = $employee->annualLeaveBalance();
                return response()->json($data);
            }
        }

        return $payload;
    }

    public function balance(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $center = $this->centerId($request);
        $employee = User::find($request->user_id);
        if (!$employee || !$center || !$employee->isInCenter($center)) {
            return response()->json(['errors' => ['error' => [__("validation.You don't belong to this center")]]], 422);
        }

        return success($employee->annualLeaveBalance());
    }

    public function put(Request $request)
    {
        $input = $request->validate([
            'id' => 'nullable|integer|exists:employee_leaves,id',
            'user_id' => 'required|exists:users,id',
            'type' => 'required|in:annual,sick,unpaid,emergency,other',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'notes' => 'nullable|string|max:1000',
        ]);

        $authUser = auth()->user();
        $center = $this->centerId($request);

        $employee = User::find($input['user_id']);
        if (!$employee || !$center || !$employee->isInCenter($center)) {
            return response()->json(['errors' => ['error' => [__("validation.You don't belong to this center")]]], 422);
        }

        $input['days'] = Carbon::parse($input['date_from'])->diffInDays(Carbon::parse($input['date_to'])) + 1;

        if (!empty($input['id'])) {
            $leave = EmployeeLeave::find($input['id']);
            if ($leave->status === 'approved' && $input['type'] === 'annual') {
                $error = $this->annualBalanceError($employee, $input['days'], $input['date_from'], $leave->id);
                if ($error) {
                    return $error;
                }
            }

            $leave->update([
                'user_id' => $input['user_id'],
                'type' => $input['type'],
                'date_from' => $input['date_from'],
                'date_to' => $input['date_to'],
                'days' => $input['days'],
                'notes' => $input['notes'] ?? null,
            ]);
            Log::add('edit_employee_leave', $leave, $leave->id, '');
        } else {
            $input['status'] = 'pending';
            $input['created_by'] = $authUser->id;
            $leave = EmployeeLeave::create($input);
            Log::add('add_employee_leave', $leave, $leave->id, '');
        }

        $leave->load(['user', 'createdBy', 'reviewedBy']);

        return success(new EmployeeLeaveResource($leave));
    }

    public function review(Request $request, $id)
    {
        $input = $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        $leave = EmployeeLeave::with('user')->find($id);
        if (!$leave) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }

        $center = $this->centerId($request);
        if (!$center || !$leave->user || !$leave->user->isInCenter($center)) {
            return response()->json(['errors' => ['error' => [__("validation.You don't belong to this center")]]], 422);
        }

        if ($input['status'] === 'approved' && $leave->type === 'annual') {
            $error = $this->annualBalanceError($leave->user, $leave->days, $leave->date_from?->toDateString(), $leave->id);
            if ($error) {
                return $error;
            }
        }

        $leave->status = $input['status'];
        $leave->reviewed_by = auth()->id();
        $leave->reviewed_at = now();
        $leave->save();

        Log::add(
            $input['status'] === 'approved' ? 'approve_employee_leave' : 'reject_employee_leave',
            $leave,
            $leave->id,
            ''
        );

        $leave->load(['user', 'createdBy', 'reviewedBy']);

        return success(new EmployeeLeaveResource($leave));
    }

    public function delete($id)
    {
        $leave = EmployeeLeave::find($id);
        if (!$leave) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }

        $leave->delete();
        Log::add('delete_employee_leave', $leave, $leave->id, '');

        return response()->json(['message' => 'Record deleted successfully', 'status' => true]);
    }

    private function centerId(Request $request)
    {
        $user = auth()->user();
        if ($request->center_id && $user->isInCenter($request->center_id)) {
            return $request->center_id;
        }
        if ($user->centers) {
            return $user->centers[0]->id;
        }

        return null;
    }

    private function annualBalanceError(User $employee, $days, $dateFrom, $excludeLeaveId = null)
    {
        $year = Carbon::parse($dateFrom)->year;
        $balance = $employee->annualLeaveBalance($year, $excludeLeaveId);
        if ((int) $days > $balance['remaining']) {
            return response()->json([
                'errors' => ['error' => [__('tr.employee_leaves.insufficient_balance')]],
            ], 422);
        }

        return null;
    }
}
