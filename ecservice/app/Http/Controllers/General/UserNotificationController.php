<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class UserNotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        if (! $user || ! Schema::hasTable('notifications')) {
            return apiResponse(['data' => []]);
        }

        $perPage = min(50, max(1, (int) ($request->per_page ?? 20)));

        $notifications = $user->notifications()
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $items = $notifications->getCollection()->map(function ($notification) {
            $data = is_array($notification->data) ? $notification->data : [];

            return [
                'id' => $notification->id,
                'title' => $data['title'] ?? '',
                'message' => $data['message'] ?? '',
                'url' => $data['url'] ?? null,
                'task_id' => $data['task_id'] ?? null,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ];
        });

        return apiPaginateResponse($notifications->setCollection($items), $items);
    }

    public function markRead(Request $request, string $id)
    {
        $user = auth()->user();
        if (! $user) {
            return error(403);
        }

        $notification = $user->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();
        }

        return apiResponse(['status' => true]);
    }
}
