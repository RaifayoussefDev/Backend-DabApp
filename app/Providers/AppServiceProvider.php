<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Listing;
use App\Models\Payment;
use App\Observers\EventObserver;
use App\Observers\EventParticipantObserver;
use App\Observers\ListingObserver;
use App\Observers\PaymentObserver;
use App\Services\FirebaseService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Broadcast;
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
        Listing::observe(ListingObserver::class);
        Payment::observe(PaymentObserver::class);

        // Use JWT guard for WebSocket private-channel authentication
        Broadcast::routes(['middleware' => ['auth:api']]);
    }
}
