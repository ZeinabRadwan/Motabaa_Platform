<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Disability;
use App\Http\Resources\DisabilityResource;
use App\Models\Log;
use App\Support\ReferenceCache;

class DisabilityController extends Controller
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

        $payload = ReferenceCache::remember('disabilities', 'c'.($center ?: 'none').':'.app()->getLocale(), function () use ($center) {
            $types = Disability::where(function ($subQuery) use ($center) {
                $subQuery->whereNull('center_id');
                $subQuery->orWhere('center_id', $center);
            })
            ->get();

            return ReferenceCache::payload(DisabilityResource::collection($types));
        });

        return apiResponse($payload);
    }
    public function put(Request $request){

        $request->validate([
            'name' => 'required|string',
        ]);

        $center = null;
        $user = auth()->user();
        if($request->center_id && $user->isInCenter($request->center_id)) {
            $center = $request->center_id;
        }

        $disability = Disability::updateOrCreate( 
            [ 
                'name_ar'=> $request->input('name') 
            ] , 
            [
                'center_id'=> $center,
                'name_ar'=> $request->input('name'),
                'name_en'=> $request->input('name')
            ]
        );
        Log::add('put_disability', $disability, $disability->id, '');
        ReferenceCache::bump('disabilities');
        return apiResponse(new DisabilityResource($disability));
    }  
}
