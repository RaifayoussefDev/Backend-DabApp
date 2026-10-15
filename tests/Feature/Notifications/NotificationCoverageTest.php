<?php

namespace Tests\Feature\Notifications;

use App\Models\Event;
use App\Models\Guide;
use App\Models\Listing;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\NotificationTemplate;
use App\Models\Payment;
use App\Models\Trainer;
use App\Models\TrainerComment;
use App\Models\User;
use App\Services\EventNotificationService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Guards the notifications audit (2026-10-13): types the code sends used to be
 * dropped silently — no preference column, no template, or no preferences row.
 */
class NotificationCoverageTest extends TestCase
{
    use DatabaseTransactions;

    /** Every type sent somewhere in app/ (opt-in broadcasts excluded, see below). */
    private const SENT_TYPES = [
        'admin_broadcast', 'dealer_approved', 'dealer_removed', 'new_report',
        'event_cancelled', 'event_created', 'event_new_participant', 'event_published',
        'event_registration_confirmed', 'event_reminder', 'event_starting_soon', 'event_updated',
        'guide_new_bookmark', 'guide_new_comment', 'guide_new_like', 'guide_published',
        'guide_submitted', 'guide_updated',
        'listing_approved', 'listing_created', 'listing_follow_up', 'listing_message',
        'listing_rejected', 'listing_sold', 'listing_updated',
        'new_course_in_city', 'new_listing_in_city', 'new_trainer_in_city',
        'order_cancelled', 'order_created', 'order_shipped', 'order_status_confirmed', 'order_status_delivered',
        'payment_failed', 'payment_success', 'refund_processed',
        'poi_approved', 'poi_rejected', 'route_approved', 'route_rejected', 'route_warning',
        'report_received', 'report_status_updated',
        'soom_accepted', 'soom_created_by_admin', 'soom_ending_soon', 'soom_lost',
        'soom_new_negotiation', 'soom_outbid', 'soom_rejected', 'soom_won',
        'trainer_approved', 'trainer_booking_cancelled_by_admin', 'trainer_booking_confirmed_by_admin',
        'trainer_course_booking_completed', 'trainer_course_booking_created', 'trainer_course_set_to_draft',
        'trainer_payout_approved', 'trainer_payout_paid', 'trainer_payout_rejected',
        'trainer_reactivated', 'trainer_rejected', 'trainer_review_approved',
        'trainer_session_completed', 'trainer_suspended',
        'trainer_new_like', 'trainer_new_favorite', 'trainer_new_comment',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function userWithoutPreferences(): User
    {
        $user = User::factory()->create(['email' => null]);
        NotificationPreference::where('user_id', $user->id)->delete();

        return $user->fresh();
    }

    public function test_every_sent_type_has_an_active_template(): void
    {
        $active = NotificationTemplate::where('is_active', true)->pluck('type')->all();

        $this->assertSame([], array_values(array_diff(self::SENT_TYPES, $active)));
    }

    public function test_every_sent_type_is_enabled_with_default_preferences(): void
    {
        $prefs = NotificationPreference::forUser($this->userWithoutPreferences());

        $blocked = array_values(array_filter(self::SENT_TYPES, fn ($t) => !$prefs->isNotificationEnabled($t)));

        $this->assertSame([], $blocked);
    }

    public function test_every_sent_type_has_a_deep_link(): void
    {
        // Admin broadcasts carry the URL the admin typed in
        $types = array_diff(self::SENT_TYPES, ['admin_broadcast', 'new_report']);

        $this->assertSame([], array_values(array_diff($types, array_keys(NotificationService::DEEP_LINKS))));
    }

    public function test_opt_in_broadcasts_stay_off_by_default(): void
    {
        $prefs = NotificationPreference::forUser($this->userWithoutPreferences());

        $this->assertFalse($prefs->isNotificationEnabled('new_guide_published'));
        $this->assertFalse($prefs->isNotificationEnabled('poi_nearby'));
    }

    public function test_user_without_preferences_still_receives_notifications(): void
    {
        $user = $this->userWithoutPreferences();

        $result = app(NotificationService::class)->sendToUser($user, 'guide_new_comment', [
            'user_name' => 'Ali',
            'guide_title' => 'Chain care',
        ], ['channels' => ['push']]);

        $this->assertTrue($result['success']);
        $this->assertSame(1, NotificationPreference::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('notifications', ['user_id' => $user->id, 'type' => 'guide_new_comment']);
    }

    public function test_unknown_type_falls_back_to_system_updates(): void
    {
        $prefs = NotificationPreference::forUser($this->userWithoutPreferences());

        $this->assertTrue($prefs->isNotificationEnabled('some_future_type'));

        $prefs->update(['system_updates' => false]);
        $this->assertFalse($prefs->fresh()->isNotificationEnabled('some_future_type'));
    }

    private function draftListing(User $seller): Listing
    {
        return Listing::create([
            'seller_id' => $seller->id,
            'status' => 'draft',
            'step' => 3,
            'title' => 'A valid listing title',
            'description' => 'A description long enough for validation.',
            'price' => 100,
            'category_id' => 1,
            'auction_enabled' => false,
        ]);
    }

    public function test_listing_going_live_notifies_seller_once(): void
    {
        $seller = $this->userWithoutPreferences();
        $listing = $this->draftListing($seller);

        $listing->update(['status' => 'published']);
        // Re-opening a sold listing is not a new publication
        $listing->update(['status' => 'sold']);
        $listing->update(['status' => 'published']);

        $this->assertSame(1, Notification::where('user_id', $seller->id)->where('type', 'listing_approved')->count());
        $this->assertSame("/listings/{$listing->id}", Notification::where('user_id', $seller->id)->where('type', 'listing_approved')->value('action_url'));
    }

    public function test_payment_failure_notifies_once(): void
    {
        $user = $this->userWithoutPreferences();
        $payment = Payment::create([
            'user_id' => $user->id,
            'amount' => 50,
            'currency' => 'AED',
            'payment_status' => 'pending',
            'cart_id' => 'test_' . uniqid(),
        ]);

        $payment->update(['payment_status' => 'failed', 'payment_result' => 'Declined']);
        $payment->update(['payment_result' => 'Declined again']);

        $this->assertSame(1, Notification::where('user_id', $user->id)->where('type', 'payment_failed')->count());
    }

    public function test_event_notifications_go_through_notification_service(): void
    {
        $user = $this->userWithoutPreferences();
        // Not persisted — the service only reads it (EventFactory is an empty stub)
        $event = (new Event())->forceFill(['id' => 987654, 'title' => 'Track day', 'event_date' => now()->addDay()]);

        $sent = app(EventNotificationService::class)->sendToUser($user, $event, 'event_updated');

        $this->assertTrue($sent);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'event_updated',
            'related_entity_type' => Event::class,
            'related_entity_id' => $event->id,
        ]);
    }

    /** The website uses the toggle endpoints, which used to skip the author notification. */
    public function test_guide_toggle_bookmark_and_like_notify_the_author(): void
    {
        $author = $this->userWithoutPreferences();
        $reader = User::factory()->create();
        $guide = Guide::create([
            'title' => 'Chain care',
            'slug' => 'chain-care-' . uniqid(),
            'content' => 'Clean, then lube.',
            'author_id' => $author->id,
            'status' => 'published',
        ]);
        $headers = ['Authorization' => 'Bearer ' . JWTAuth::fromUser($reader)];

        $this->withHeaders($headers)->postJson("/api/guides/{$guide->id}/toggle-bookmark")->assertOk();
        $this->withHeaders($headers)->postJson("/api/guides/{$guide->id}/toggle-like")->assertOk();
        // Toggling off must not notify again
        $this->withHeaders($headers)->postJson("/api/guides/{$guide->id}/toggle-bookmark")->assertOk();

        $this->assertSame(1, Notification::where('user_id', $author->id)->where('type', 'guide_new_bookmark')->count());
        $this->assertSame(1, Notification::where('user_id', $author->id)->where('type', 'guide_new_like')->count());
        $this->assertSame("/guides/{$guide->slug}", Notification::where('user_id', $author->id)->where('type', 'guide_new_like')->value('action_url'));
    }

    public function test_trainer_like_favorite_and_approved_comment_notify_the_trainer(): void
    {
        $trainerUser = $this->userWithoutPreferences();
        $fan = User::factory()->create();
        $trainer = Trainer::create([
            'user_id' => $trainerUser->id, 'name' => 'Coach Test', 'specialty' => 'coaching',
            'price_per_hour' => 150, 'status' => 'approved', 'is_available' => true,
            'rating_average' => 0, 'total_sessions' => 0, 'likes_count' => 0, 'experience_years' => 5,
        ]);
        $headers = ['Authorization' => 'Bearer ' . JWTAuth::fromUser($fan)];

        $this->withHeaders($headers)->postJson("/api/trainers/{$trainer->id}/like")->assertOk();
        $this->withHeaders($headers)->postJson("/api/trainers/{$trainer->id}/favorite")->assertOk();
        // Un-like / un-favorite must not notify
        $this->withHeaders($headers)->postJson("/api/trainers/{$trainer->id}/like")->assertOk();
        $this->withHeaders($headers)->postJson("/api/trainers/{$trainer->id}/favorite")->assertOk();

        // Comments are moderated: nothing until an admin approves, and only once
        $this->withHeaders($headers)->postJson("/api/trainers/{$trainer->id}/comments", ['content' => 'Great coach'])->assertCreated();
        $this->assertSame(0, Notification::where('user_id', $trainerUser->id)->where('type', 'trainer_new_comment')->count());

        $comment = TrainerComment::where('trainer_id', $trainer->id)->firstOrFail();
        $admin = User::where('role_id', 1)->first() ?? User::factory()->create(['role_id' => 1]);
        // actingAs, not a bearer header: the JWT guard would keep the fan from the requests above
        $this->actingAs($admin, 'api')->postJson("/api/admin/trainer-comments/{$comment->id}/approve")->assertOk();
        $this->actingAs($admin, 'api')->postJson("/api/admin/trainer-comments/{$comment->id}/approve")->assertOk();

        foreach (['trainer_new_like', 'trainer_new_favorite', 'trainer_new_comment'] as $type) {
            $this->assertSame(1, Notification::where('user_id', $trainerUser->id)->where('type', $type)->count(), $type);
            $this->assertSame("/trainers/{$trainer->id}", Notification::where('user_id', $trainerUser->id)->where('type', $type)->value('action_url'), $type);
        }
    }
}
