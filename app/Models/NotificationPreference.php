<?php
// app/Models/NotificationPreference.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        // Listings
        'listing_approved',
        'listing_rejected',
        'listing_expired',
        'listing_sold',
        // Auctions
        'bid_placed',
        'bid_accepted',
        'bid_rejected',
        'bid_outbid',
        'auction_ending_soon',
        // Payments
        'payment_success',
        'payment_failed',
        'payment_pending',
        // Wishlist
        'wishlist_price_drop',
        'wishlist_item_sold',
        // Messages
        'new_message',
        // Guides
        'new_guide_published',
        'guide_comment',
        'guide_like',
        // Events
        'event_reminder',
        'event_updated',
        'event_cancelled',
        // POI
        'poi_review',
        'new_poi_nearby',
        // Routes
        'route_comment',
        'route_warning',
        // System
        'system_updates',
        'promotional',
        'newsletter',
        'admin_custom',
        // Canaux
        'push_enabled',
        'in_app_enabled',
        'email_enabled',
        'sms_enabled',
        // Quiet hours
        'quiet_hours_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
        // Push settings
        'push_vibration',
        'push_sound',
        'push_badge',
        'push_priority',
    ];

    protected $casts = [
        'listing_approved' => 'boolean',
        'listing_rejected' => 'boolean',
        'listing_expired' => 'boolean',
        'listing_sold' => 'boolean',
        'bid_placed' => 'boolean',
        'bid_accepted' => 'boolean',
        'bid_rejected' => 'boolean',
        'bid_outbid' => 'boolean',
        'auction_ending_soon' => 'boolean',
        'payment_success' => 'boolean',
        'payment_failed' => 'boolean',
        'payment_pending' => 'boolean',
        'wishlist_price_drop' => 'boolean',
        'wishlist_item_sold' => 'boolean',
        'new_message' => 'boolean',
        'new_guide_published' => 'boolean',
        'guide_comment' => 'boolean',
        'guide_like' => 'boolean',
        'event_reminder' => 'boolean',
        'event_updated' => 'boolean',
        'event_cancelled' => 'boolean',
        'poi_review' => 'boolean',
        'new_poi_nearby' => 'boolean',
        'route_comment' => 'boolean',
        'route_warning' => 'boolean',
        'system_updates' => 'boolean',
        'promotional' => 'boolean',
        'newsletter' => 'boolean',
        'admin_custom' => 'boolean',
        'push_enabled' => 'boolean',
        'in_app_enabled' => 'boolean',
        'email_enabled' => 'boolean',
        'sms_enabled' => 'boolean',
        'quiet_hours_enabled' => 'boolean',
        'push_vibration' => 'boolean',
        'push_sound' => 'boolean',
        'push_badge' => 'boolean',
    ];

    // Relations
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Methods
    public function isNotificationEnabled(string $type): bool
    {
        return $this->{$type} ?? false;
    }

    public function isQuietHours(): bool
    {
        if (!$this->quiet_hours_enabled) {
            return false;
        }

        if (!$this->quiet_hours_start || !$this->quiet_hours_end) {
            return false;
        }

        $now = Carbon::now()->format('H:i:s');
        $start = $this->quiet_hours_start;
        $end = $this->quiet_hours_end;

        // Si start < end: vérifier si now est entre les deux
        if ($start < $end) {
            return $now >= $start && $now <= $end;
        } else {
            // Si les heures traversent minuit (ex: 22:00 - 08:00)
            return $now >= $start || $now <= $end;
        }
    }

    public function canSendPush(): bool
    {
        return $this->push_enabled && !$this->isQuietHours();
    }

    public function canSendEmail(): bool
    {
        return $this->email_enabled;
    }

    public function canSendSms(): bool
    {
        return $this->sms_enabled;
    }

    public function enableAll(): void
    {
        $this->update([
            'listing_approved' => true,
            'listing_rejected' => true,
            'listing_expired' => true,
            'listing_sold' => true,
            'bid_placed' => true,
            'bid_accepted' => true,
            'bid_rejected' => true,
            'bid_outbid' => true,
            'auction_ending_soon' => true,
            'payment_success' => true,
            'payment_failed' => true,
            'payment_pending' => true,
            'wishlist_price_drop' => true,
            'wishlist_item_sold' => true,
            'new_message' => true,
            'push_enabled' => true,
            'in_app_enabled' => true,
        ]);
    }

    public function disableAll(): void
    {
        $this->update([
            'listing_approved' => false,
            'listing_rejected' => false,
            'listing_expired' => false,
            'listing_sold' => false,
            'bid_placed' => false,
            'bid_accepted' => false,
            'bid_rejected' => false,
            'bid_outbid' => false,
            'auction_ending_soon' => false,
            'payment_success' => false,
            'payment_failed' => false,
            'payment_pending' => false,
            'wishlist_price_drop' => false,
            'wishlist_item_sold' => false,
            'new_message' => false,
            'push_enabled' => false,
            'in_app_enabled' => false,
        ]);
    }
}
