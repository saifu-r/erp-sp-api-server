<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = $request->user();

        if (!$user || !in_array($permission, $user->getPermissionCodes())) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}