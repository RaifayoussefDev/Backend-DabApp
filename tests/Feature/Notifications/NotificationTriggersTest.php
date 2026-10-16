<?php

namespace Tests\Feature\Notifications;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Listing;
use App\Models\Notification;
use App\Models\PointOfInterest;
use App\Models\Submission;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Notifications wired on 2026-10-13 that had a feature but no trigger:
 * soom_lost, dealer_approved / dealer_removed, event_starting_soon.
 */
class NotificationTriggersTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function sent(User $user, string $type): int
    {
        return Notification::where('user_id', $user->id)->where('type', $type)->count();
    }

    public function test_validating_a_sale_tells_the_other_bidders_they_lost(): void
    {
        $seller = User::factory()->create(['email' => null]);
        $winner = User::factory()->create(['email' => null]);
        $loser = User::factory()->create(['email' => null]);

        $listing = Listing::create([
            'seller_id' => $seller->id, 'status' => 'published', 'step' => 3, 'title' => 'Ducati Monster',
            'description' => 'A description long enough for validation.', 'price' => 9000, 'category_id' => 1,
            'auction_enabled' => true, 'allow_submission' => true, 'minimum_bid' => 1000,
        ]);
        $base = ['listing_id' => $listing->id, 'submission_date' => now(), 'min_soom' => 1000];
        $win = Submission::create($base + ['user_id' => $winner->id, 'amount' => 1100, 'status' => 'accepted', 'acceptance_date' => now()]);
        Submission::create($base + ['user_id' => $loser->id, 'amount' => 1050, 'status' => 'pending']);

        $this->actingAs($seller, 'api')->postJson("/api/submissions/{$win->id}/validate-sale")->assertOk();

        $this->assertSame(1, $this->sent($loser, 'soom_lost'));
        $this->assertSame(0, $this->sent($winner, 'soom_lost'));
        $this->assertSame(1, $this->sent($winner, 'soom_won'));
        $this->assertSame(
            "/listings/{$listing->id}",
            Notification::where('user_id', $loser->id)->where('type', 'soom_lost')->value('action_url')
        );
    }

    public function test_dealer_notified_on_first_poi_and_when_the_last_one_is_removed(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'email' => 'adm_' . uniqid() . '@test.local']);
        $dealer = User::factory()->create(['email' => null]);
        [$poiA, $poiB] = PointOfInterest::query()->take(2)->get()->all();
        $this->assertNotNull($poiB, 'needs two POIs in the DB');
        PointOfInterest::whereIn('id', [$poiA->id, $poiB->id])->update(['owner_id' => null]);

        $this->actingAs($admin, 'api');
        $this->postJson("/api/admin/users/{$dealer->id}/assign-poi", ['poi_id' => $poiA->id])->assertOk();
        $this->postJson("/api/admin/users/{$dealer->id}/assign-poi", ['poi_id' => $poiB->id])->assertOk();
        $this->assertSame(1, $this->sent($dealer, 'dealer_approved'));

        $this->postJson("/api/admin/users/{$dealer->id}/unassign-poi", ['poi_id' => $poiA->id])->assertOk();
        $this->assertSame(0, $this->sent($dealer, 'dealer_removed'));
        $this->postJson("/api/admin/users/{$dealer->id}/unassign-poi", ['poi_id' => $poiB->id])->assertOk();
        $this->assertSame(1, $this->sent($dealer, 'dealer_removed'));
    }

    public function test_new_listing_in_city_only_reaches_users_living_in_that_city(): void
    {
        [$jeddah, $riyadh] = DB::table('cities')->orderBy('id')->limit(2)->pluck('id')->all();

        $seller = User::factory()->create(['email' => null, 'city_id' => $jeddah]);
        $inJeddah = User::factory()->create(['email' => null, 'city_id' => $jeddah, 'is_active' => true]);
        $inRiyadh = User::factory()->create(['email' => null, 'city_id' => $riyadh, 'is_active' => true]);
        // Lives in Riyadh but once posted a listing in Jeddah: used to be notified, must not be any more
        $riyadhWithJeddahAd = User::factory()->create(['email' => null, 'city_id' => $riyadh, 'is_active' => true]);
        Listing::create([
            'seller_id' => $riyadhWithJeddahAd->id, 'status' => 'draft', 'step' => 1, 'title' => 'Old ad',
            'category_id' => 1, 'city_id' => $jeddah,
        ]);

        $listing = Listing::create([
            'seller_id' => $seller->id, 'status' => 'draft', 'step' => 3, 'title' => 'Kawasaki Z900',
            'description' => 'A description long enough for validation.', 'price' => 30000,
            'category_id' => 1, 'city_id' => $jeddah,
        ]);
        $listing->update(['status' => 'published']);

        $this->assertSame(1, $this->sent($inJeddah, 'new_listing_in_city'));
        $this->assertSame(0, $this->sent($inRiyadh, 'new_listing_in_city'));
        $this->assertSame(0, $this->sent($riyadhWithJeddahAd, 'new_listing_in_city'));
        $this->assertSame(0, $this->sent($seller, 'new_listing_in_city'));
    }

    public function test_user_sets_their_city_in_the_profile(): void
    {
        $city = DB::table('cities')->value('id');
        $user = User::factory()->create(['email' => 'city_' . uniqid() . '@test.local']);

        $this->actingAs($user, 'api')->putJson('/api/user/update', ['city_id' => $city])->assertOk();
        $this->assertSame((int) $city, (int) $user->fresh()->city_id);

        $this->actingAs($user, 'api')->putJson('/api/user/update', ['city_id' => 999999999])->assertStatus(422);
    }

    public function test_event_starting_soon_is_sent_about_an_hour_before(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-01 09:00:00'));

        $participant = User::factory()->create(['email' => null]);
        $event = Event::create([
            'title' => 'Track day', 'slug' => 'track-day-' . uniqid(), 'description' => 'Laps all day', 'status' => 'published',
            'category_id' => DB::table('event_categories')->value('id'),
            'event_date' => '2026-11-01', 'start_time' => '10:15:00',
        ]);
        EventParticipant::create(['event_id' => $event->id, 'user_id' => $participant->id, 'status' => 'registered', 'registration_date' => now()]);

        $this->artisan('events:send-reminders')->assertSuccessful();
        // Next hourly run must not send it again
        Carbon::setTestNow(Carbon::parse('2026-11-01 10:00:00'));
        $this->artisan('events:send-reminders')->assertSuccessful();

        $this->assertSame(1, $this->sent($participant, 'event_starting_soon'));
        $this->assertSame(
            "/events/{$event->id}",
            Notification::where('user_id', $participant->id)->where('type', 'event_starting_soon')->value('action_url')
        );

        Carbon::setTestNow();
    }

    /** The app's settings screen could not turn soom back on after "disable all" (field was silently dropped). */
    public function test_user_can_turn_soom_notifications_back_on_after_disable_all(): void
    {
        $user = User::factory()->create(['email' => 'pref_' . uniqid() . '@test.local']);
        $this->actingAs($user, 'api')->postJson('/api/notification-preferences/disable-all')->assertOk();
        $this->assertFalse((bool) $user->fresh()->notificationPreference->soom_new_negotiation);

        $this->actingAs($user, 'api')->putJson('/api/notification-preferences', [
            'soom_new_negotiation' => true,
            'soom_accepted' => true,
            'new_trainer_in_city' => true,
            'push_enabled' => true,
        ])->assertOk();

        $prefs = $user->fresh()->notificationPreference;
        $this->assertTrue((bool) $prefs->soom_new_negotiation);
        $this->assertTrue((bool) $prefs->soom_accepted);
        $this->assertTrue((bool) $prefs->new_trainer_in_city);
        $this->assertTrue($prefs->isNotificationEnabled('soom_new_negotiation'));
    }
}
