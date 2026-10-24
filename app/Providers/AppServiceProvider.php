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
use Illuminate\Support\Facades\URL;
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

        // TLS ends at Cloudflare; the app receives plain http through the tunnel/Traefik.
        // Generate https links whenever the configured APP_URL is https.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
