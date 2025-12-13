<?php

use App\Http\Middleware\OwnCors;
use App\Http\Middleware\SwaggerAuth;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckAdminAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Ajouter OwnCors globalement
        $middleware->append(OwnCors::class);

        // Enregistrer tous les aliases de middleware
        $middleware->alias([
            'swagger.auth' => SwaggerAuth::class,
            'permission' => CheckPermission::class,
            'admin.access' => CheckAdminAccess::class,
            'auth.admin' => \App\Http\Middleware\AdminAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // ✅ Gérer Token Expiré (JWT)
        $exceptions->render(function (TokenExpiredException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Token expired',
                    'message' => 'Your session has expired. Please login again.'
                ], 401);
            }
        });

        // ✅ Gérer Token Invalide (JWT)
        $exceptions->render(function (TokenInvalidException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Token invalid',
                    'message' => 'Your authentication token is invalid. Please login again.'
                ], 401);
            }
        });

        // ✅ Gérer Token Absent (JWT)
        $exceptions->render(function (JWTException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Token not provided',
                    'message' => 'Authentication token is required.'
                ], 401);
            }
        });

        // ✅ Gérer Non Authentifié (Général)
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthenticated',
                    'message' => 'You must be authenticated to access this resource.'
                ], 401);
            }
        });

        // ✅ BONUS: Gérer les erreurs 403 (Forbidden - Permissions)
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Forbidden',
                    'message' => 'You do not have permission to access this resource.'
                ], 403);
            }
        });

        // ✅ BONUS: Gérer les erreurs 404 (Not Found)
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Not Found',
                    'message' => 'The requested resource was not found.'
                ], 404);
            }
        });

        // ✅ BONUS: Gérer les erreurs de validation (422)
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation Error',
                    'message' => 'The given data was invalid.',
                    'errors' => $e->errors()
                ], 422);
            }
        });

    })->create();
