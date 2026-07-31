<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckActiveUser
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && (int) $user->status !== 1) {
            $user->tokens()->delete();
            return response()->json(['message' => 'Account is inactive.'], 403);
        }
        return $next($request);
    }
}
