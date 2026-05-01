<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SwaggerAuth
{
    public function handle(Request $request, Closure $next)
    {
        $username = config('swagger-auth.user', env('SWAGGER_USER', 'admin'));
        $password = config('swagger-auth.password', env('SWAGGER_PASSWORD', 'secret'));

        Log::info('SwaggerAuth attempt', [
            'user_match'     => $request->getUser() === $username,
            'password_match' => $request->getPassword() === $password,
            'got_user'       => $request->getUser(),
            'got_pass'       => $request->getPassword(),
            'expected_user'  => $username,
            'expected_pass'  => $password,
        ]);

        if ($request->getUser() != $username || $request->getPassword() != $password) {
            return response('Unauthorized.', 401, ['WWW-Authenticate' => 'Basic']);
        }

        return $next($request);
    }
}
