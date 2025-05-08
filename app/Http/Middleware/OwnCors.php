<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OwnCors
{
    public function handle(Request $request, Closure $next): Response
    {
        $headers = [
            'Access-Control-Allow-Origin'      => '*',
            'Access-Control-Allow-Methods'     => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers'     => 'Origin, Content-Type, Accept, Authorization, X-Requested-With, X-Auth-Token',
            'Access-Control-Allow-Credentials' => 'true',
        ];

        if ($request->getMethod() === 'OPTIONS') {
            return response('OK', 200)->withHeaders($headers);
        }

        $response = $next($request);
        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }
}
