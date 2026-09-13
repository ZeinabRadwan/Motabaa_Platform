<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $preferredLocale = $request->getPreferredLanguage(['en', 'ar']); // Provide the list of supported locales here

        if (!in_array($preferredLocale, ['en', 'ar'])) {
            $preferredLocale = 'ar';
        }

        app()->setLocale($preferredLocale);

        return $next($request);
    }
}
