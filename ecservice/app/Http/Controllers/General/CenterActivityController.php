<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use App\Http\Requests\CenterActivityRequest;
use App\Http\Resources\CenterActivityResource;
use App\Models\CenterActivity;
use App\Models\Log;
use App\Models\Term;
use App\Support\CenterActivityAccess;
use Illuminate\Http\Request;

class CenterActivityController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        if (CenterActivityAccess::isParentAccount($user)) {
            return error(403);
        }

        if (! CenterActivityAccess::userCanViewModule($user)) {
            return error(403);
        }

        $centerId = Term::resolveCenterId($user, $request->center_id);
        if (! $centerId) {
            return response()->json(['errors' => ['error' => [__("validation.You don't belong to any center")]]], 422);
        }

        $keyword = mb_ereg_replace(' ', '%', getFTS($request->q));
        $perPage = resolvePerPage($request, 10, 50);
        $isAdmin = can(CenterActivityAccess::PERM_DELETE, $user);

        $query = CenterActivity::query()
            ->with(['creator:id,name', 'visibleRoles:id,name', 'term:id,title'])
            ->withCount('entries')
            ->where('center_id', $centerId)
            ->when(
                $request->q,
                fn ($q) => $q->where('title', 'like', "%{$keyword}%")
            )
            ->when(
                ! $isAdmin,
                function ($q) use ($user) {
                    $roleIds = $user->roles->pluck('id')->all();
                    $q->where(function ($inner) use ($user, $roleIds) {
                        $inner->where('created_by', $user->id)
                            ->orWhereDoesntHave('visibleRoles')
                            ->orWhereHas('visibleRoles', fn ($roles) => $roles->whereIn('roles.id', $roleIds));
                    });
                }
            )
            ->orderByDesc('created_at');

        $activities = $query->paginate($perPage);

        return apiPaginateResponse($activities, CenterActivityResource::collection($activities));
    }

    public function show(Request $request, $activityId)
    {
        $user = auth()->user();

        if (CenterActivityAccess::isParentAccount($user)) {
            return error(403);
        }

        $activity = CenterActivity::with(['creator:id,name', 'visibleRoles:id,name', 'term:id,title'])
            ->withCount('entries')
            ->find($activityId);

        if (! $activity || ! CenterActivityAccess::userCanViewActivity($user, $activity, $request->center_id)) {
            return error(403);
        }

        return apiResponse(new CenterActivityResource($activity));
    }

    public function put(CenterActivityRequest $request, $activityId = null)
    {
        $user = auth()->user();

        if (CenterActivityAccess::isParentAccount($user)) {
            return error(403);
        }

        $activity = null;
        if ($activityId && (int) $activityId > 0) {
            $activity = CenterActivity::find($activityId);
            if (! $activity || ! CenterActivityAccess::userCanEditActivity($user, $activity)) {
                return error(403);
            }
        } elseif (! CenterActivityAccess::userCanCreateActivity($user)) {
            return error(403);
        }

        $centerId = Term::resolveCenterId($user, $request->center_id);
        if (! $centerId) {
            return response()->json(['errors' => ['error' => [__("validation.You don't belong to any center")]]], 422);
        }

        if ($activity && (int) $activity->center_id !== (int) $centerId) {
            return error(403);
        }

        $input = $request->validated();

        $denied = Term::abortIfInaccessible($user, $centerId, $input['term_id']);
        if ($denied) {
            return $denied;
        }

        $term = Term::find($input['term_id']);
        if (! $term || (int) $term->center_id !== (int) $centerId) {
            return response()->json(['errors' => ['term_id' => [__('validation.exists', ['attribute' => 'term_id'])]]], 422);
        }

        $payload = [
            'title' => $input['title'],
            'center_id' => $centerId,
            'term_id' => $input['term_id'],
        ];

        if (! $activity) {
            $payload['created_by'] = $user->id;
        }

        $activity = CenterActivity::updateOrCreate(['id' => $activity?->id], $payload);

        if ($request->has('role_ids')) {
            $activity->visibleRoles()->sync($request->input('role_ids', []));
        }

        Log::add($activityId && (int) $activityId > 0 ? 'edit_center_activity' : 'add_center_activity', $activity, $activity->id, '');

        return success(['id' => $activity->id]);
    }

    public function delete($id)
    {
        $user = auth()->user();

        if (! CenterActivityAccess::userCanDeleteActivity($user)) {
            return error(403);
        }

        $activity = CenterActivity::with('entries')->find($id);
        if (! $activity || ! CenterActivityAccess::userCanViewActivity($user, $activity)) {
            return error(403);
        }

        foreach ($activity->entries as $entry) {
            $entry->deleteFolder();
            $entry->delete();
        }

        $activity->delete();
        Log::add('delete_center_activity', $activity, $activity->id, '');

        return success();
    }
}
