<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Guide;
use App\Models\Listing;
use App\Models\PointOfInterest;
use App\Models\Route;
use App\Models\Trainer;
use App\Models\TrainerCourse;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * Sends every notification type the backend uses to ONE user, push only (no email),
 * with real ids from the DB so each deep link opens a real screen. QA tool.
 *
 *   php artisan notifications:test-all 65 --list
 *   php artisan notifications:test-all 65 --group=listing
 *   php artisan notifications:test-all 65 --only=soom_won --only=guide_new_like
 *   php artisan notifications:test-all 65                  (everything)
 *   php artisan notifications:test-all 65 --delay=0
 */
class TestAllNotifications extends Command
{
    protected $signature = 'notifications:test-all
                            {user : User id that receives the notifications}
                            {--group= : Only one module: listing, soom, guide, event, trainer, poi, route, other}
                            {--only=* : Send only these types}
                            {--list : Show the groups and their types, send nothing}
                            {--delay=2 : Seconds to wait between two notifications}';

    protected $description = 'Send every notification type (push only) to one user, to test the app and its deep links';

    /** The types the backend actually sends today, by module (see NotificationCoverageTest::SENT_TYPES). */
    private const GROUPS = [
        'listing' => [
            'listing_created', 'listing_approved', 'new_listing_in_city', 'listing_updated', 'listing_sold',
            'listing_follow_up', 'payment_failed',
        ],
        'soom' => [
            'soom_new_negotiation', 'soom_outbid', 'soom_accepted', 'soom_rejected', 'soom_won', 'soom_lost',
            'soom_created_by_admin',
        ],
        'guide' => [
            'guide_new_like', 'guide_new_bookmark', 'guide_new_comment', 'guide_submitted', 'guide_published',
            'guide_updated', 'new_guide_published',
        ],
        'event' => [
            'event_created', 'event_published', 'event_updated', 'event_new_participant',
            'event_registration_confirmed', 'event_reminder', 'event_starting_soon', 'event_cancelled',
        ],
        'trainer' => [
            'trainer_approved', 'trainer_reactivated', 'trainer_rejected', 'trainer_suspended',
            'trainer_new_like', 'trainer_new_favorite', 'trainer_new_comment', 'trainer_review_approved',
            'trainer_session_completed', 'trainer_booking_confirmed_by_admin', 'trainer_booking_cancelled_by_admin',
            'trainer_course_booking_created', 'trainer_course_set_to_draft', 'trainer_course_booking_completed',
            'trainer_payout_approved', 'trainer_payout_rejected', 'trainer_payout_paid',
            'new_trainer_in_city', 'new_course_in_city',
        ],
        'poi' => ['poi_approved', 'poi_rejected', 'dealer_approved', 'dealer_removed'],
        'route' => ['route_approved', 'route_rejected', 'route_warning'],
        'other' => ['report_received', 'report_status_updated', 'admin_broadcast'],
    ];

    public function handle(NotificationService $notifications): int
    {
        if ($this->option('list')) {
            foreach (self::GROUPS as $group => $groupTypes) {
                $this->line("<info>{$group}</info> (" . count($groupTypes) . '): ' . implode(', ', $groupTypes));
            }
            return self::SUCCESS;
        }

        $user = User::find($this->argument('user'));
        if (!$user) {
            $this->error('User not found.');
            return self::FAILURE;
        }

        $group = $this->option('group');
        if ($group && !isset(self::GROUPS[$group])) {
            $this->error("Unknown group '{$group}'. Use one of: " . implode(', ', array_keys(self::GROUPS)));
            return self::FAILURE;
        }

        $types = $this->option('only')
            ?: ($group ? self::GROUPS[$group] : array_merge(...array_values(self::GROUPS)));
        $delay = max(0, (int) $this->option('delay'));
        $data = $this->sampleData();

        $this->info("Sending " . count($types) . ($group ? " [{$group}]" : '') . " notifications to #{$user->id} {$user->first_name} {$user->last_name} (push only)…");
        $tokens = $user->notificationTokens()->where('is_active', true)->count();
        if ($tokens === 0) {
            $this->warn('This user has no active FCM token: notifications will appear in the in-app list only, no push on the phone.');
        }

        $rows = [];
        foreach ($types as $i => $type) {
            try {
                $result = $notifications->sendToUser($user, $type, $data, ['channels' => ['push'], 'force' => true]);
                $notification = isset($result['notification_id']) ? \App\Models\Notification::find($result['notification_id']) : null;
                $push = $result['push_results'] ?? [];
                $rows[] = [
                    $type,
                    $result['success'] ? 'OK' : 'FAIL: ' . ($result['message'] ?? ''),
                    $notification?->action_url ?? '—',
                    isset($push['sent']) ? "{$push['sent']}/{$push['total']}" : ($push['message'] ?? '—'),
                ];
            } catch (\Throwable $e) {
                $rows[] = [$type, 'ERROR: ' . $e->getMessage(), '—', '—'];
            }

            if ($delay && $i < count($types) - 1) {
                sleep($delay);
            }
        }

        $this->table(['Type', 'Result', 'Deep link', 'Push sent'], $rows);

        return self::SUCCESS;
    }

    /** One data bag for every template, filled with real records so deep links resolve. */
    private function sampleData(): array
    {
        $listing = Listing::where('status', 'published')->latest('id')->first();
        $guide = Guide::where('status', 'published')->latest('id')->first() ?? Guide::latest('id')->first();
        $event = Event::latest('id')->first();
        $course = TrainerCourse::latest('id')->first();
        // The course's own trainer, so /trainers/courses/{trainer}/{course} is a valid pair
        $trainer = $course?->trainer ?? Trainer::where('status', 'approved')->latest('id')->first();
        $poi = PointOfInterest::latest('id')->first();
        $route = Route::latest('id')->first();

        $listingTitle = $listing?->title ?? 'Test listing';

        return [
            // Listing / soom
            'listing_id' => $listing?->id,
            'listing_title' => $listingTitle,
            'soom_id' => $listing?->id,
            'soom_title' => $listingTitle,
            'amount' => 1500,
            'winning_bid' => 1500,
            'new_bid_amount' => 1550,
            'currency' => 'AED',
            'buyer_name' => 'Test Buyer',
            'reason' => 'Test reason',
            'rejection_reason' => 'Test reason',
            'payment_id' => 0,
            // Guide
            'guide_id' => $guide?->id,
            'guide_slug' => $guide?->slug,
            'guide_title' => $guide?->title ?? 'Test guide',
            'author_name' => 'Test Author',
            'user_name' => 'Test User',
            'liker_name' => 'Test User',
            // Event
            'event_id' => $event?->id,
            'event_title' => $event?->title ?? 'Test event',
            'event_title_ar' => $event?->title_ar ?? $event?->title ?? 'Test event',
            'event_date' => $event?->event_date?->format('Y-m-d'),
            'participant_name' => 'Test Participant',
            'hours' => 24,
            // Trainer
            'trainer_id' => $trainer?->id ?? $course?->trainer_id,
            'trainer_name' => $trainer?->name ?? 'Test Trainer',
            'course_id' => $course?->id,
            'course_title' => $course?->title ?? 'Test course',
            'total_sessions' => 4,
            'session_date' => now()->addDay()->format('Y-m-d'),
            'comment' => 'Great coach!',
            'transfer_ref' => 'TEST-REF',
            'price' => 300,
            'city_name' => 'Jeddah',
            'city_name_ar' => 'جدة',
            // POI / routes / reports
            'poi_id' => $poi?->id,
            'poi_name' => $poi?->name ?? 'Test place',
            'route_id' => $route?->id,
            'route_title' => $route?->title ?? 'Test route',
            'route_name' => $route?->title ?? 'Test route',
            'warning_type' => 'pothole',
            'report_id' => 0,
            'item' => 'listing',
            'status' => 'resolved',
            // Admin broadcast template
            'title' => 'Test broadcast',
            'body' => 'This is a test notification from DabApp.',
            'title_ar' => 'إشعار تجريبي',
            'body_ar' => 'هذا إشعار تجريبي من دباب.',
        ];
    }
}
