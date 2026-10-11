<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use App\Http\Requests\CenterActivityEntryRequest;
use App\Http\Resources\CenterActivityEntryResource;
use App\Models\CenterActivity;
use App\Models\CenterActivityEntry;
use App\Models\Log;
use App\Support\CenterActivityAccess;
use Illuminate\Http\Request;

class CenterActivityEntryController extends Controller
{
    public function index(Request $request, $activityId)
    {
        $user = auth()->user();
        $activity = CenterActivity::find($activityId);

        if (! $activity) {
            return error(404);
        }

        if (CenterActivityAccess::isParentAccount($user)) {
            return error(403);
        }

        if (! CenterActivityAccess::userCanViewActivity($user, $activity, $request->center_id)) {
            return error(403);
        }

        $perPage = resolvePerPage($request, 10, 50);

        $entries = CenterActivityEntry::with(['user:id,name,image_path', 'user.roles:id,name,default_name'])
            ->where('center_activity_id', $activity->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return apiPaginateResponse($entries, CenterActivityEntryResource::collection($entries));
    }

    public function parentFeed(Request $request)
    {
        $user = auth()->user();

        if (! CenterActivityAccess::isParentAccount($user)) {
            return error(403);
        }

        $centerId = \App\Models\Term::resolveCenterId($user, $request->center_id);
        if (! $centerId) {
            return response()->json(['errors' => ['error' => [__("validation.You don't belong to any center")]]], 422);
        }

        $perPage = resolvePerPage($request, 10, 50);

        $termIds = CenterActivityAccess::parentVisibleTermIds($user);
        if ($termIds === []) {
            $termIds = [-1];
        }

        $entries = CenterActivityEntry::with([
            'user:id,name,image_path',
            'user.roles:id,name,default_name',
            'activity:id,title,center_id,term_id',
            'activity.term:id,title',
        ])
            ->where('parents_can_see', 1)
            ->whereHas('activity', fn ($q) => $q
                ->where('center_id', $centerId)
                ->whereIn('term_id', $termIds)
            )
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return apiPaginateResponse($entries, CenterActivityEntryResource::collection($entries));
    }

    public function put(CenterActivityEntryRequest $request, $activityId)
    {
        $user = auth()->user();

        if (CenterActivityAccess::isParentAccount($user)) {
            return error(403);
        }

        $activity = CenterActivity::find($activityId);
        if (! $activity || ! CenterActivityAccess::userCanViewActivity($user, $activity, $request->center_id)) {
            return error(403);
        }

        if (! CenterActivityAccess::userCanManageContent($user)) {
            return error(403);
        }

        $input = $request->validated();
        $entry = CenterActivityEntry::create([
            'center_activity_id' => $activity->id,
            'user_id' => $user->id,
            'content' => $input['content'],
            'parents_can_see' => ! empty($input['parents_can_see']) ? 1 : 0,
        ]);

        $auditContext = [
            'center_id' => $request->integer('center_id') ?: ($activity->center_id ?? null),
            'user_id' => $user->id,
        ];

        $attachmentMeta = null;
        try {
            if ($request->hasFile('image') || $request->hasFile('file') || $request->hasFile('video')) {
                ini_set('max_execution_time', '600');
            }
            if ($request->hasFile('image')) {
                $attachmentMeta = $entry->saveFile($request->file('image'), 'image', $auditContext);
            } elseif ($request->hasFile('file')) {
                $attachmentMeta = $entry->saveFile($request->file('file'), 'file', $auditContext);
            } elseif ($request->hasFile('video')) {
                $attachmentMeta = $entry->saveFile($request->file('video'), 'video', $auditContext);
            }
        } catch (\Throwable $e) {
            \Log::error('Center activity entry upload failed', [
                'entry_id' => $entry->id,
                'error' => $e->getMessage(),
            ]);
            $field = $request->hasFile('video') ? 'video' : ($request->hasFile('image') ? 'image' : 'file');
            try {
                $entry->deleteFolder($auditContext);
            } catch (\Throwable $cleanupError) {
                \Log::error('Center activity entry upload cleanup failed', [
                    'entry_id' => $entry->id,
                    'error' => $cleanupError->getMessage(),
                ]);
            }
            $entry->delete();

            return response()->json([
                'errors' => [
                    $field => [__('File upload failed.')],
                ],
            ], 422);
        }

        Log::add('add_center_activity_entry', $entry, $entry->id, '');

        $entry->load(['user.roles:id,name,default_name']);

        return success([
            'id' => $entry->id,
            'entry' => new CenterActivityEntryResource($entry),
            'attachment' => $attachmentMeta,
        ]);
    }

    public function delete(Request $request, $entryId)
    {
        $user = auth()->user();
        $entry = CenterActivityEntry::with('activity')->find($entryId);

        if (! $entry || ! $entry->activity) {
            return error(404);
        }

        if (! CenterActivityAccess::userCanViewActivity($user, $entry->activity, $request->center_id)) {
            return error(403);
        }

        if (! CenterActivityAccess::userCanDeleteEntry($user, $entry)) {
            return error(403);
        }

        $auditContext = [
            'center_id' => $request->integer('center_id') ?: ($entry->activity->center_id ?? null),
            'user_id' => $user->id,
        ];

        try {
            $entry->deleteFolder($auditContext);
        } catch (\Throwable $e) {
            \Log::error('Center activity entry delete storage failed', [
                'entry_id' => $entry->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'errors' => [
                    'delete' => [__('File delete failed.')],
                ],
            ], 422);
        }

        $entry->delete();
        Log::add('delete_center_activity_entry', $entry, $entry->id, '');

        return success();
    }

    public function file(Request $request, CenterActivityEntry $entry)
    {
        $user = auth()->user();
        $entry->load('activity');

        if (! $entry->activity) {
            return error(404);
        }

        $parent = CenterActivityAccess::isParentAccount($user);

        if ($parent) {
            if (! CenterActivityAccess::parentCanAccessEntry($user, $entry)) {
                return error(403);
            }
        } elseif (! CenterActivityAccess::userCanViewActivity($user, $entry->activity, $request->center_id)) {
            return error(403);
        }

        if ($parent && ! $entry->parents_can_see) {
            return error(403);
        }

        $fileURL = $entry->urlFile();
        if ($fileURL) {
            return apiResponse($fileURL);
        }

        $imageURL = $entry->urlImage();
        if ($imageURL) {
            return apiResponse(['file' => $imageURL, 'is_video' => false]);
        }

        $videoURL = $entry->urlVideo();
        if ($videoURL) {
            return apiResponse(['file' => $videoURL, 'is_video' => true]);
        }

        return apiResponse(null);
    }
}
