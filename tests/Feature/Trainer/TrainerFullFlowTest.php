<?php

namespace Tests\Feature\Trainer;

use App\Models\City;
use App\Models\CommissionSetting;
use App\Models\PaymentSplit;
use App\Models\Trainer;
use App\Models\TrainerBooking;
use App\Models\TrainerComment;
use App\Models\TrainerFavorite;
use App\Models\TrainerLike;
use App\Models\TrainerLocation;
use App\Models\TrainerPayment;
use App\Models\TrainerPayout;
use App\Models\TrainerReview;
use App\Models\TrainerSchedule;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Trainer Module — Full Flow Tests
 *
 * Scenarios:
 *  1.  Public browse (no auth)
 *  2.  Trainer registration
 *  3.  Admin approval workflow (approve / reject / suspend / reactivate)
 *  4.  Trainer profile & schedule management
 *  5.  Trainer locations CRUD
 *  6.  Client booking (PayTabs mocked)
 *  7.  Session lifecycle: start → complete
 *  8.  Cancel booking (pending + paid)
 *  9.  Reviews: submit, admin approve/delete
 * 10.  Comments: add, reply, delete, admin moderate
 * 11.  Social: like toggle, favorite toggle
 * 12.  Admin booking management (confirm, force-cancel)
 * 13.  Commission settings
 * 14.  Payouts: approve, reject, mark-paid
 * 15.  Admin stats & payments
 * 16.  Auth / guard error cases
 */
class TrainerFullFlowTest extends TestCase
{
    // ----------------------------------------------------------------
    // Shared actors & fixtures
    // ----------------------------------------------------------------

    protected User $admin;
    protected User $trainerUser;
    protected User $clientUser;
    protected Trainer $trainer;
    protected TrainerLocation $location;
    protected City $city;

    protected string $adminToken;
    protected string $trainerToken;
    protected string $clientToken;

    // ----------------------------------------------------------------
    // Bootstrap
    // ----------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootActors();
        $this->bootTrainer();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    private function bootActors(): void
    {
        $this->admin = User::where('role_id', 1)->first()
            ?? User::factory()->create(['role_id' => 1]);

        $uid = uniqid();
        $this->trainerUser = User::factory()->create(['role_id' => 2, 'is_active' => true, 'email' => "trainer_{$uid}@test.local"]);
        $this->clientUser  = User::factory()->create(['role_id' => 2, 'is_active' => true, 'email' => "client_{$uid}@test.local"]);

        $this->adminToken   = JWTAuth::fromUser($this->admin);
        $this->trainerToken = JWTAuth::fromUser($this->trainerUser);
        $this->clientToken  = JWTAuth::fromUser($this->clientUser);

        $this->city = City::first();
    }

    private function bootTrainer(): void
    {
        $this->trainer = Trainer::create([
            'user_id'         => $this->trainerUser->id,
            'name'            => 'Test Trainer',
            'name_ar'         => 'مدرب تجريبي',
            'bio'             => 'Expert trainer for automated tests',
            'specialty'       => 'coaching',       // ENUM: coaching|competition|off-road|street|custom
            'price_per_hour'  => 150.00,
            'status'          => 'approved',
            'is_available'    => true,
            'rating_average'  => 0,
            'total_sessions'  => 0,
            'likes_count'     => 0,
            'experience_years'=> 5,
        ]);

        $this->location = TrainerLocation::create([
            'trainer_id'       => $this->trainer->id,
            'location_name'    => 'Test Arena',
            'location_name_ar' => 'ساحة الاختبار',
            'city_id'          => $this->city->id,
            'is_available'     => true,
        ]);

        TrainerSchedule::create([
            'trainer_id'   => $this->trainer->id,
            'day_of_week'  => 1,      // Monday
            'start_time'   => '08:00',
            'end_time'     => '18:00',
            'is_available' => true,
        ]);
    }

    private function cleanUp(): void
    {
        $bookingIds = TrainerBooking::where('trainer_id', $this->trainer->id)->pluck('id');

        TrainerReview::where('trainer_id', $this->trainer->id)->delete();
        TrainerComment::where('trainer_id', $this->trainer->id)->delete();
        TrainerFavorite::where('trainer_id', $this->trainer->id)->delete();
        TrainerLike::where('trainer_id', $this->trainer->id)->delete();
        TrainerSchedule::where('trainer_id', $this->trainer->id)->delete();

        if ($bookingIds->isNotEmpty()) {
            $paymentIds = TrainerBooking::whereIn('id', $bookingIds)->pluck('payment_id')->filter();
            TrainerPayout::where('trainer_id', $this->trainer->id)->delete();
            PaymentSplit::where('trainer_id', $this->trainer->id)->delete();
            TrainerBooking::whereIn('id', $bookingIds)->delete();
            if ($paymentIds->isNotEmpty()) {
                TrainerPayment::whereIn('id', $paymentIds)->delete();
            }
        } else {
            TrainerPayout::where('trainer_id', $this->trainer->id)->delete();
            PaymentSplit::where('trainer_id', $this->trainer->id)->delete();
        }

        CommissionSetting::where('entity_type', 'trainer')->where('entity_id', $this->trainer->id)->delete();
        TrainerLocation::where('trainer_id', $this->trainer->id)->delete();
        $this->trainer->delete();
        $this->trainerUser->delete();
        $this->clientUser->delete();
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function auth(string $token): array
    {
        return ['Authorization' => "Bearer {$token}"];
    }

    /** Booking in confirmed+paid state (ready to start). */
    private function createConfirmedBooking(): TrainerBooking
    {
        $payment = TrainerPayment::create([
            'user_id'        => $this->clientUser->id,
            'amount'         => 150.00,
            'payment_status' => 'paid',
            'tran_ref'       => 'TST-' . uniqid(),
            'cart_id'        => 'TRAINER_TST_' . uniqid(),
            'currency'       => 'SAR',
        ]);

        $booking = TrainerBooking::create([
            'trainer_id'     => $this->trainer->id,
            'user_id'        => $this->clientUser->id,
            'location_id'    => $this->location->id,
            'booking_date'   => now()->addDays(3)->toDateString(),
            'start_time'     => '10:00',
            'end_time'       => '12:00',
            'duration_hours' => 2,
            'session_type'   => 'beginner',   // ENUM: beginner|intermediate|advanced|custom
            'status'         => 'confirmed',
            'price'          => 150.00,
            'payment_id'     => $payment->id,
            'payment_status' => 'paid',
            'confirmed_at'   => now(),
        ]);

        $payment->update(['cart_id' => 'TRAINER_' . $booking->id]);
        return $booking;
    }

    /** Booking with no payment, pending status. */
    private function createPendingBooking(): TrainerBooking
    {
        return TrainerBooking::create([
            'trainer_id'     => $this->trainer->id,
            'user_id'        => $this->clientUser->id,
            'location_id'    => $this->location->id,
            'booking_date'   => now()->addDays(5)->toDateString(),
            'start_time'     => '14:00',
            'end_time'       => '16:00',
            'duration_hours' => 2,
            'session_type'   => 'beginner',
            'status'         => 'pending',
            'price'          => 150.00,
            'payment_status' => 'pending',
        ]);
    }

    /** Booking in completed state for review tests. */
    private function createCompletedBooking(): TrainerBooking
    {
        $booking = $this->createConfirmedBooking();
        $booking->update(['status' => 'in_progress']);
        $booking->update(['status' => 'completed', 'completed_at' => now()]);
        return $booking->fresh();
    }

    /** Full payout chain: payment → booking → split → payout. */
    private function createPayout(string $status = 'pending'): TrainerPayout
    {
        $payment = TrainerPayment::create([
            'user_id'        => $this->clientUser->id,
            'amount'         => 300.00,
            'payment_status' => 'paid',
            'tran_ref'       => 'TST-PO-' . uniqid(),
            'cart_id'        => 'TRAINER_PO_' . uniqid(),
            'currency'       => 'SAR',
        ]);

        $booking = TrainerBooking::create([
            'trainer_id'     => $this->trainer->id,
            'user_id'        => $this->clientUser->id,
            'location_id'    => $this->location->id,
            'booking_date'   => now()->subDays(2)->toDateString(),
            'start_time'     => '10:00',
            'end_time'       => '12:00',
            'duration_hours' => 2,
            'session_type'   => 'beginner',
            'status'         => 'completed',
            'price'          => 300.00,
            'payment_id'     => $payment->id,
            'payment_status' => 'paid',
            'completed_at'   => now()->subDays(2),
        ]);

        $split = PaymentSplit::create([
            'trainer_id'            => $this->trainer->id,
            'booking_id'            => $booking->id,
            'payment_id'            => $payment->id,
            'total_amount'          => 300.00,
            'commission_percentage' => 15.0,
            'commission_amount'     => 45.00,
            'trainer_amount'        => 255.00,
            'currency'              => 'SAR',
            'status'                => 'pending',
        ]);

        return TrainerPayout::create([
            'trainer_id'       => $this->trainer->id,
            'payment_split_id' => $split->id,
            'amount'           => 255.00,
            'currency'         => 'SAR',
            'status'           => $status,
            'bank_name'        => 'Al Rajhi Bank',
            'iban'             => 'SA0380000000608010167519',
        ]);
    }

    // ================================================================
    // SCENARIO 1 — Public browse (no auth required)
    // ================================================================

    public function test_public_can_list_trainers()
    {
        $response = $this->getJson('/api/trainers');
        $response->assertStatus(200)
                 ->assertJsonStructure(['success', 'data']);
    }

    public function test_public_can_view_trainer_profile()
    {
        $response = $this->getJson("/api/trainers/{$this->trainer->id}");
        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.id', $this->trainer->id);
    }

    public function test_public_trainer_profile_returns_404_for_unknown_id()
    {
        $response = $this->getJson('/api/trainers/99999999');
        $response->assertStatus(404);
    }

    public function test_public_can_list_trainer_locations()
    {
        $response = $this->getJson('/api/trainer-locations');
        $response->assertStatus(200);
    }

    public function test_public_can_check_trainer_availability()
    {
        $from = now()->next('Monday')->toDateString();
        $to   = now()->next('Monday')->addDays(6)->toDateString();
        $response = $this->getJson(
            "/api/trainers/{$this->trainer->id}/availability?from_date={$from}&to_date={$to}&location_id={$this->location->id}"
        );
        $response->assertStatus(200)
                 ->assertJsonStructure(['success', 'data']);
    }

    public function test_public_can_list_trainer_reviews()
    {
        $response = $this->getJson("/api/trainers/{$this->trainer->id}/reviews");
        $response->assertStatus(200)
                 ->assertJsonStructure(['success', 'data']);
    }

    public function test_public_can_list_trainer_comments()
    {
        $response = $this->getJson("/api/trainers/{$this->trainer->id}/comments");
        $response->assertStatus(200)
                 ->assertJsonStructure(['success', 'data']);
    }

    // ================================================================
    // SCENARIO 2 — Trainer registration
    // ================================================================

    public function test_user_can_register_as_trainer()
    {
        $newUser  = User::factory()->create(['role_id' => 2, 'is_active' => true]);
        $token    = JWTAuth::fromUser($newUser);

        $response = $this->withHeaders($this->auth($token))
            ->postJson('/api/trainer/register', [
                'name'            => 'New Test Trainer',
                'name_ar'         => 'مدرب جديد',
                'specialty'       => 'competition',
                'bio'             => 'Registered via automated test',
                'experience_years'=> 3,
                'price_per_hour'  => 200.00,
            ]);

        $response->assertStatus(201)
                 ->assertJsonPath('success', true);

        $this->assertDatabaseHas('trainers', [
            'user_id' => $newUser->id,
            'status'  => 'pending',
        ]);

        Trainer::where('user_id', $newUser->id)->delete();
        $newUser->delete();
    }

    public function test_user_cannot_register_as_trainer_twice()
    {
        $response = $this->withHeaders($this->auth($this->trainerToken))
            ->postJson('/api/trainer/register', [
                'name'          => 'Duplicate',
                'specialty'     => 'coaching',
                'price_per_hour'=> 100.00,
            ]);

        $response->assertStatus(409);
    }

    public function test_registration_requires_mandatory_fields()
    {
        $newUser = User::factory()->create(['role_id' => 2, 'is_active' => true]);
        $token   = JWTAuth::fromUser($newUser);

        $response = $this->withHeaders($this->auth($token))
            ->postJson('/api/trainer/register', []);

        $response->assertStatus(422);

        $newUser->delete();
    }

    public function test_unauthenticated_cannot_register_as_trainer()
    {
        $response = $this->postJson('/api/trainer/register', [
            'name'          => 'Ghost',
            'specialty'     => 'coaching',
            'price_per_hour'=> 50,
        ]);
        $response->assertStatus(401);
    }

    // ================================================================
    // SCENARIO 3 — Admin approval workflow
    // ================================================================

    public function test_admin_can_list_trainers()
    {
        $response = $this->withHeaders($this->auth($this->adminToken))
            ->getJson('/api/admin/trainers');

        $response->assertStatus(200)
                 ->assertJsonStructure(['success', 'data']);
    }

    public function test_admin_can_filter_trainers_by_status()
    {
        $response = $this->withHeaders($this->auth($this->adminToken))
            ->getJson('/api/admin/trainers?status=pending');

        $response->assertStatus(200);
    }

    public function test_admin_can_view_trainer_detail()
    {
        $response = $this->withHeaders($this->auth($this->adminToken))
            ->getJson("/api/admin/trainers/{$this->trainer->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.id', $this->trainer->id);
    }

    public function test_admin_full_approval_lifecycle()
    {
        $pendingUser    = User::factory()->create(['role_id' => 2, 'is_active' => true]);
        $pendingTrainer = Trainer::create([
            'user_id'         => $pendingUser->id,
            'name'            => 'Lifecycle Trainer',
            'specialty'       => 'competition',
            'price_per_hour'  => 100,
            'status'          => 'pending',
            'is_available'    => false,
            'rating_average'  => 0,
            'total_sessions'  => 0,
            'likes_count'     => 0,
            'experience_years'=> 2,
        ]);

        // Approve
        $r = $this->withHeaders($this->auth($this->adminToken))
            ->postJson("/api/admin/trainers/{$pendingTrainer->id}/approve");
        $r->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('trainers', ['id' => $pendingTrainer->id, 'status' => 'approved']);

        // Cannot approve again
        $r = $this->withHeaders($this->auth($this->adminToken))
            ->postJson("/api/admin/trainers/{$pendingTrainer->id}/approve");
        $r->assertStatus(400);

        // Suspend
        $r = $this->withHeaders($this->auth($this->adminToken))
            ->postJson("/api/admin/trainers/{$pendingTrainer->id}/suspend", ['reason' => 'Policy violation']);
        $r->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('trainers', ['id' => $pendingTrainer->id, 'status' => 'suspended']);

        // Reactivate
        $r = $this->withHeaders($this->auth($this->adminToken))
            ->postJson("/api/admin/trainers/{$pendingTrainer->id}/reactivate");
        $r->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('trainers', ['id' => $pendingTrainer->id, 'status' => 'approved']);

        // Cannot reactivate non-suspended trainer
        $r = $this->withHeaders($this->auth($this->adminToken))
            ->postJson("/api/admin/trainers/{$pendingTrainer->id}/reactivate");
        $r->assertStatus(400);

        // Reject
        $pendingTrainer->update(['status' => 'pending']);
        $r = $this->withHeaders($this->auth($this->adminToken))
            ->postJson("/api/admin/trainers/{$pendingTrainer->id}/reject", ['reason' => 'Incomplete docs']);
        $r->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('trainers', ['id' => $pendingTrainer->id, 'status' => 'rejected']);

        // Delete
        $r = $this->withHeaders($this->auth($this->adminToken))
            ->deleteJson("/api/admin/trainers/{$pendingTrainer->id}");
        $r->assertStatus(200);
        $this->assertDatabaseMissing('trainers', ['id' => $pendingTrainer->id]);

        $pendingUser->delete();
    }

    public function test_admin_trainer_approve_returns_404_for_unknown()
    {
        $r = $this->withHeaders($this->auth($this->adminToken))
            ->postJson('/api/admin/trainers/99999999/approve');
        $r->assertStatus(404);
    }


    // ================================================================
    // SCENARIO 4 — Trainer profile management
    // ================================================================

    public function test_trainer_can_view_own_profile()
    {
        $response = $this->withHeaders($this->auth($this->trainerToken))
            ->getJson('/api/trainer/me');

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.trainer.id', $this->trainer->id);
    }

    public function test_trainer_can_update_profile()
    {
        $response = $this->withHeaders($this->auth($this->trainerToken))
            ->postJson('/api/trainer/profile', [
                'bio'           => 'Updated bio via automated test',
                'price_per_hour'=> 175.00,
            ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true);

        $this->assertDatabaseHas('trainers', [
            'id'            => $this->trainer->id,
            'price_per_hour'=> 175.00,
        ]);
    }

    public function test_non_trainer_cannot_view_trainer_me()
    {
        $response = $this->withHeaders($this->auth($this->clientToken))
            ->getJson('/api/trainer/me');

        $response->assertStatus(404);  // No trainer profile for this user
    }

    // ================================================================
    // SCENARIO 5 — Schedule management
    // ================================================================

    public function test_trainer_can_get_schedule()
    {
        $response = $this->withHeaders($this->auth($this->trainerToken))
            ->getJson('/api/trainer/schedule');

        $response->assertStatus(200)
                 ->assertJsonPath('success', true);
    }

    public function test_trainer_can_upsert_schedule()
    {
        $response = $this->withHeaders($this->auth($this->trainerToken))
            ->postJson('/api/trainer/schedule', [
                'schedule' => [
                    ['day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '18:00', 'is_available' => true],
                    ['day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '17:00', 'is_available' => true],
                    ['day_of_week' => 6, 'start_time' => '08:00', 'end_time' => '09:00', 'is_available' => false],
                ],
            ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true);

        $this->assertDatabaseHas('trainer_schedules', [
            'trainer_id'  => $this->trainer->id,
            'day_of_week' => 2,
        ]);
    }

    // ================================================================
    // SCENARIO 6 — Locations CRUD
    // ================================================================

    public function test_trainer_can_add_location()
    {
        $response = $this->withHeaders($this->auth($this->trainerToken))
            ->postJson('/api/trainer/locations', [
                'location_name'    => 'New Test Location',
                'location_name_ar' => 'موقع اختبار جديد',
                'city_id'          => $this->city->id,
            ]);

        $response->assertStatus(201)
                 ->assertJsonPath('success', true);

        $locationId = $response->json('data.id');
        $this->assertDatabaseHas('trainer_locations', ['id' => $locationId]);

        TrainerLocation::where('id', $locationId)->delete();
    }

    public function test_trainer_can_delete_own_location()
    {
        $extra = TrainerLocation::create([
            'trainer_id'    => $this->trainer->id,
            'location_name' => 'To Be Deleted',
            'city_id'       => $this->city->id,
            'is_available'  => true,
        ]);

        $response = $this->withHeaders($this->auth($this->trainerToken))
            ->deleteJson("/api/trainer/locations/{$extra->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('trainer_locations', ['id' => $extra->id]);
    }

    public function test_trainer_cannot_delete_another_trainers_location()
    {
        $otherUser     = User::factory()->create(['role_id' => 2, 'is_active' => true]);
        $otherTrainer  = Trainer::create([
            'user_id'         => $otherUser->id,
            'name'            => 'Other',
            'specialty'       => 'coaching',
            'price_per_hour'  => 100,
            'status'          => 'approved',
            'is_available'    => true,
            'rating_average'  => 0,
            'total_sessions'  => 0,
            'likes_count'     => 0,
            'experience_years'=> 1,
        ]);
        $otherLocation = TrainerLocation::create([
            'trainer_id'    => $otherTrainer->id,
            'location_name' => 'Other Location',
            'city_id'       => $this->city->id,
            'is_available'  => true,
        ]);

        $response = $this->withHeaders($this->auth($this->trainerToken))
            ->deleteJson("/api/trainer/locations/{$otherLocation->id}");

        $response->assertStatus(403);

        $otherLocation->delete();
        $otherTrainer->delete();
        $otherUser->delete();
    }

    // ================================================================
    // SCENARIO 7 — Booking: book (PayTabs mocked)
    // ================================================================

    public function test_client_can_initiate_booking_with_paytabs_mocked()
    {
        Http::fake([
            '*paytabs*' => Http::response([
                'redirect_url' => 'https://secure.paytabs.sa/payment/wr/faketoken',
                'tran_ref'     => 'TST-' . time(),
                'cart_id'      => 'TRAINER_TEST',
            ], 200),
        ]);

        $date = now()->next('Monday')->toDateString();

        $response = $this->withHeaders($this->auth($this->clientToken))
            ->postJson("/api/trainers/{$this->trainer->id}/book", [
                'location_id'  => $this->location->id,
                'booking_date' => $date,
                'start_time'   => '08:00',
                'end_time'     => '10:00',
                'session_type' => 'beginner',
            ]);

        $response->assertStatus(201)
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure(['data' => ['booking_id', 'payment_url']]);

        $bookingId = $response->json('data.booking_id');
        if ($bookingId) {
            $booking = TrainerBooking::find($bookingId);
            if ($booking) {
                $paymentId = $booking->payment_id;
                $booking->delete();
                if ($paymentId) {
                    TrainerPayment::where('id', $paymentId)->delete();
                }
            }
        }
    }

    public function test_booking_requires_authentication()
    {
        $response = $this->postJson("/api/trainers/{$this->trainer->id}/book", [
            'location_id'  => $this->location->id,
            'booking_date' => now()->addDays(7)->toDateString(),
            'start_time'   => '08:00',
            'end_time'     => '10:00',
            'session_type' => 'beginner',
        ]);

        $response->assertStatus(401);
    }

    public function test_booking_rejected_for_past_date()
    {
        $response = $this->withHeaders($this->auth($this->clientToken))
            ->postJson("/api/trainers/{$this->trainer->id}/book", [
                'location_id'  => $this->location->id,
                'booking_date' => now()->subDays(1)->toDateString(),
                'start_time'   => '10:00',
                'end_time'     => '12:00',
                'session_type' => 'beginner',
            ]);

        $response->assertStatus(422);
    }

    // ================================================================
    // SCENARIO 8 — Session lifecycle
    // ================================================================

    public function test_trainer_can_start_confirmed_session()
    {
        $booking = $this->createConfirmedBooking();

        $response = $this->withHeaders($this->auth($this->trainerToken))
            ->postJson("/api/trainer/sessions/{$booking->id}/start");

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('trainer_bookings', ['id' => $booking->id, 'status' => 'in_progress']);
    }

    public function test_trainer_cannot_start_non_confirmed_session()
    {
        $booking = $this->createConfirmedBooking();
        $booking->update(['status' => 'pending']);

        $response = $this->withHeaders($this->auth($this->trainerToken))
            ->postJson("/api/trainer/sessions/{$booking->id}/start");

        $response->assertStatus(400);
    }

    public function test_trainer_can_complete_in_progress_session()
    {
        $booking = $this->createConfirmedBooking();
        $booking->update(['status' => 'in_progress']);

        $response = $this->withHeaders($this->auth($this->trainerToken))
            ->postJson("/api/trainer/sessions/{$booking->id}/complete");

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('trainer_bookings', ['id' => $booking->id, 'status' => 'completed']);
    }

    public function test_trainer_cannot_complete_non_in_progress_session()
    {
        $booking = $this->createConfirmedBooking();

        $response = $this->withHeaders($this->auth($this->trainerToken))
            ->postJson("/api/trainer/sessions/{$booking->id}/complete");

        $response->assertStatus(400);
    }

    public function test_other_trainer_cannot_manage_session()
    {
        $otherUser  = User::factory()->create(['role_id' => 2, 'is_active' => true]);
        $otherToken = JWTAuth::fromUser($otherUser);
        $booking    = $this->createConfirmedBooking();

        $response = $this->withHeaders($this->auth($otherToken))
            ->postJson("/api/trainer/sessions/{$booking->id}/start");

        $response->assertStatus(403);
        $otherUser->delete();
    }

    public function test_trainer_can_list_own_sessions()
    {
        $this->createConfirmedBooking();

        $response = $this->withHeaders($this->auth($this->trainerToken))
            ->getJson('/api/trainer/sessions');

        $response->assertStatus(200)
                 ->assertJsonStructure(['success', 'data']);
    }

    // ================================================================
    // SCENARIO 9 — Cancel booking
    // ================================================================

    public function test_client_can_cancel_pending_booking()
    {
        $booking = $this->createPendingBooking();

        $response = $this->withHeaders($this->auth($this->clientToken))
            ->postJson("/api/trainer/bookings/{$booking->id}/cancel");

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('trainer_bookings', ['id' => $booking->id, 'status' => 'cancelled']);
    }

    public function test_client_can_cancel_paid_booking_triggering_refund()
    {
        Http::fake([
            '*paytabs*' => Http::response(['payment_status' => 'Refunded'], 200),
        ]);

        $booking = $this->createConfirmedBooking();

        $response = $this->withHeaders($this->auth($this->clientToken))
            ->postJson("/api/trainer/bookings/{$booking->id}/cancel");

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('trainer_bookings', ['id' => $booking->id, 'status' => 'cancelled']);
    }

    public function test_client_cannot_cancel_completed_booking()
    {
        $booking = $this->createCompletedBooking();

        $response = $this->withHeaders($this->auth($this->clientToken))
            ->postJson("/api/trainer/bookings/{$booking->id}/cancel");

        $response->assertStatus(400);
    }

    public function test_client_cannot_cancel_another_users_booking()
    {
        $otherUser  = User::factory()->create(['role_id' => 2, 'is_active' => true]);
        $otherToken = JWTAuth::fromUser($otherUser);
        $booking    = $this->createPendingBooking();

        $response = $this->withHeaders($this->auth($otherToken))
            ->postJson("/api/trainer/bookings/{$booking->id}/cancel");

        $response->assertStatus(403);
        $otherUser->delete();
    }

    public function test_client_can_list_own_bookings()
    {
        $this->createPendingBooking();

        $response = $this->withHeaders($this->auth($this->clientToken))
            ->getJson('/api/trainer/bookings');

        $response->assertStatus(200)
                 ->assertJsonStructure(['success', 'data']);
    }

    // ================================================================
    // SCENARIO 10 — Reviews
    // ================================================================

    public function test_client_can_leave_review_on_completed_booking()
    {
        $booking = $this->createCompletedBooking();

        $response = $this->withHeaders($this->auth($this->clientToken))
            ->postJson("/api/trainer/bookings/{$booking->id}/review", [
                'rating'  => 5,
                'comment' => 'Excellent trainer, highly professional.',
            ]);

        $response->assertStatus(201)->assertJsonPath('success', true);

        $this->assertDatabaseHas('trainer_reviews', [
            'trainer_id'  => $this->trainer->id,
            'user_id'     => $this->clientUser->id,
            'rating'      => 5,
            'is_approved' => false,
        ]);
    }

    public function test_client_cannot_review_non_completed_booking()
    {
        $booking = $this->createPendingBooking();

        $response = $this->withHeaders($this->auth($this->clientToken))
            ->postJson("/api/trainer/bookings/{$booking->id}/review", [
                'rating'  => 4,
                'comment' => 'Too soon.',
            ]);

        $response->assertStatus(400);
    }

    public function test_client_cannot_review_same_booking_twice()
    {
        $booking = $this->createCompletedBooking();

        TrainerReview::create([
            'trainer_id'  => $this->trainer->id,
            'user_id'     => $this->clientUser->id,
            'booking_id'  => $booking->id,
            'rating'      => 4,
            'comment'     => 'First review',
            'is_approved' => false,
        ]);

        $response = $this->withHeaders($this->auth($this->clientToken))
            ->postJson("/api/trainer/bookings/{$booking->id}/review", [
                'rating'  => 5,
                'comment' => 'Second review attempt',
            ]);

        $response->assertStatus(400);
    }

    public function test_admin_can_list_pending_reviews()
    {
        $response = $this->withHeaders($this->auth($this->adminToken))
            ->getJson('/api/admin/trainer-reviews?approved=0');

        $response->assertStatus(200)
                 ->assertJsonStructure(['success', 'data']);
    }

    public function test_admin_can_approve_review()
    {
        $booking = $this->createCompletedBooking();
        $review  = TrainerReview::create([
            'trainer_id'  => $this->trainer->id,
            'user_id'     => $this->clientUser->id,
            'booking_id'  => $booking->id,
            'rating'      => 4,
            'comment'     => 'Pending approval review',
            'is_approved' => false,
        ]);

        $response = $this->withHeaders($this->auth($this->adminToken))
            ->postJson("/api/admin/trainer-reviews/{$review->id}/approve");

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('trainer_reviews', ['id' => $review->id, 'is_approved' => true]);
    }

    public function test_admin_can_delete_review()
    {
        $booking = $this->createCompletedBooking();
        $review  = TrainerReview::create([
            'trainer_id'  => $this->trainer->id,
            'user_id'     => $this->clientUser->id,
            'booking_id'  => $booking->id,
            'rating'      => 2,
            'comment'     => 'Spam review to delete',
            'is_approved' => false,
        ]);

        $response = $this->withHeaders($this->auth($this->adminToken))
            ->deleteJson("/api/admin/trainer-reviews/{$review->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('trainer_reviews', ['id' => $review->id]);
    }

    // ================================================================
    // SCENARIO 11 — Comments
    // ================================================================

    public function test_authenticated_user_can_add_comment()
    {
        $response = $this->withHeaders($this->auth($this->clientToken))
            ->postJson("/api/trainers/{$this->trainer->id}/comments", [
                'content' => 'Great trainer, very professional!',
            ]);

        $response->assertStatus(201)->assertJsonPath('success', true);

        $this->assertDatabaseHas('trainer_comments', [
            'trainer_id' => $this->trainer->id,
            'user_id'    => $this->clientUser->id,
        ]);
    }

    public function test_user_can_reply_to_a_comment()
    {
        $parent = TrainerComment::create([
            'trainer_id' => $this->trainer->id,
            'user_id'    => $this->clientUser->id,
            'content'    => 'Parent comment',
            'parent_id'  => null,
            'is_approved'=> true,
        ]);

        $response = $this->withHeaders($this->auth($this->clientToken))
            ->postJson("/api/trainers/{$this->trainer->id}/comments", [
                'content'   => 'Reply to parent',
                'parent_id' => $parent->id,
            ]);

        $response->assertStatus(201)->assertJsonPath('success', true);
        $this->assertDatabaseHas('trainer_comments', ['parent_id' => $parent->id]);
    }

    public function test_user_can_delete_own_comment()
    {
        $comment = TrainerComment::create([
            'trainer_id' => $this->trainer->id,
            'user_id'    => $this->clientUser->id,
            'content'    => 'To be deleted',
            'is_approved'=> false,
        ]);

        $response = $this->withHeaders($this->auth($this->clientToken))
            ->deleteJson("/api/trainer/comments/{$comment->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('trainer_comments', ['id' => $comment->id]);
    }

    public function test_user_cannot_delete_another_users_comment()
    {
        $comment    = TrainerComment::create([
            'trainer_id' => $this->trainer->id,
            'user_id'    => $this->clientUser->id,
            'content'    => 'Not mine',
            'is_approved'=> false,
        ]);
        $otherUser  = User::factory()->create(['role_id' => 2, 'is_active' => true]);
        $otherToken = JWTAuth::fromUser($otherUser);

        $response = $this->withHeaders($this->auth($otherToken))
            ->deleteJson("/api/trainer/comments/{$comment->id}");

        $response->assertStatus(403);
        $comment->delete();
        $otherUser->delete();
    }

    public function test_admin_can_approve_comment()
    {
        $comment = TrainerComment::create([
            'trainer_id' => $this->trainer->id,
            'user_id'    => $this->clientUser->id,
            'content'    => 'Awaiting moderation',
            'is_approved'=> false,
        ]);

        $response = $this->withHeaders($this->auth($this->adminToken))
            ->postJson("/api/admin/trainer-comments/{$comment->id}/approve");

        $response->assertStatus(200);
        $this->assertDatabaseHas('trainer_comments', ['id' => $comment->id, 'is_approved' => true]);
    }

    public function test_admin_can_delete_comment()
    {
        $comment = TrainerComment::create([
            'trainer_id' => $this->trainer->id,
            'user_id'    => $this->clientUser->id,
            'content'    => 'Spam comment',
            'is_approved'=> false,
        ]);

        $response = $this->withHeaders($this->auth($this->adminToken))
            ->deleteJson("/api/admin/trainer-comments/{$comment->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('trainer_comments', ['id' => $comment->id]);
    }

    public function test_unauthenticated_cannot_post_comment()
    {
        $response = $this->postJson("/api/trainers/{$this->trainer->id}/comments", [
            'content' => 'Anonymous attempt',
        ]);
        $response->assertStatus(401);
    }

    // ================================================================
    // SCENARIO 12 — Social (like & favorite)
    // ================================================================

    public function test_client_can_like_trainer()
    {
        $response = $this->withHeaders($this->auth($this->clientToken))
            ->postJson("/api/trainers/{$this->trainer->id}/like");

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure(['liked', 'likes_count']);
    }

    public function test_client_can_toggle_like_off()
    {
        TrainerLike::create([
            'trainer_id' => $this->trainer->id,
            'user_id'    => $this->clientUser->id,
        ]);
        $this->trainer->increment('likes_count');

        $response = $this->withHeaders($this->auth($this->clientToken))
            ->postJson("/api/trainers/{$this->trainer->id}/like");

        $response->assertStatus(200)
                 ->assertJsonPath('liked', false);

        $this->assertDatabaseMissing('trainer_likes', [
            'trainer_id' => $this->trainer->id,
            'user_id'    => $this->clientUser->id,
        ]);
    }

    public function test_client_can_favorite_trainer()
    {
        $response = $this->withHeaders($this->auth($this->clientToken))
            ->postJson("/api/trainers/{$this->trainer->id}/favorite");

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure(['favorited']);
    }

    public function test_client_can_unfavorite_trainer()
    {
        TrainerFavorite::create([
            'trainer_id' => $this->trainer->id,
            'user_id'    => $this->clientUser->id,
        ]);

        $response = $this->withHeaders($this->auth($this->clientToken))
            ->postJson("/api/trainers/{$this->trainer->id}/favorite");

        $response->assertStatus(200)
                 ->assertJsonPath('favorited', false);
    }

    public function test_client_can_list_own_favorites()
    {
        TrainerFavorite::create([
            'trainer_id' => $this->trainer->id,
            'user_id'    => $this->clientUser->id,
        ]);

        $response = $this->withHeaders($this->auth($this->clientToken))
            ->getJson('/api/user/trainer-favorites');

        $response->assertStatus(200)
                 ->assertJsonStructure(['success', 'data']);
    }

    public function test_unauthenticated_cannot_like_trainer()
    {
        $response = $this->postJson("/api/trainers/{$this->trainer->id}/like");
        $response->assertStatus(401);
    }

    // ================================================================
    // SCENARIO 13 — Admin booking management
    // ================================================================

    public function test_admin_can_list_all_bookings()
    {
        $this->createPendingBooking();

        $response = $this->withHeaders($this->auth($this->adminToken))
            ->getJson('/api/admin/trainer-bookings');

        $response->assertStatus(200)
                 ->assertJsonStructure(['success', 'data']);
    }

    public function test_admin_can_view_booking_detail()
    {
        $booking = $this->createPendingBooking();

        $response = $this->withHeaders($this->auth($this->adminToken))
            ->getJson("/api/admin/trainer-bookings/{$booking->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.id', $booking->id);
    }

    public function test_admin_can_manually_confirm_pending_booking()
    {
        $booking = $this->createPendingBooking();

        $response = $this->withHeaders($this->auth($this->adminToken))
            ->postJson("/api/admin/trainer-bookings/{$booking->id}/confirm");

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('trainer_bookings', ['id' => $booking->id, 'status' => 'confirmed']);
    }

    public function test_admin_cannot_confirm_already_confirmed_booking()
    {
        $booking = $this->createConfirmedBooking();

        $response = $this->withHeaders($this->auth($this->adminToken))
            ->postJson("/api/admin/trainer-bookings/{$booking->id}/confirm");

        $response->assertStatus(400);
    }

    public function test_admin_can_force_cancel_confirmed_booking()
    {
        $booking = $this->createConfirmedBooking();

        $response = $this->withHeaders($this->auth($this->adminToken))
            ->postJson("/api/admin/trainer-bookings/{$booking->id}/cancel", [
                'reason' => 'Force cancel for automated test',
            ]);

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('trainer_bookings', ['id' => $booking->id, 'status' => 'cancelled']);
    }

    public function test_admin_force_cancel_requires_reason()
    {
        $booking = $this->createConfirmedBooking();

        $response = $this->withHeaders($this->auth($this->adminToken))
            ->postJson("/api/admin/trainer-bookings/{$booking->id}/cancel", []);

        $response->assertStatus(422);
    }

    public function test_admin_booking_detail_returns_404_for_unknown()
    {
        $response = $this->withHeaders($this->auth($this->adminToken))
            ->getJson('/api/admin/trainer-bookings/99999999');

        $response->assertStatus(404);
    }

    // ================================================================
    // SCENARIO 14 — Commission settings
    // ================================================================

    public function test_admin_can_get_commission_settings()
    {
        $response = $this->withHeaders($this->auth($this->adminToken))
            ->getJson('/api/admin/commission');

        $response->assertStatus(200)
                 ->assertJsonStructure(['success', 'data']);
    }

    public function test_admin_can_set_global_commission()
    {
        $response = $this->withHeaders($this->auth($this->adminToken))
            ->postJson('/api/admin/commission/global', [
                'commission_percentage' => 15.0,
                'effective_from'        => now()->toDateString(),
            ]);

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('commission_settings', [
            'entity_type'           => 'global',
            'commission_percentage' => 15.0,
        ]);
    }

    public function test_admin_can_set_trainer_specific_commission()
    {
        $response = $this->withHeaders($this->auth($this->adminToken))
            ->postJson("/api/admin/commission/trainer/{$this->trainer->id}", [
                'commission_percentage' => 10.0,
                'effective_from'        => now()->toDateString(),
            ]);

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('commission_settings', [
            'entity_type'           => 'trainer',
            'entity_id'             => $this->trainer->id,
            'commission_percentage' => 10.0,
        ]);
    }

    public function test_admin_can_remove_trainer_specific_commission()
    {
        CommissionSetting::updateOrCreate(
            ['entity_type' => 'trainer', 'entity_id' => $this->trainer->id],
            [
                'commission_percentage' => 12.0,
                'effective_from'        => now()->toDateString(),
                'is_active'             => true,
                'created_by'            => $this->admin->id,
            ]
        );

        $response = $this->withHeaders($this->auth($this->adminToken))
            ->deleteJson("/api/admin/commission/trainer/{$this->trainer->id}");

        $response->assertStatus(200)->assertJsonPath('success', true);
    }

    public function test_admin_can_view_commission_history()
    {
        $response = $this->withHeaders($this->auth($this->adminToken))
            ->getJson('/api/admin/commission/history');

        $response->assertStatus(200)
                 ->assertJsonStructure(['success', 'data']);
    }


    // ================================================================
    // SCENARIO 15 — Payouts
    // ================================================================

    public function test_admin_can_list_payouts()
    {
        $this->createPayout();

        $response = $this->withHeaders($this->auth($this->adminToken))
            ->getJson('/api/admin/payouts');

        $response->assertStatus(200)
                 ->assertJsonStructure(['success', 'data']);
    }

    public function test_admin_can_view_payout_detail()
    {
        $payout = $this->createPayout();

        $response = $this->withHeaders($this->auth($this->adminToken))
            ->getJson("/api/admin/payouts/{$payout->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.id', $payout->id);
    }

    public function test_admin_can_approve_payout()
    {
        $payout = $this->createPayout('pending');

        $response = $this->withHeaders($this->auth($this->adminToken))
            ->postJson("/api/admin/payouts/{$payout->id}/approve", [
                'bank_name' => 'Al Rajhi Bank',
                'iban'      => 'SA0380000000608010167519',
            ]);

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('trainer_payouts', ['id' => $payout->id, 'status' => 'approved']);
    }

    public function test_admin_cannot_approve_already_approved_payout()
    {
        $payout = $this->createPayout('approved');

        $response = $this->withHeaders($this->auth($this->adminToken))
            ->postJson("/api/admin/payouts/{$payout->id}/approve");

        $response->assertStatus(400);
    }

    public function test_admin_can_reject_payout()
    {
        $payout = $this->createPayout('pending');

        $response = $this->withHeaders($this->auth($this->adminToken))
            ->postJson("/api/admin/payouts/{$payout->id}/reject", [
                'reason' => 'Incorrect IBAN provided',
            ]);

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('trainer_payouts', ['id' => $payout->id, 'status' => 'failed']);
    }

    public function test_admin_reject_payout_requires_reason()
    {
        $payout = $this->createPayout('pending');

        $response = $this->withHeaders($this->auth($this->adminToken))
            ->postJson("/api/admin/payouts/{$payout->id}/reject", []);

        $response->assertStatus(422);
    }

    public function test_admin_cannot_reject_paid_payout()
    {
        $payout = $this->createPayout('paid');

        $response = $this->withHeaders($this->auth($this->adminToken))
            ->postJson("/api/admin/payouts/{$payout->id}/reject", ['reason' => 'Too late']);

        $response->assertStatus(400);
    }

    public function test_admin_can_mark_payout_as_paid()
    {
        $payout = $this->createPayout('approved');

        $response = $this->withHeaders($this->auth($this->adminToken))
            ->postJson("/api/admin/payouts/{$payout->id}/mark-paid", [
                'transfer_ref' => 'IB20260612001234',
            ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure(['transfer_ref']);

        $this->assertDatabaseHas('trainer_payouts', [
            'id'           => $payout->id,
            'status'       => 'paid',
            'transfer_ref' => 'IB20260612001234',
        ]);
    }

    public function test_admin_cannot_mark_non_approved_payout_as_paid()
    {
        $payout = $this->createPayout('pending');

        $response = $this->withHeaders($this->auth($this->adminToken))
            ->postJson("/api/admin/payouts/{$payout->id}/mark-paid", [
                'transfer_ref' => 'IB20260612001234',
            ]);

        $response->assertStatus(400);
    }

    public function test_admin_payout_detail_returns_404_for_unknown()
    {
        $response = $this->withHeaders($this->auth($this->adminToken))
            ->getJson('/api/admin/payouts/99999999');

        $response->assertStatus(404);
    }

    // ================================================================
    // SCENARIO 16 — Admin stats & payments
    // ================================================================

    public function test_admin_can_view_trainer_stats_dashboard()
    {
        $response = $this->withHeaders($this->auth($this->adminToken))
            ->getJson('/api/admin/trainer-stats/dashboard');

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure(['data']);
    }

    public function test_admin_can_view_revenue_stats()
    {
        $response = $this->withHeaders($this->auth($this->adminToken))
            ->getJson('/api/admin/trainer-stats/revenue');

        $response->assertStatus(200)
                 ->assertJsonStructure(['success', 'data']);
    }

    public function test_admin_can_list_trainer_payments()
    {
        $response = $this->withHeaders($this->auth($this->adminToken))
            ->getJson('/api/admin/trainer-payments');

        $response->assertStatus(200)
                 ->assertJsonStructure(['success', 'data']);
    }

    public function test_admin_payment_detail_returns_404_for_unknown()
    {
        $response = $this->withHeaders($this->auth($this->adminToken))
            ->getJson('/api/admin/trainer-payments/99999999');

        $response->assertStatus(404);
    }

    // ================================================================
    // SCENARIO 17 — Auth / guard error cases
    // ================================================================

    public function test_unauthenticated_cannot_access_trainer_me()
    {
        $this->getJson('/api/trainer/me')->assertStatus(401);
    }

    public function test_unauthenticated_cannot_access_admin_trainers()
    {
        $this->getJson('/api/admin/trainers')->assertStatus(401);
    }

    public function test_unauthenticated_cannot_access_admin_stats()
    {
        $this->getJson('/api/admin/trainer-stats/dashboard')->assertStatus(401);
    }


    public function test_trainer_profile_returns_404_for_unknown_id()
    {
        $this->getJson('/api/trainers/99999999')->assertStatus(404);
    }
}
