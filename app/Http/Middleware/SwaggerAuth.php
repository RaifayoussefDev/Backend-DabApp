<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SwaggerAuth
{
    public function handle(Request $request, Closure $next)
    {
        $username = config('swagger-auth.user');
        $password = config('swagger-auth.password');

        // Never log credentials: only whether they matched.
        Log::info('SwaggerAuth attempt', [
            'user_match'     => $request->getUser() === $username,
            'password_match' => $request->getPassword() === $password,
        ]);

        if ($request->getUser() != $username || $request->getPassword() != $password) {
            return response('Unauthorized.', 401, ['WWW-Authenticate' => 'Basic']);
        }

        return $next($request);
    }
}
