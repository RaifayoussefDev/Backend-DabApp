<?php

use App\Http\Middleware\OwnCors;
use App\Http\Middleware\SwaggerAuth;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckAdminAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
