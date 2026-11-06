<?php

namespace App\Http\Controllers\Hr;

use App\Http\Resources\Hr\AuditLogResource;
use App\Models\Hr\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends HrController
{
    public function index(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $query = AuditLog::query()
                ->where('center_id', $centerId)
                ->with('user:id,name')
                ->when($request->filled('auditable_type'), fn ($query) => $query->where('auditable_type', $request->get('auditable_type')))
                ->when($request->filled('auditable_id'), fn ($query) => $query->where('auditable_id', (int) $request->get('auditable_id')))
                ->when($request->filled('event'), fn ($query) => $query->where('event', $request->get('event')))
                ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', (int) $request->get('user_id')))
                ->when($request->filled('date_from'), fn ($query) => $query->where('created_at', '>=', $request->date('date_from')->startOfDay()))
                ->when($request->filled('date_to'), fn ($query) => $query->where('created_at', '<=', $request->date('date_to')->endOfDay()))
                ->orderByDesc('id');

            return $this->paginated($request, $query, AuditLogResource::class);
        });
    }
}
