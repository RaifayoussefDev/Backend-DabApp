<?php

namespace Tests\Feature\Trainer;

use App\Models\City;
use App\Models\Trainer;
use App\Models\TrainerBooking;
use App\Models\TrainerCourse;
use App\Models\TrainerCourseBooking;
use App\Models\TrainerCourseBookingSession;
use App\Models\TrainerLocation;
use App\Models\User;
use Tests\TestCase;

/**
 * A payment decline no longer cancels a booking outright — it stays held so the
 * client can retry within a 2-hour window. This suite covers the scheduled
 * command (`trainer-bookings:expire-unpaid`) that cancels whatever is still
 * unpaid once that window has passed.
 */
class ExpireUnpaidTrainerBookingsCommandTest extends TestCase
{
    protected User $trainerUser;
    protected User $clientUser;
    protected Trainer $trainer;
    protected TrainerLocation $location;
    protected City $city;

    protected function setUp(): void
    {
        parent::setUp();

        $uid = uniqid();
        $this->trainerUser = User::factory()->create(['role_id' => 2, 'is_active' => true, 'email' => "expiretrainer_{$uid}@test.local"]);
        $this->clientUser  = User::factory()->create(['role_id' => 2, 'is_active' => true, 'email' => "expireclient_{$uid}@test.local"]);
        $this->city = City::first();

        $this->trainer = Trainer::create([
            'user_id'          => $this->trainerUser->id,
            'name'             => 'Expiry Test Trainer',
            'specialty'        => 'coaching',
            'price_per_hour'   => 150.00,
            'status'           => 'approved',
            'is_available'     => true,
            'rating_average'   => 0,
            'total_sessions'   => 0,
            'likes_count'      => 0,
            'experience_years' => 5,
        ]);

        $this->location = TrainerLocation::create([
            'trainer_id'    => $this->trainer->id,
            'location_name' => 'Expiry Test Arena',
            'city_id'       => $this->city->id,
            'is_available'  => true,
        ]);
    }

    protected function tearDown(): void
    {
        TrainerBooking::where('trainer_id', $this->trainer->id)->delete();

        $courseBookingIds = TrainerCourseBooking::where('trainer_id', $this->trainer->id)->pluck('id');
        TrainerCourseBookingSession::whereIn('course_booking_id', $courseBookingIds)->delete();
        TrainerCourseBooking::whereIn('id', $courseBookingIds)->delete();
        TrainerCourse::where('trainer_id', $this->trainer->id)->delete();

        $this->location->delete();
        $this->trainer->delete();
        $this->trainerUser->delete();
        $this->clientUser->delete();

        parent::tearDown();
    }

    private function makeBooking(string $paymentStatus, string $createdAt, string $status = 'confirmed'): TrainerBooking
    {
        $booking = TrainerBooking::create([
            'trainer_id'     => $this->trainer->id,
            'user_id'        => $this->clientUser->id,
            'location_id'    => $this->location->id,
            'booking_date'   => now()->addWeek()->toDateString(),
            'start_time'     => '08:00',
            'end_time'       => '10:00',
            'duration_hours' => 2,
            'status'         => $status,
            'price'          => 300.00,
            'payment_status' => $paymentStatus,
        ]);
        $booking->forceFill(['created_at' => $createdAt])->save();

        return $booking->fresh();
    }

    private function makeCourseBooking(string $paymentStatus, string $createdAt, string $status = 'confirmed'): TrainerCourseBooking
    {
        $course = TrainerCourse::create([
            'trainer_id'        => $this->trainer->id,
            'price_type'        => 'total',
            'title'             => 'Expiry Test Course',
            'hours_per_session' => 2,
            'total_sessions'    => 1,
            'original_price'    => 300.00,
            'location_id'       => $this->location->id,
            'can_travel'        => false,
            'status'            => 'published',
            'is_active'         => true,
        ]);

        $courseBooking = TrainerCourseBooking::create([
            'course_id'      => $course->id,
            'trainer_id'     => $this->trainer->id,
            'user_id'        => $this->clientUser->id,
            'status'         => $status,
            'total_price'    => 300.00,
            'payment_status' => $paymentStatus,
        ]);
        $courseBooking->forceFill(['created_at' => $createdAt])->save();

        $session = new TrainerCourseBookingSession([
            'course_booking_id' => $courseBooking->id,
            'trainer_id'        => $this->trainer->id,
            'session_number'    => 1,
            'booking_date'      => now()->addWeek()->toDateString(),
            'start_time'        => '08:00',
            'end_time'          => '10:00',
            'status'            => 'scheduled',
        ]);
        $session->generateOtps();
        $session->save();

        return $courseBooking->fresh();
    }

    public function test_cancels_session_booking_unpaid_past_grace_period()
    {
        $booking = $this->makeBooking('failed', now()->subHours(3)->toDateTimeString());

        $this->artisan('trainer-bookings:expire-unpaid')->assertExitCode(0);

        $this->assertDatabaseHas('trainer_bookings', ['id' => $booking->id, 'status' => 'cancelled']);
    }

    public function test_leaves_recent_unpaid_session_booking_alone()
    {
        $booking = $this->makeBooking('failed', now()->subMinutes(30)->toDateTimeString());

        $this->artisan('trainer-bookings:expire-unpaid')->assertExitCode(0);

        $this->assertDatabaseHas('trainer_bookings', ['id' => $booking->id, 'status' => 'confirmed']);
    }

    public function test_leaves_paid_session_booking_alone_even_if_old()
    {
        $booking = $this->makeBooking('paid', now()->subHours(3)->toDateTimeString());

        $this->artisan('trainer-bookings:expire-unpaid')->assertExitCode(0);

        $this->assertDatabaseHas('trainer_bookings', ['id' => $booking->id, 'status' => 'confirmed']);
    }

    public function test_cancels_course_booking_and_cascades_sessions_unpaid_past_grace_period()
    {
        $courseBooking = $this->makeCourseBooking('failed', now()->subHours(3)->toDateTimeString());

        $this->artisan('trainer-bookings:expire-unpaid')->assertExitCode(0);

        $this->assertDatabaseHas('trainer_course_bookings', ['id' => $courseBooking->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('trainer_course_booking_sessions', [
            'course_booking_id' => $courseBooking->id,
            'status'             => 'cancelled',
        ]);
    }

    public function test_leaves_recent_unpaid_course_booking_alone()
    {
        $courseBooking = $this->makeCourseBooking('pending', now()->subMinutes(30)->toDateTimeString());

        $this->artisan('trainer-bookings:expire-unpaid')->assertExitCode(0);

        $this->assertDatabaseHas('trainer_course_bookings', ['id' => $courseBooking->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('trainer_course_booking_sessions', [
            'course_booking_id' => $courseBooking->id,
            'status'             => 'scheduled',
        ]);
    }
}
