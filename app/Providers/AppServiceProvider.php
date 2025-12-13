<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Observers\EventObserver;
use App\Observers\EventParticipantObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\EventNotificationService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::observe(EventObserver::class);
        EventParticipant::observe(EventParticipantObserver::class);
    }
}
