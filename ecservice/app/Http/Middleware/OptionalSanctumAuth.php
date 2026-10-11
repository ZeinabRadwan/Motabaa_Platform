<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class OptionalSanctumAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() && ! auth()->check()) {
            $accessToken = PersonalAccessToken::findToken($request->bearerToken());
            if ($accessToken?->tokenable) {
                auth()->setUser($accessToken->tokenable);
            }
        }

        return $next($request);
    }
}
