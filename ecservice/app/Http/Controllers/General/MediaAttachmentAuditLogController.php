<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaAttachmentAuditLogResource;
use App\Models\MediaAttachmentAuditLog;
use Illuminate\Http\Request;

class MediaAttachmentAuditLogController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $perPage = resolvePerPage($request, 10, 100);

        $centerId = null;
        if ($request->center_id && $user->isInCenter($request->center_id)) {
            $centerId = (int) $request->center_id;
        } elseif ($user->centers && isset($user->centers[0]->id)) {
            $centerId = (int) $user->centers[0]->id;
        }

        $query = MediaAttachmentAuditLog::query()
            ->with('user:id,name')
            ->where('center_id', $centerId)
            ->when(
                $request->operation,
                fn ($q) => $q->where('operation', $request->operation)
            )
            ->when(
                $request->outcome,
                fn ($q) => $q->where('outcome', $request->outcome)
            )
            ->when(
                $request->user_id,
                fn ($q) => $q->where('user_id', $request->user_id)
            )
            ->when(
                $request->q,
                function ($q) use ($request) {
                    $term = '%'.$request->q.'%';
                    $q->where(function ($inner) use ($term) {
                        $inner->where('original_filename', 'like', $term)
                            ->orWhere('stored_filename', 'like', $term)
                            ->orWhere('failure_reason', 'like', $term)
                            ->orWhere('attachable_id', 'like', $term)
                            ->orWhere('correlation_id', 'like', $term);
                    });
                }
            )
            ->orderByDesc('id');

        $logs = $query->paginate($perPage);

        return apiPaginateResponse($logs, MediaAttachmentAuditLogResource::collection($logs));
    }
}
