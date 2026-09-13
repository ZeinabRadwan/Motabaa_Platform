<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LoadAuthUserRelations
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user) {
            $user->loadMissing(['centers', 'roles']);
        }

        return $next($request);
    }
}
