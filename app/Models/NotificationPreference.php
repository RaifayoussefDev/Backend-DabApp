<?php
// app/Models/NotificationPreference.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * @OA\Schema(
 *     schema="NotificationPreference",
 *     title="Notification Preference",
 *     description="User notification settings",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=10),
 *     @OA\Property(property="listing_approved", type="boolean", example=true),
 *     @OA\Property(property="listing_rejected", type="boolean", example=true),
 *     @OA\Property(property="listing_expired", type="boolean", example=true),
 *     @OA\Property(property="listing_sold", type="boolean", example=true),
 *     @OA\Property(property="bid_placed", type="boolean", example=true),
 *     @OA\Property(property="bid_accepted", type="boolean", example=true),
 *     @OA\Property(property="bid_rejected", type="boolean", example=true),
 *     @OA\Property(property="bid_outbid", type="boolean", example=true),
 *     @OA\Property(property="auction_ending_soon", type="boolean", example=true),
 *     @OA\Property(property="soom_new_negotiation", type="boolean", example=true),
 *     @OA\Property(property="soom_counter_offer", type="boolean", example=true),
 *     @OA\Property(property="soom_accepted", type="boolean", example=true),
 *     @OA\Property(property="soom_rejected", type="boolean", example=true),
 *     @OA\Property(property="dealer_approved", type="boolean", example=true),
 *     @OA\Property(property="payment_success", type="boolean", example=true),
 *     @OA\Property(property="payment_failed", type="boolean", example=true),
 *     @OA\Property(property="payment_pending", type="boolean", example=true),
 *     @OA\Property(property="wishlist_price_drop", type="boolean", example=true),
 *     @OA\Property(property="wishlist_item_sold", type="boolean", example=true),
 *     @OA\Property(property="new_message", type="boolean", example=true),
 *     @OA\Property(property="new_guide_published", type="boolean", example=true),
 *     @OA\Property(property="guide_comment", type="boolean", example=true),
 *     @OA\Property(property="guide_like", type="boolean", example=true),
 *     @OA\Property(property="event_created", type="boolean", example=true),
 *     @OA\Property(property="event_published", type="boolean", example=true),
 *     @OA\Property(property="event_reminder", type="boolean", example=true),
 *     @OA\Property(property="event_updated", type="boolean", example=true),
 *     @OA\Property(property="event_cancelled", type="boolean", example=true),
 *     @OA\Property(property="poi_review", type="boolean", example=true),
 *     @OA\Property(property="new_poi_nearby", type="boolean", example=true),
 *     @OA\Property(property="route_comment", type="boolean", example=true),
 *     @OA\Property(property="route_warning", type="boolean", example=true),
 *     @OA\Property(property="system_updates", type="boolean", example=true),
 *     @OA\Property(property="promotional", type="boolean", example=true),
 *     @OA\Property(property="newsletter", type="boolean", example=true),
 *     @OA\Property(property="admin_custom", type="boolean", example=true),
 *     @OA\Property(property="push_enabled", type="boolean", example=true),
 *     @OA\Property(property="in_app_enabled", type="boolean", example=true),
 *     @OA\Property(property="email_enabled", type="boolean", example=true),
 *     @OA\Property(property="sms_enabled", type="boolean", example=true),
 *     @OA\Property(property="quiet_hours_enabled", type="boolean", example=false),
 *     @OA\Property(property="quiet_hours_start", type="string", format="time", example="22:00:00"),
 *     @OA\Property(property="quiet_hours_end", type="string", format="time", example="08:00:00"),
 *     @OA\Property(property="push_vibration", type="boolean", example=true),
 *     @OA\Property(property="push_sound", type="boolean", example=true),
 *     @OA\Property(property="push_badge", type="boolean", example=true),
 *     @OA\Property(property="push_priority", type="string", example="high")
 * )
 */
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
        'listing_updated',

        // Auctions
        'bid_placed',
        'bid_accepted',
        'bid_rejected',
        'bid_outbid',
        'auction_ending_soon',
        // Soom (Negotiations)
        'soom_new_negotiation',
        'soom_counter_offer',
        'soom_accepted',
        'soom_rejected',
        // Dealer
        'dealer_approved',
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
        'guide_published',
        // Events
        'event_created',
        'event_published',
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
        // Trainer city-wide broadcasts
        'new_trainer_in_city',
        'new_course_in_city',
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
        'listing_updated' => 'boolean',

        'bid_placed' => 'boolean',
        'bid_accepted' => 'boolean',
        'bid_rejected' => 'boolean',
        'bid_outbid' => 'boolean',
        'auction_ending_soon' => 'boolean',
        'soom_new_negotiation' => 'boolean',
        'soom_counter_offer' => 'boolean',
        'soom_accepted' => 'boolean',
        'soom_rejected' => 'boolean',
        'dealer_approved' => 'boolean',
        'payment_success' => 'boolean',
        'payment_failed' => 'boolean',
        'payment_pending' => 'boolean',
        'wishlist_price_drop' => 'boolean',
        'wishlist_item_sold' => 'boolean',
        'new_message' => 'boolean',
        'new_guide_published' => 'boolean',
        'guide_published' => 'boolean',
        'guide_comment' => 'boolean',
        'guide_like' => 'boolean',
        'event_created' => 'boolean',
        'event_published' => 'boolean',
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
        'new_trainer_in_city' => 'boolean',
        'new_course_in_city' => 'boolean',
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
        // Map specific types to preference columns
        $map = [
            'admin_broadcast'                    => 'admin_custom',
            'report_received'                    => 'system_updates',
            'report_status_updated'              => 'system_updates',
            'new_report'                         => 'system_updates',
            'dealer_removed'                     => 'dealer_approved',
            // Trainer module — map all trainer types to system_updates
            'trainer_approved'                   => 'system_updates',
            'trainer_rejected'                   => 'system_updates',
            'trainer_suspended'                  => 'system_updates',
            'trainer_reactivated'                => 'system_updates',
            'trainer_review_approved'            => 'system_updates',
            'trainer_session_completed'          => 'system_updates',
            'trainer_booking_cancelled_by_admin' => 'system_updates',
            'trainer_booking_confirmed_by_admin' => 'system_updates',
            'trainer_payout_approved'            => 'system_updates',
            'trainer_payout_rejected'            => 'system_updates',
            'trainer_payout_paid'                => 'system_updates',
            'trainer_course_booking_created'     => 'system_updates',
            'trainer_course_booking_completed'   => 'system_updates',
            'trainer_course_set_to_draft'        => 'system_updates',
        ];

        $column = $map[$type] ?? $type;

        return $this->{$column} ?? false;
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

    /** Every togglable preference field except config-only ones (user_id, quiet hours, push_priority). */
    protected static function togglableFields(): array
    {
        return array_diff(
            (new static())->getFillable(),
            ['user_id', 'quiet_hours_start', 'quiet_hours_end', 'push_priority']
        );
    }

    public function enableAll(): void
    {
        $data = array_fill_keys(static::togglableFields(), true);
        // Enabling every notification type shouldn't also switch on quiet-hours muting.
        $data['quiet_hours_enabled'] = false;
        $this->update($data);
    }

    public function disableAll(): void
    {
        $this->update(array_fill_keys(static::togglableFields(), false));
    }
}
