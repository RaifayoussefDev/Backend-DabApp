<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OwnCors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Liste des origines autorisées
        $allowedOrigins = [
            'https://dabapp.co',
            'http://localhost:4200',
            'http://localhost:8000', // Ajout pour le développement
            'http://127.0.0.1:8000',  // Alternative à localhost
            'https://be.dabapp.co/'
        ];

        // Headers CORS de base
        $headers = [
            'Access-Control-Allow-Methods'     => 'GET, POST, PUT, DELETE, OPTIONS, PATCH',
            'Access-Control-Allow-Headers'     => 'Origin, Content-Type, Accept, Authorization, X-Requested-With, X-Auth-Token, X-CSRF-TOKEN',
            'Access-Control-Expose-Headers'    => 'Authorization, X-Auth-Token',
            'Access-Control-Max-Age'           => 86400, // Pré-flight cache pour 24h
        ];

        // Gestion de l'origine
        $origin = $request->headers->get('Origin');
        if (in_array($origin, $allowedOrigins)) {
            $headers['Access-Control-Allow-Origin'] = $origin;
            $headers['Vary'] = 'Origin'; // Important pour le cache
        }

        // Gestion des credentials si nécessaire
        if ($request->headers->get('Authorization') ||
            $request->cookies->has('XSRF-TOKEN')) {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        // Réponse pour les requêtes OPTIONS (pré-flight)
        if ($request->isMethod('OPTIONS')) {
            return response()->json([], 204, $headers);
        }

        // Traitement de la requête normale
        $response = $next($request);

        // Ajout des headers à la réponse
        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }
}