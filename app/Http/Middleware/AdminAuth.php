<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;

class AdminAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            // ✅ Parser et authentifier le token JWT
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                Log::warning('AdminAuth: User not found', [
                    'token' => substr($request->bearerToken(), 0, 20) . '...'
                ]);
                return response()->json(['error' => 'User not found'], 404);
            }

            // ✅ Vérifier si l'utilisateur est actif
            if (!$user->is_active) {
                Log::warning('AdminAuth: Inactive user attempted access', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
                return response()->json(['error' => 'User account is inactive'], 403);
            }

            // ✅ OPTIONNEL : Vérifier si l'utilisateur est admin (décommente si nécessaire)
            // if (!$user->is_admin) {
            //     Log::warning('AdminAuth: Non-admin user attempted access', [
            //         'user_id' => $user->id,
            //         'email' => $user->email
            //     ]);
            //     return response()->json(['error' => 'Access denied. Admin privileges required.'], 403);
            // }

            // ✅ Attacher l'utilisateur authentifié à la requête
            $request->setUserResolver(function () use ($user) {
                return $user;
            });

            Log::info('AdminAuth: Authentication successful', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            return $next($request);

        } catch (TokenExpiredException $e) {
            Log::warning('AdminAuth: Token expired', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'error' => 'Token expired',
                'message' => 'Please refresh your token or login again'
            ], 401);

        } catch (TokenInvalidException $e) {
            Log::warning('AdminAuth: Token invalid', [
                'error' => $e->getMessage(),
                'token' => substr($request->bearerToken(), 0, 20) . '...'
            ]);
            return response()->json([
                'error' => 'Token invalid',
                'message' => 'Please login again'
            ], 401);

        } catch (JWTException $e) {
            Log::warning('AdminAuth: Token absent or malformed', [
                'error' => $e->getMessage(),
                'has_authorization_header' => $request->hasHeader('Authorization')
            ]);
            return response()->json([
                'error' => 'Token not provided',
                'message' => 'Authorization token is required'
            ], 401);

        } catch (\Exception $e) {
            Log::error('AdminAuth: Unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Authentication failed',
                'message' => 'An unexpected error occurred'
            ], 500);
        }
    }
}
