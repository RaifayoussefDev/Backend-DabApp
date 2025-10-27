<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SwaggerAuth
{
    public function handle(Request $request, Closure $next)
    {
        $username = env('SWAGGER_USER', 'admin');
        $password = env('SWAGGER_PASSWORD', 'secret');

        if ($request->getUser() != $username || $request->getPassword() != $password) {
            return response('Unauthorized.', 401, ['WWW-Authenticate' => 'Basic']);
        }

        return $next($request);
    }
}
