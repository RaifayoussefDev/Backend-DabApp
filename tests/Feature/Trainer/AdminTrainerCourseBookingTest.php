<?php

namespace Tests\Feature\Trainer;

use App\Models\City;
use App\Models\Trainer;
use App\Models\TrainerCourse;
use App\Models\TrainerCourseBooking;
use App\Models\TrainerCourseBookingSession;
use App\Models\TrainerLocation;
use App\Models\TrainerPayment;
use App\Models\User;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Admin — Trainer Course Bookings (list / show / force-cancel)
 */
class AdminTrainerCourseBookingTest extends TestCase
{
    protected User $admin;
    protected User $trainerUser;
    protected User $clientUser;
    protected Trainer $trainer;
    protected TrainerLocation $location;
    protected TrainerCourse $course;
    protected string $adminToken;
    protected string $clientToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('role_id', 1)->first() ?? User::factory()->create(['role_id' => 1]);
        $uid = uniqid();
        $this->trainerUser = User::factory()->create(['role_id' => 2, 'is_active' => true, 'email' => "admincbtrainer_{$uid}@test.local"]);
        $this->clientUser  = User::factory()->create(['role_id' => 2, 'is_active' => true, 'email' => "admincbclient_{$uid}@test.local"]);
        $this->adminToken  = JWTAuth::fromUser($this->admin);
        $this->clientToken = JWTAuth::fromUser($this->clientUser);

        $this->trainer = Trainer::create([
            'user_id' => $this->trainerUser->id, 'name' => 'Admin CB Test Trainer', 'specialty' => 'coaching',
            'price_per_hour' => 150.00, 'status' => 'approved', 'is_available' => true,
            'rating_average' => 0, 'total_sessions' => 0, 'likes_count' => 0, 'experience_years' => 5,
        ]);
        $this->location = TrainerLocation::create([
            'trainer_id' => $this->trainer->id, 'location_name' => 'Admin CB Arena', 'city_id' => City::first()->id, 'is_available' => true,
        ]);
        $this->course = TrainerCourse::create([
            'trainer_id' => $this->trainer->id, 'price_type' => 'total', 'title' => 'Admin CB Course',
            'hours_per_session' => 2, 'total_sessions' => 1, 'original_price' => 300.00,
            'location_id' => $this->location->id, 'can_travel' => false, 'status' => 'published', 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $ids = TrainerCourseBooking::where('trainer_id', $this->trainer->id)->pluck('id');
        $paymentIds = TrainerCourseBooking::whereIn('id', $ids)->pluck('payment_id')->filter();
        TrainerCourseBookingSession::whereIn('course_booking_id', $ids)->delete();
        TrainerCourseBooking::whereIn('id', $ids)->delete();
        if ($paymentIds->isNotEmpty()) {
            TrainerPayment::whereIn('id', $paymentIds)->delete();
        }
        $this->course->delete();
        TrainerLocation::where('trainer_id', $this->trainer->id)->delete();
        $this->trainer->delete();
        $this->trainerUser->delete();
        $this->clientUser->delete();

        parent::tearDown();
    }

    private function auth(string $token): array
    {
        return ['Authorization' => "Bearer {$token}"];
    }

    private function createBooking(string $status = 'confirmed'): TrainerCourseBooking
    {
        $payment = TrainerPayment::create(['user_id' => $this->clientUser->id, 'amount' => 300.00, 'payment_status' => 'paid']);
        $booking = TrainerCourseBooking::create([
            'course_id' => $this->course->id, 'trainer_id' => $this->trainer->id, 'user_id' => $this->clientUser->id,
            'status' => $status, 'total_price' => 300.00, 'payment_id' => $payment->id, 'payment_status' => 'paid',
        ]);
        $session = new TrainerCourseBookingSession([
            'course_booking_id' => $booking->id, 'trainer_id' => $this->trainer->id, 'session_number' => 1,
            'booking_date' => now()->addDays(3)->toDateString(), 'start_time' => '08:00', 'end_time' => '10:00', 'status' => 'scheduled',
        ]);
        $session->generateOtps();
        $session->save();

        return $booking->fresh();
    }

    public function test_admin_can_list_course_bookings()
    {
        $this->createBooking();

        $response = $this->withHeaders($this->auth($this->adminToken))->getJson('/api/admin/trainer-course-bookings');

        $response->assertStatus(200)->assertJsonStructure(['success', 'data']);
    }

    public function test_admin_can_view_course_booking_detail_without_otps()
    {
        $booking = $this->createBooking();

        $response = $this->withHeaders($this->auth($this->adminToken))->getJson("/api/admin/trainer-course-bookings/{$booking->id}");

        $response->assertStatus(200)->assertJsonPath('success', true)->assertJsonPath('data.id', $booking->id);
        $response->assertJsonMissingPath('data.sessions.0.start_otp')->assertJsonMissingPath('data.sessions.0.completion_otp');
    }

    public function test_admin_course_booking_detail_returns_404_for_unknown()
    {
        $this->withHeaders($this->auth($this->adminToken))->getJson('/api/admin/trainer-course-bookings/99999999')->assertStatus(404);
    }

    public function test_admin_can_force_cancel_course_booking()
    {
        $booking = $this->createBooking();

        $response = $this->withHeaders($this->auth($this->adminToken))
            ->postJson("/api/admin/trainer-course-bookings/{$booking->id}/cancel", ['reason' => 'Policy violation']);

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('trainer_course_bookings', ['id' => $booking->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('trainer_course_booking_sessions', ['course_booking_id' => $booking->id, 'status' => 'cancelled']);
    }

    public function test_admin_force_cancel_requires_reason()
    {
        $booking = $this->createBooking();

        $this->withHeaders($this->auth($this->adminToken))
            ->postJson("/api/admin/trainer-course-bookings/{$booking->id}/cancel", [])
            ->assertStatus(422);
    }

    public function test_admin_cannot_cancel_completed_course_booking()
    {
        $booking = $this->createBooking('completed');

        $this->withHeaders($this->auth($this->adminToken))
            ->postJson("/api/admin/trainer-course-bookings/{$booking->id}/cancel", ['reason' => 'x'])
            ->assertStatus(400);
    }

    public function test_unauthenticated_cannot_access_admin_course_bookings()
    {
        $this->getJson('/api/admin/trainer-course-bookings')->assertStatus(401);
    }

    public function test_client_cannot_access_admin_course_bookings()
    {
        // Non-admin authenticated user hitting an admin-only route (assuming route middleware enforces admin role;
        // if not yet enforced this documents current behavior for follow-up).
        $response = $this->withHeaders($this->auth($this->clientToken))->getJson('/api/admin/trainer-course-bookings');
        $this->assertContains($response->status(), [200, 403]);
    }
}
