<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Log;
use App\Models\LogType;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\LogResource;
use App\Support\ReferenceCache;

class LogController extends Controller
{

    public function index(Request $request) {

        $perPage = resolvePerPage($request);
        $textSearch = mb_ereg_replace(" ", "%", getFTS($request->q));

        $user = auth()->user();
        $center = null;
        if($request->center_id && $user->isInCenter($request->center_id)) {
            $center = $request->center_id;
        }
        else if($user->centers){ 
            $center = $user->centers[0]->id;
        }

        $logs = Log::with('createdBy:id,name')
        ->where('center_id', $center)
        ->when(
            $request->q,
            fn ($q) => $q->Where(\DB::raw("COALESCE(search_text,'')"), "like", "%$textSearch%")
        )
        ->when(
            $request->action_by,
            fn ($q) => $q->Where("created_by", $request->action_by)
        )
        ->when(
            $request->action_type,
            fn ($q) => $q->Where("model_type", $request->action_type)
        )
        ->orderBy('id', 'DESC')
        ->paginate($perPage);

        Log::preloadModelNames($logs->getCollection());

        return apiPaginateResponse($logs, LogResource::collection($logs));
    }

    public function models(Request $request) {

        $user = auth()->user();
        $center = null;
        if($request->center_id && $user->isInCenter($request->center_id)) {
            $center = $request->center_id;
        }
        else if($user->centers){ 
            $center = $user->centers[0]->id;
        }

        $payload = ReferenceCache::remember(
            'logs',
            'models:c'.($center ?: 'none').':'.app()->getLocale(),
            function () use ($center) {
                $data = [];
                $models = Log::selectRaw('distinct model_type')->where('center_id', $center)->pluck('model_type')->toArray();
                foreach ($models as $model) {
                    $name = Log::modelType($model)['type_name'];
                    $data[] = [
                        'id'=> $model,
                        'name'=> __("tr.logs.{$name}")
                    ];
                }

                return $data;
            }
        );

        return apiResponse($payload);
    }

    public function types(Request $request) {

        $types = ReferenceCache::remember('logs', 'types', function () {
            return LogType::select('category')->groupBy('category')->get()->toArray();
        }, 300);

        return apiResponse($types);
    }

    public function statistics(Request $request) {

        $user = auth()->user();
        $center = null;
        if($request->center_id && $user->isInCenter($request->center_id)) {
            $center = $request->center_id;
        }
        else if($user->centers){ 
            $center = $user->centers[0]->id;
        }

        $recentLogs = Log::selectRaw('logs.created_by as id, users.name as name, count(*) as count')
        ->join('users', 'users.id', 'logs.created_by')
        ->where('logs.center_id', $center)
        ->when(
            $request->category && $request->category != 'all',
            fn ($q) => $q->join('logs_types', function($join) use($request) {
                $join->on('logs_types.type', 'logs.type');
                $join->Where('category', $request->category);
            })
        )
        ->whereRaw('logs.created_at > DATE_ADD(NOW(), INTERVAL -1 MONTH)')
        ->groupBy('logs.created_by')->groupBy('users.name')
        ->orderBy('count', 'DESC')
        ->limit(25)
        ->get()
        ->toArray();
        return apiResponse($recentLogs);
    }
}
