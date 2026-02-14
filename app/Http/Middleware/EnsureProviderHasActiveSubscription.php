<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProviderHasActiveSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->serviceProvider) {
            return response()->json([
                'success' => false,
                'message' => 'You must be a service provider to access this resource.'
            ], 403);
        }

        if (!$user->serviceProvider->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your provider account is inactive. Please contact support.',
                'error_code' => 'ACCOUNT_INACTIVE'
            ], 403);
        }

        if (!$user->serviceProvider->hasActiveSubscription()) {
            return response()->json([
                'success' => false,
                'message' => 'Active subscription required.',
                'error_code' => 'SUBSCRIPTION_REQUIRED',
                'action' => 'SUBSCRIBE',
                'redirect_to' => '/my-services/subscription'
            ], 403);
        }

        return $next($request);
    }
}
