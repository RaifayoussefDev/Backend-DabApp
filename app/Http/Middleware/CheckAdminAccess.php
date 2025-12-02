<?php
// app/Http/Middleware/CheckAdminAccess.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckAdminAccess
{
    /**
     * Handle an incoming request.
     * Block users with role_id = 2 from accessing admin panel
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Block regular users (role_id = 2) from admin panel
        if ($user->role_id === 2) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied - Admin privileges required'
            ], 403);
        }

        return $next($request);
    }
}
