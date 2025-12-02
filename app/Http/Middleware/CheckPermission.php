<?php
// app/Http/Middleware/CheckPermission.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $permission
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $permission)
    {
        $user = auth()->user();

        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Admin role (role_id = 1) has all permissions
        if ($user->role_id === 1) {
            return $next($request);
        }

        // Check if user has the required permission
        if (!$user->hasPermission($permission)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden - You do not have permission to access this resource',
                'required_permission' => $permission
            ], 403);
        }

        return $next($request);
    }
}
