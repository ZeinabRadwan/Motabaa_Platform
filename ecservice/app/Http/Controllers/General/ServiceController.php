<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Support\ReferenceCache;


class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $center = null;
        if($request->center_id && $user->isInCenter($request->center_id)) {
            $center = $request->center_id;
        }
        else if($user->centers){ 
            $center = $user->centers[0]->id;
        }

        $payload = ReferenceCache::remember('services', 'c'.($center ?: 'none').':'.app()->getLocale(), function () use ($center) {
            $types = Service::where(function ($subQuery) use ($center) {
                $subQuery->whereNull('center_id');
                $subQuery->orWhere('center_id', $center);
            })
            ->get();

            return ReferenceCache::payload(ServiceResource::collection($types));
        });

        return apiResponse($payload);
    }
}
