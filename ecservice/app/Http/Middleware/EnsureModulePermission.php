<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureModulePermission
{
    public function handle(Request $request, Closure $next, ...$modules)
    {
        $needed = [];
        $method = strtoupper($request->method());

        foreach ($modules as $module) {
            $module = trim($module);
            if ($module === '') {
                continue;
            }

            if ($method === 'GET') {
                $needed[] = [
                    "access_{$module}",
                    "show_{$module}",
                    "edit_{$module}",
                    "admin_{$module}",
                    "apply_{$module}",
                ];
            } elseif (in_array($method, ['DELETE', 'PATCH'], true)) {
                $needed[] = ["admin_{$module}"];
            } else {
                $needed[] = ["edit_{$module}", "admin_{$module}", "apply_{$module}"];
            }
        }

        if ($needed === [] || canAny($needed)) {
            return $next($request);
        }

        return error(403);
    }
}
