<?php
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return [
    'api' => [
        EnsureFrontendRequestsAreStateful::class,
        'throttle:api',
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ],
];
