<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Observers\EventObserver;
use App\Observers\EventParticipantObserver;
use App\Services\FirebaseService;
use App\Services\NotificationService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\EventNotificationService::class);
        $this->app->singleton(FirebaseService::class, function ($app) {
            return new FirebaseService();
        });
        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService($app->make(FirebaseService::class));
        });
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
