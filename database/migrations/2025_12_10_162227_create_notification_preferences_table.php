<?php
// database/migrations/2024_12_08_000003_create_notification_preferences_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Listings
            $table->boolean('listing_approved')->default(true);
            $table->boolean('listing_rejected')->default(true);
            $table->boolean('listing_expired')->default(true);
            $table->boolean('listing_sold')->default(true);

            // Auctions
            $table->boolean('bid_placed')->default(true);
            $table->boolean('bid_accepted')->default(true);
            $table->boolean('bid_rejected')->default(true);
            $table->boolean('bid_outbid')->default(true);
            $table->boolean('auction_ending_soon')->default(true);

            // Payments
            $table->boolean('payment_success')->default(true);
            $table->boolean('payment_failed')->default(true);
            $table->boolean('payment_pending')->default(true);

            // Wishlist
            $table->boolean('wishlist_price_drop')->default(true);
            $table->boolean('wishlist_item_sold')->default(true);

            // Messages
            $table->boolean('new_message')->default(true);

            // Guides
            $table->boolean('new_guide_published')->default(false);
            $table->boolean('guide_comment')->default(true);
            $table->boolean('guide_like')->default(true);

            // Events
            $table->boolean('event_reminder')->default(true);
            $table->boolean('event_updated')->default(true);
            $table->boolean('event_cancelled')->default(true);

            // POI
            $table->boolean('poi_review')->default(true);
            $table->boolean('new_poi_nearby')->default(false);

            // Routes
            $table->boolean('route_comment')->default(true);
            $table->boolean('route_warning')->default(true);

            // System
            $table->boolean('system_updates')->default(true);
            $table->boolean('promotional')->default(true);
            $table->boolean('newsletter')->default(true);
            $table->boolean('admin_custom')->default(true);

            // Canaux
            $table->boolean('push_enabled')->default(true);
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(false);

            // Quiet hours
            $table->boolean('quiet_hours_enabled')->default(false);
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();

            // Push settings
            $table->boolean('push_vibration')->default(true);
            $table->boolean('push_sound')->default(true);
            $table->boolean('push_badge')->default(true);
            $table->enum('push_priority', ['default', 'high'])->default('default');

            $table->timestamps();

            $table->unique('user_id', 'idx_user_preferences');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
