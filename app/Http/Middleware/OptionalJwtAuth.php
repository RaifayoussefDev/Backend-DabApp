<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Résout l'utilisateur authentifié si un token JWT valide est présent, mais
 * ne bloque JAMAIS la requête quand il n'y en a pas (ou qu'il est expiré /
 * invalide). Permet le "mode invité" : la même route sert les visiteurs et
 * les utilisateurs connectés, et le contrôleur adapte sa réponse via Auth::user().
 */
class OptionalJwtAuth
{
    public function handle(Request $request, Closure $next)
    {
        try {
            if ($request->bearerToken()) {
                JWTAuth::parseToken()->authenticate();
            }
        } catch (\Throwable $e) {
            // Token absent, expiré ou invalide → on continue en tant qu'invité.
        }

        return $next($request);
    }
}
