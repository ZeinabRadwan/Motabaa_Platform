<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Term;
use App\Http\Resources\TermResource;
use App\Models\Log;
use App\Support\ReferenceCache;

class TermController extends Controller
{
    public function currentTerm(Request $request){

        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);

        $payload = ReferenceCache::remember('terms', 'current:c'.($center ?: 'none').':'.app()->getLocale(), function () use ($center) {
            $term = Term::currentForCenter($center);

            return $term ? ReferenceCache::payload(new TermResource($term)) : null;
        });

        return apiResponse($payload);
    }
    
    public function filterItems(Request $request){

        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);

        $exceptKey = md5(json_encode($request->except));
        $payload = ReferenceCache::remember(
            'terms',
            'items:c'.($center ?: 'none').':u'.($user->id ?? 'anon').':x'.$exceptKey.':'.app()->getLocale(),
            function () use ($center, $user, $request) {
                $terms = Term::withPeriods()->where('center_id', $center)
                ->visibleToUser($user, $center)
                ->when(
                    $request->except,
                    fn ($q) => $q->whereNotIn('id', $request->except)
                )
                ->orderByDesc('starts_at')
                ->orderByDesc('id')
                ->get();

                return ReferenceCache::payload(TermResource::collection($terms));
            }
        );

        return apiResponse($payload);
    }

    public function index(Request $request){

        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);

        if (!Term::canManageAllCenterTerms($user, $center) && !canAny(['access_qualifying-classes', 'show_qualifying-classes', 'edit_qualifying-classes', 'admin_qualifying-classes'])) {
            return Term::forbiddenResponse();
        }

        $perPage = resolvePerPage($request);

        $keywords = mb_ereg_replace(" ", "%", getFTS($request->q));

        $terms = Term::withPeriods()->where('center_id', $center)
        ->visibleToUser($user, $center)
        ->when(
            $request->q,
            fn ($q) => $q->where('title', 'like', "%{$keywords}%")
        )
        ->where(function ($query) use ($request) {
            if($request->from_date && $request->to_date) {
                $query->orWhereBetween('starts_at', [$request->from_date, $request->to_date]);
                $query->orWhereBetween('ends_at', [$request->from_date, $request->to_date]);
            }
            
            if($request->from_date) {
                $query->orWhere(function ($query) use ($request) {
                    $query->where('starts_at', '<=', $request->from_date)
                    ->where('ends_at', '>=', $request->from_date);
                });
            }
            
            if($request->to_date) {
                $query->orWhere(function ($query) use ($request) {
                    $query->where('starts_at', '<=', $request->to_date)
                    ->where('ends_at', '>=', $request->to_date);
                });
            }
        })
        ->when(
            Term::canManageAllCenterTerms($user, $center) && $this->requestedStatus($request) === 'all',
            fn ($q) => $q->withTrashed()
        )
        ->when(
            Term::canManageAllCenterTerms($user, $center) && $this->requestedStatus($request) === 'inactive',
            fn ($q) => $q->onlyTrashed()
        )->orderByDesc('starts_at')->orderByDesc('id')->paginate($perPage);
        return apiPaginateResponse($terms, TermResource::collection($terms));
    }

    public function show(Request $request, $term){

        $termQuery = Term::withPeriods()->withTrashed();
        if (Term::hasUserAssignmentTable()) {
            $termQuery->with(['users.roles']);
        }
        $term = $termQuery->find($term);

        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);

        if($term && Term::userCanAccessTerm($user, $center ?: $term->center_id, $term->id))
            $term = new TermResource($term);
        else if ($term)
            return Term::forbiddenResponse();
        else
            $term = null;

        return apiResponse($term);
    }

    public function put(Request $request){

        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);

        if (!can('edit_qualifying-classes') && !Term::canManageAllCenterTerms($user, $center)) {
            return Term::forbiddenResponse();
        }

        if(!$center)
            return response()->json(['errors' => ['error' => [__("validation.You don't belong to any center")]]], 422);

        $isNew = !($request->id>0);
        if(!$isNew)
            $term = Term::withTrashed()->find($request->id);
        else {
            $term = new Term();
            $term->center_id = $center;
        }

        if(!$isNew) {
            if(!$term || !Term::userCanAccessTerm($user, $center, $term->id))
                return Term::forbiddenResponse();
        }

        $nameExists = Term::select('terms.*')->when(
            $request->id>0,
            fn ($q) => $q->where('id', '!=', $request->id)
        )
        ->where('center_id', $center)
        ->where('title', $request->title)
        ->exists();
        if($nameExists)
            return response()->json(['errors' => ['error' => [__('validation.terms.Term Title already exists')]]], 422);

        $overlapping = Term::overlapping($center, $request->starts_at, $request->ends_at, (int) $request->id);
        if($overlapping)
            return response()->json(['errors' => ['error' => [$this->overlapMessage($overlapping)]]], 422);

        if($request->starts_at > $request->ends_at)
            return response()->json(['errors' => ['error' => [__('validation.Start Date after End Date')]]], 422);

        $evaluationDates = array_values(array_filter((array) $request->evaluation_dates, fn ($date) => !empty($date)));
        $periodsCount = (int) ($request->periods_count ?: count($evaluationDates));

        if($periodsCount < 1 || $periodsCount > 12)
            return response()->json(['errors' => ['error' => [__('validation.terms.Invalid periods count')]]], 422);

        if(count($evaluationDates) !== $periodsCount)
            return response()->json(['errors' => ['error' => [__('validation.terms.Periods dates required')]]], 422);

        foreach ($evaluationDates as $index => $date) {
            if($request->starts_at > $date || $request->ends_at < $date)
                return response()->json(['errors' => ['error' => [__('validation.terms.Evaluation date not in term range')]]], 422);

            if($index > 0 && $evaluationDates[$index - 1] > $date)
                return response()->json(['errors' => ['error' => [__('validation.terms.Evaluation dates must be in order')]]], 422);
        }

        $term->title = $request->title;
        $term->title_local = $request->title;
        $term->starts_at = $request->starts_at;
        $term->ends_at = $request->ends_at;
        if (Term::hasPeriodsCountColumn()) {
            $term->periods_count = $periodsCount;
        }
        $term->save();
        $term->syncPeriods($evaluationDates);
        $this->syncTermUsers($term, $user, $center, $request, $isNew);
        ReferenceCache::bump('terms');

        $type = 'add_term';
        if($request->id>0)
            $type = 'edit_term';

        try {
            Log::add($type, $term, $term->id, '');
        } catch (\Throwable $e) {
            report($e);
        }

        if($request->id>0)
            return response()->json(['message' => 'Updated successfully.', 'status' => true], 200);
        else
            return response()->json(['message' => 'Added successfully.', 'status' => true], 200);
    }

    public function delete($id) {

        $record = Term::find($id);
        $user = auth()->user();
        if (
            !$record
            || (!can('admin_qualifying-classes') && !Term::canManageAllCenterTerms($user, $record->center_id))
            || !Term::userCanAccessTerm($user, $record->center_id, $record->id)
        )
            return Term::forbiddenResponse();

        $record->delete();
        ReferenceCache::bump('terms');
        try {
            Log::add('delete_term', $record, $record->id, '');
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['message' => 'Deleted successfully.', 'status' => true]);
    }

    public function restore($id) {

        $record = Term::withTrashed()->find($id);
        $user = auth()->user();
        if (
            !$record
            || (!can('admin_qualifying-classes') && !Term::canManageAllCenterTerms($user, $record->center_id))
            || !Term::userCanAccessTerm($user, $record->center_id, $record->id)
        )
            return Term::forbiddenResponse();

        $nameExists = Term::withTrashed()
        ->where('id', '!=', $record->id)
        ->where('center_id', $record->center_id)
        ->where('title', $record->title)
        ->exists();
        if($nameExists)
            return response()->json(['errors' => ['error' => [__('validation.terms.Term Title already exists')]]], 422);

        $overlapping = Term::overlapping($record->center_id, $record->starts_at, $record->ends_at, $record->id);
        if($overlapping)
            return response()->json(['errors' => ['error' => [$this->overlapMessage($overlapping)]]], 422);

        $record->restore();
        ReferenceCache::bump('terms');
        try {
            Log::add('restore_term', $record, $record->id, '');
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['message' => 'Restored successfully.', 'status' => true]);
    }

    private function overlapMessage(Term $term): string
    {
        $from = substr((string) $term->starts_at, 0, 10);
        $to = substr((string) $term->ends_at, 0, 10);

        return __('validation.terms.Term date overlapped with another term')
            .' '.$term->title.' ('.$from.' - '.$to.')';
    }

    private function syncTermUsers(Term $term, $user, $center, Request $request, bool $isNew): void
    {
        if (!Term::hasUserAssignmentTable()) {
            return;
        }
        $canAssign = Term::canManageAllCenterTerms($user, $center);
        $requestedIds = $this->requestedUserIds($request);
        $assignAll = $this->requestedAssignAllUsers($term, $request, $canAssign, $isNew);

        if (Term::hasAssignAllUsersColumn() && ($canAssign || $isNew)) {
            $term->assign_all_users = $assignAll;
            $term->save();
        }

        if ($canAssign && $assignAll) {
            $term->users()->sync([]);
            return;
        }

        if ($canAssign) {
            $userIds = $this->assignableUserIds($center, $requestedIds);
            if (!$isNew) {
                $alreadyAssigned = $term->users()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
                $userIds = array_values(array_unique(array_merge(
                    $userIds,
                    array_intersect($requestedIds, $alreadyAssigned)
                )));
            }
            if ($isNew && !in_array((int) $user->id, $userIds, true)) {
                $userIds[] = (int) $user->id;
            }
            $term->users()->sync($userIds);
            return;
        }

        if ($isNew) {
            $term->users()->sync([(int) $user->id]);
        }
    }

    private function requestedAssignAllUsers(Term $term, Request $request, bool $canAssign, bool $isNew): bool
    {
        if (!Term::hasAssignAllUsersColumn() || !$canAssign) {
            return false;
        }

        if ($request->has('assign_all_users')) {
            return $request->boolean('assign_all_users');
        }

        return !$isNew && $term->assignsAllUsers();
    }

    private function requestedUserIds(Request $request): array
    {
        $ids = $request->input('user_ids', []);
        if (is_string($ids)) {
            $ids = preg_split('/\s*,\s*/', $ids, -1, PREG_SPLIT_NO_EMPTY);
        }

        return array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
    }

    private function assignableUserIds($center, array $requestedIds): array
    {
        if (!count($requestedIds)) {
            return [];
        }

        return Term::assignableStaffQuery($center)
            ->whereIn('id', $requestedIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function requestedStatus(Request $request): string
    {
        $status = $request->input('status');
        if (is_array($status)) {
            $status = $status['value'] ?? $status['status'] ?? '';
        }

        return is_string($status) ? $status : '';
    }
}
