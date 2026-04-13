<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * AssistServiceProvider
 *
 * Bootstraps the Velocity Assist module.
 * Routes are registered directly in routes/api.php under /api/assist.
 */
class AssistServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void {}
}
