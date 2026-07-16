<?php

namespace Tests\Feature\Trainer;

use App\Models\City;
use App\Models\PaymentSplit;
use App\Models\Trainer;
use App\Models\TrainerCourse;
use App\Models\TrainerCourseBooking;
use App\Models\TrainerCourseBookingSession;
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
 * Trainer Course Booking — Full Flow Tests
 *
 * Scenarios:
 *  1. Book all sessions of a course (PayTabs mocked) + wrong session count / duplicate dates / bad slot rejected
 *  2. Payment webhook: approve (confirms + payout chain + trainer notified) / decline (cancels + cascades sessions)
 *  3. Trainer session lifecycle: start (OTP) → complete (OTP), last session completes the whole course booking
 *  4. Trainer never sees OTPs in any response
 *  5. Cancel course booking (+ refund) / review after completion
 */
class TrainerCourseBookingFlowTest extends TestCase
{
    protected User $trainerUser;
    protected User $clientUser;
    protected Trainer $trainer;
    protected TrainerLocation $location;
    protected City $city;

    protected string $trainerToken;
    protected string $clientToken;

    protected function setUp(): void
    {
        parent::setUp();

        $uid = uniqid();
        $this->trainerUser = User::factory()->create(['role_id' => 2, 'is_active' => true, 'email' => "coursetrainer_{$uid}@test.local"]);
        $this->clientUser  = User::factory()->create(['role_id' => 2, 'is_active' => true, 'email' => "coursetrainee_{$uid}@test.local"]);
        $this->trainerToken = JWTAuth::fromUser($this->trainerUser);
        $this->clientToken  = JWTAuth::fromUser($this->clientUser);
        $this->city = City::first();

        $this->trainer = Trainer::create([
            'user_id'          => $this->trainerUser->id,
            'name'             => 'Course Test Trainer',
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
            'location_name' => 'Course Test Arena',
            'city_id'       => $this->city->id,
            'is_available'  => true,
        ]);

        // Only Monday 08:00-18:00 is available — generateSlots(120min) yields 08:00-10:00, 10:00-12:00, ...
        TrainerSchedule::create([
            'trainer_id'   => $this->trainer->id,
            'day_of_week'  => 1,
            'start_time'   => '08:00',
            'end_time'     => '18:00',
            'is_available' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $courseBookingIds = TrainerCourseBooking::where('trainer_id', $this->trainer->id)->pluck('id');
        $paymentIds        = TrainerCourseBooking::whereIn('id', $courseBookingIds)->pluck('payment_id')->filter();

        TrainerReview::where('trainer_id', $this->trainer->id)->delete();
        TrainerPayout::where('trainer_id', $this->trainer->id)->delete();
        PaymentSplit::where('trainer_id', $this->trainer->id)->delete();
        TrainerCourseBookingSession::whereIn('course_booking_id', $courseBookingIds)->delete();
        TrainerCourseBooking::whereIn('id', $courseBookingIds)->delete();
        if ($paymentIds->isNotEmpty()) {
            TrainerPayment::whereIn('id', $paymentIds)->delete();
        }
        TrainerCourse::where('trainer_id', $this->trainer->id)->delete();
        TrainerSchedule::where('trainer_id', $this->trainer->id)->delete();
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

    private function firstMonday(): \Carbon\Carbon
    {
        return now()->next('Monday');
    }

    private function createCourse(int $totalSessions = 2): TrainerCourse
    {
        return TrainerCourse::create([
            'trainer_id'        => $this->trainer->id,
            'price_type'        => 'total',
            'title'             => 'Course Booking Test Course',
            'hours_per_session' => 2,
            'total_sessions'    => $totalSessions,
            'original_price'    => 300.00,
            'location_id'       => $this->location->id,
            'can_travel'        => false,
            'status'            => 'published',
            'is_active'         => true,
        ]);
    }

    /** Course booking with N sessions already scheduled/paid, ready to start. */
    private function createConfirmedCourseBooking(int $totalSessions = 2): TrainerCourseBooking
    {
        $course = $this->createCourse($totalSessions);

        $payment = TrainerPayment::create([
            'user_id'        => $this->clientUser->id,
            'amount'         => 300.00,
            'payment_status' => 'paid',
            'tran_ref'       => 'TST-' . uniqid(),
            'cart_id'        => 'TRAINERCOURSE_TST_' . uniqid(),
            'currency'       => 'SAR',
        ]);

        $courseBooking = TrainerCourseBooking::create([
            'course_id'      => $course->id,
            'trainer_id'     => $this->trainer->id,
            'user_id'        => $this->clientUser->id,
            'status'         => 'confirmed',
            'total_price'    => 300.00,
            'payment_id'     => $payment->id,
            'payment_status' => 'paid',
            'confirmed_at'   => now(),
        ]);
        $payment->update(['cart_id' => 'TRAINERCOURSE_' . $courseBooking->id]);

        for ($i = 1; $i <= $totalSessions; $i++) {
            $session = new TrainerCourseBookingSession([
                'course_booking_id' => $courseBooking->id,
                'trainer_id'        => $this->trainer->id,
                'session_number'    => $i,
                'booking_date'      => $this->firstMonday()->copy()->addWeeks($i - 1)->toDateString(),
                'start_time'        => '08:00',
                'end_time'          => '10:00',
                'status'            => 'scheduled',
            ]);
            $session->generateOtps();
            $session->save();
        }

        return $courseBooking->fresh();
    }

    // ================================================================
    // SCENARIO 1 — Book all sessions (PayTabs mocked)
    // ================================================================

    public function test_client_can_book_all_course_sessions_with_paytabs_mocked()
    {
        Http::fake([
            '*paytabs*' => Http::response([
                'redirect_url' => 'https://secure.paytabs.sa/payment/wr/faketoken',
                'tran_ref'     => 'TST-' . time(),
            ], 200),
        ]);

        $course = $this->createCourse(2);
        $d1 = $this->firstMonday()->toDateString();
        $d2 = $this->firstMonday()->copy()->addWeek()->toDateString();

        $response = $this->withHeaders($this->auth($this->clientToken))
            ->postJson("/api/trainers/{$this->trainer->id}/courses/{$course->id}/book", [
                'sessions' => [
                    ['session_number' => 1, 'booking_date' => $d1, 'start_time' => '08:00'],
                    ['session_number' => 2, 'booking_date' => $d2, 'start_time' => '10:00'],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['course_booking_id', 'payment_url', 'total_price', 'total_price_aed', 'sessions']]);

        // 300 SAR / 1.02 (1 AED = 1.02 SAR) = 294.12 AED — the mobile PayTabs SDK
        // needs this since it always settles in AED regardless of display currency.
        $this->assertEquals(294.12, $response->json('data.total_price_aed'));

        $sessions = $response->json('data.sessions');
        $this->assertCount(2, $sessions);
        $this->assertNotEmpty($sessions[0]['start_otp']);
        $this->assertNotEmpty($sessions[0]['completion_otp']);

        $this->assertDatabaseHas('trainer_course_bookings', [
            'id'          => $response->json('data.course_booking_id'),
            'course_id'   => $course->id,
            'total_price' => 300.00,
        ]);
    }

    public function test_course_detail_includes_price_in_aed()
    {
        $course = $this->createCourse(2);

        $response = $this->getJson("/api/trainers/{$this->trainer->id}/courses/{$course->id}");

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertEquals(294.12, (float) $response->json('data.total_price_aed'));
    }

    public function test_booking_rejects_wrong_session_count()
    {
        $course = $this->createCourse(2);

        $response = $this->withHeaders($this->auth($this->clientToken))
            ->postJson("/api/trainers/{$this->trainer->id}/courses/{$course->id}/book", [
                'sessions' => [
                    ['session_number' => 1, 'booking_date' => $this->firstMonday()->toDateString(), 'start_time' => '08:00'],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_booking_rejects_duplicate_dates()
    {
        $course = $this->createCourse(2);
        $date = $this->firstMonday()->toDateString();

        $response = $this->withHeaders($this->auth($this->clientToken))
            ->postJson("/api/trainers/{$this->trainer->id}/courses/{$course->id}/book", [
                'sessions' => [
                    ['session_number' => 1, 'booking_date' => $date, 'start_time' => '08:00'],
                    ['session_number' => 2, 'booking_date' => $date, 'start_time' => '10:00'],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_booking_rejects_slot_outside_schedule()
    {
        $course = $this->createCourse(2);
        // Tuesday has no schedule row configured — no free slots that day.
        $tuesday = $this->firstMonday()->copy()->addDay()->toDateString();

        $response = $this->withHeaders($this->auth($this->clientToken))
            ->postJson("/api/trainers/{$this->trainer->id}/courses/{$course->id}/book", [
                'sessions' => [
                    ['session_number' => 1, 'booking_date' => $tuesday, 'start_time' => '08:00'],
                    ['session_number' => 2, 'booking_date' => $this->firstMonday()->copy()->addWeek()->toDateString(), 'start_time' => '08:00'],
                ],
            ]);

        $response->assertStatus(422);
    }

    // ================================================================
    // SCENARIO 2 — Payment webhook
    // ================================================================

    public function test_payment_webhook_approval_confirms_and_creates_payout_chain()
    {
        $course = $this->createCourse(1);
        $payment = TrainerPayment::create(['user_id' => $this->clientUser->id, 'amount' => 300.00, 'payment_status' => 'pending']);
        $courseBooking = TrainerCourseBooking::create([
            'course_id' => $course->id, 'trainer_id' => $this->trainer->id, 'user_id' => $this->clientUser->id,
            'status' => 'confirmed', 'total_price' => 300.00, 'payment_id' => $payment->id, 'payment_status' => 'pending',
        ]);
        $payment->update(['cart_id' => 'TRAINERCOURSE_' . $courseBooking->id]);

        $response = $this->postJson('/api/trainer/course-bookings/payments/callback', [
            'tran_ref' => 'TST-APPROVE',
            'cart_id'  => 'TRAINERCOURSE_' . $courseBooking->id,
            'payment_result' => ['response_status' => 'A', 'response_code' => '000', 'response_message' => 'Authorised'],
        ]);

        $response->assertStatus(200)->assertJsonPath('success', true);

        $this->assertDatabaseHas('trainer_course_bookings', ['id' => $courseBooking->id, 'status' => 'confirmed', 'payment_status' => 'paid']);
        $this->assertDatabaseHas('payment_splits', ['course_booking_id' => $courseBooking->id]);
        $this->assertDatabaseHas('trainer_payouts', ['trainer_id' => $this->trainer->id]);
    }

    public function test_payment_webhook_decline_keeps_booking_held_for_retry()
    {
        $courseBooking = $this->createConfirmedCourseBooking(2);
        $courseBooking->update(['payment_status' => 'pending', 'status' => 'confirmed']);
        $courseBooking->payment->update(['payment_status' => 'pending', 'cart_id' => 'TRAINERCOURSE_' . $courseBooking->id]);

        $response = $this->postJson('/api/trainer/course-bookings/payments/callback', [
            'tran_ref' => 'TST-DECLINE',
            'cart_id'  => 'TRAINERCOURSE_' . $courseBooking->id,
            'payment_result' => ['response_status' => 'D', 'response_code' => '400', 'response_message' => 'Declined'],
        ]);

        // A declined payment gives the client a 2-hour retry window instead of
        // cancelling outright — the booking (and its sessions' slots) stay held.
        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('trainer_course_bookings', [
            'id'             => $courseBooking->id,
            'status'         => 'confirmed',
            'payment_status' => 'failed',
        ]);
        $this->assertDatabaseMissing('trainer_course_booking_sessions', [
            'course_booking_id' => $courseBooking->id,
            'status'             => 'cancelled',
        ]);
    }

    // ================================================================
    // SCENARIO 2b — Mobile SDK payment verification (no browser redirect)
    // ================================================================

    public function test_verify_payment_already_paid_is_idempotent()
    {
        // Simulate the webhook having already confirmed payment (creating the split)
        // before the mobile SDK's own verify-payment call races in behind it.
        $courseBooking = $this->createConfirmedCourseBooking(2);
        $courseBooking->update(['payment_status' => 'pending', 'status' => 'confirmed']);
        $courseBooking->payment->update(['payment_status' => 'pending', 'cart_id' => 'TRAINERCOURSE_' . $courseBooking->id]);
        PaymentSplit::where('course_booking_id', $courseBooking->id)->delete();

        $this->postJson('/api/trainer/course-bookings/payments/callback', [
            'tran_ref' => 'TST-WEBHOOK-FIRST',
            'cart_id'  => 'TRAINERCOURSE_' . $courseBooking->id,
            'payment_result' => ['response_status' => 'A', 'response_code' => '100', 'response_message' => 'Approved'],
        ])->assertStatus(200);
        $this->assertEquals(1, PaymentSplit::where('course_booking_id', $courseBooking->id)->count());

        $response = $this->withHeaders($this->auth($this->clientToken))
            ->postJson('/api/trainer/course-bookings/verify-payment', [
                'course_booking_id' => $courseBooking->id,
                'tran_ref'          => 'TST-WEBHOOK-FIRST',
            ]);

        $response->assertStatus(200)->assertJsonPath('success', true)->assertJsonPath('data.payment_status', 'paid');
        // Must not create a second payment split now that the SDK verify races in.
        $this->assertEquals(1, PaymentSplit::where('course_booking_id', $courseBooking->id)->count());
    }

    public function test_verify_payment_confirms_via_paytabs_api()
    {
        $courseBooking = $this->createConfirmedCourseBooking(2);
        $courseBooking->update(['payment_status' => 'pending', 'status' => 'confirmed']);
        $courseBooking->payment->update(['payment_status' => 'pending']);
        PaymentSplit::where('course_booking_id', $courseBooking->id)->delete();

        Http::fake([
            '*payment/query*' => Http::response([
                'payment_result' => ['response_status' => 'A', 'response_code' => '100', 'response_message' => 'Approved'],
            ], 200),
        ]);

        $response = $this->withHeaders($this->auth($this->clientToken))
            ->postJson('/api/trainer/course-bookings/verify-payment', [
                'course_booking_id' => $courseBooking->id,
                'tran_ref'          => 'TST-SDK-APPROVED',
            ]);

        $response->assertStatus(200)->assertJsonPath('success', true)->assertJsonPath('data.verification_method', 'paytabs_api');
        $this->assertDatabaseHas('trainer_course_bookings', ['id' => $courseBooking->id, 'status' => 'confirmed', 'payment_status' => 'paid']);
        $this->assertDatabaseHas('payment_splits', ['course_booking_id' => $courseBooking->id]);
    }

    public function test_verify_payment_declined_via_paytabs_api()
    {
        $courseBooking = $this->createConfirmedCourseBooking(2);
        $courseBooking->update(['payment_status' => 'pending', 'status' => 'confirmed']);
        $courseBooking->payment->update(['payment_status' => 'pending']);
        PaymentSplit::where('course_booking_id', $courseBooking->id)->delete();

        Http::fake([
            '*payment/query*' => Http::response([
                'payment_result' => ['response_status' => 'D', 'response_code' => '400', 'response_message' => 'Declined'],
            ], 200),
        ]);

        $response = $this->withHeaders($this->auth($this->clientToken))
            ->postJson('/api/trainer/course-bookings/verify-payment', [
                'course_booking_id' => $courseBooking->id,
                'tran_ref'          => 'TST-SDK-DECLINED',
            ]);

        $response->assertStatus(400)->assertJsonPath('success', false);
        $this->assertDatabaseHas('trainer_course_bookings', ['id' => $courseBooking->id, 'payment_status' => 'failed']);
    }

    public function test_verify_payment_trusts_sdk_when_skip_verification_is_true()
    {
        $courseBooking = $this->createConfirmedCourseBooking(2);
        $courseBooking->update(['payment_status' => 'pending', 'status' => 'confirmed']);
        $courseBooking->payment->update(['payment_status' => 'pending']);
        PaymentSplit::where('course_booking_id', $courseBooking->id)->delete();

        $response = $this->withHeaders($this->auth($this->clientToken))
            ->postJson('/api/trainer/course-bookings/verify-payment', [
                'course_booking_id'  => $courseBooking->id,
                'tran_ref'           => 'TST-SDK-TRUST',
                'skip_verification'  => true,
            ]);

        $response->assertStatus(200)->assertJsonPath('success', true)->assertJsonPath('data.verification_method', 'sdk_trust');
        $this->assertDatabaseHas('trainer_course_bookings', ['id' => $courseBooking->id, 'status' => 'confirmed', 'payment_status' => 'paid']);
    }

    public function test_verify_payment_rejects_another_users_booking()
    {
        $courseBooking = $this->createConfirmedCourseBooking(2);
        $otherUser = User::factory()->create(['role_id' => 2, 'is_active' => true, 'email' => 'other_' . uniqid() . '@test.local']);
        $otherToken = JWTAuth::fromUser($otherUser);

        $response = $this->withHeaders($this->auth($otherToken))
            ->postJson('/api/trainer/course-bookings/verify-payment', [
                'course_booking_id' => $courseBooking->id,
                'tran_ref'          => 'TST-OTHER-USER',
            ]);

        $response->assertStatus(403);
        $otherUser->delete();
    }

    public function test_verify_payment_returns_404_for_unknown_booking()
    {
        $this->withHeaders($this->auth($this->clientToken))
            ->postJson('/api/trainer/course-bookings/verify-payment', [
                'course_booking_id' => 999999999,
                'tran_ref'          => 'TST-UNKNOWN',
            ])
            ->assertStatus(422); // fails the 'exists:trainer_course_bookings,id' validation rule
    }

    // ================================================================
    // SCENARIO 3 — Session lifecycle (OTP-gated)
    // ================================================================

    public function test_trainer_can_start_and_complete_session_with_correct_otp()
    {
        $courseBooking = $this->createConfirmedCourseBooking(1);
        $session = $courseBooking->sessions()->first();

        $wrongStart = $this->withHeaders($this->auth($this->trainerToken))
            ->postJson("/api/trainer/course-sessions/{$session->id}/start", ['otp' => '000000']);
        $wrongStart->assertStatus(422);

        $start = $this->withHeaders($this->auth($this->trainerToken))
            ->postJson("/api/trainer/course-sessions/{$session->id}/start", ['otp' => $session->start_otp]);
        $start->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('trainer_course_booking_sessions', ['id' => $session->id, 'status' => 'in_progress']);
        $this->assertDatabaseHas('trainer_course_bookings', ['id' => $courseBooking->id, 'status' => 'in_progress']);

        $wrongComplete = $this->withHeaders($this->auth($this->trainerToken))
            ->postJson("/api/trainer/course-sessions/{$session->id}/complete", ['otp' => '111111']);
        $wrongComplete->assertStatus(422);

        $complete = $this->withHeaders($this->auth($this->trainerToken))
            ->postJson("/api/trainer/course-sessions/{$session->id}/complete", ['otp' => $session->completion_otp]);
        $complete->assertStatus(200)->assertJsonPath('success', true);

        // Last (only) session completed → whole course booking completed
        $this->assertDatabaseHas('trainer_course_booking_sessions', ['id' => $session->id, 'status' => 'completed']);
        $this->assertDatabaseHas('trainer_course_bookings', ['id' => $courseBooking->id, 'status' => 'completed']);
    }

    public function test_multi_session_course_only_completes_after_last_session()
    {
        $courseBooking = $this->createConfirmedCourseBooking(2);
        $sessions = $courseBooking->sessions()->orderBy('session_number')->get();

        foreach ($sessions as $session) {
            $this->withHeaders($this->auth($this->trainerToken))
                ->postJson("/api/trainer/course-sessions/{$session->id}/start", ['otp' => $session->start_otp])
                ->assertStatus(200);
        }

        $first = $this->withHeaders($this->auth($this->trainerToken))
            ->postJson("/api/trainer/course-sessions/{$sessions[0]->id}/complete", ['otp' => $sessions[0]->completion_otp]);
        $first->assertStatus(200);

        $this->assertDatabaseHas('trainer_course_bookings', ['id' => $courseBooking->id, 'status' => 'in_progress']);

        $second = $this->withHeaders($this->auth($this->trainerToken))
            ->postJson("/api/trainer/course-sessions/{$sessions[1]->id}/complete", ['otp' => $sessions[1]->completion_otp]);
        $second->assertStatus(200);

        $this->assertDatabaseHas('trainer_course_bookings', ['id' => $courseBooking->id, 'status' => 'completed']);
    }

    public function test_other_trainer_cannot_manage_course_session()
    {
        $courseBooking = $this->createConfirmedCourseBooking(1);
        $session = $courseBooking->sessions()->first();

        $otherUser  = User::factory()->create(['role_id' => 2, 'is_active' => true]);
        $otherToken = JWTAuth::fromUser($otherUser);

        $response = $this->withHeaders($this->auth($otherToken))
            ->postJson("/api/trainer/course-sessions/{$session->id}/start", ['otp' => $session->start_otp]);

        $response->assertStatus(403);
        $otherUser->delete();
    }

    // ================================================================
    // SCENARIO 4 — Trainer never sees OTPs
    // ================================================================

    public function test_trainer_facing_endpoints_never_expose_otps()
    {
        $courseBooking = $this->createConfirmedCourseBooking(1);
        $session = $courseBooking->sessions()->first();

        $list = $this->withHeaders($this->auth($this->trainerToken))->getJson('/api/trainer/course-sessions');
        $list->assertStatus(200)->assertJsonMissingPath('data.0.start_otp')->assertJsonMissingPath('data.0.completion_otp');

        $detail = $this->withHeaders($this->auth($this->trainerToken))->getJson("/api/trainer/course-sessions/{$session->id}");
        $detail->assertStatus(200)->assertJsonMissingPath('data.start_otp')->assertJsonMissingPath('data.completion_otp');
    }

    // ================================================================
    // SCENARIO 5 — Cancel / review
    // ================================================================

    public function test_client_can_cancel_confirmed_course_booking_triggering_refund()
    {
        Http::fake(['*paytabs*' => Http::response(['payment_status' => 'Refunded'], 200)]);

        $courseBooking = $this->createConfirmedCourseBooking(2);

        $response = $this->withHeaders($this->auth($this->clientToken))
            ->postJson("/api/trainer/course-bookings/{$courseBooking->id}/cancel");

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('trainer_course_bookings', ['id' => $courseBooking->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('trainer_course_booking_sessions', ['course_booking_id' => $courseBooking->id, 'status' => 'cancelled']);
    }

    public function test_client_cannot_review_before_course_completed()
    {
        $courseBooking = $this->createConfirmedCourseBooking(1);

        $response = $this->withHeaders($this->auth($this->clientToken))
            ->postJson("/api/trainer/course-bookings/{$courseBooking->id}/review", ['rating' => 5]);

        $response->assertStatus(400);
    }

    public function test_client_can_review_after_course_completed()
    {
        $courseBooking = $this->createConfirmedCourseBooking(1);
        $session = $courseBooking->sessions()->first();

        $this->withHeaders($this->auth($this->trainerToken))
            ->postJson("/api/trainer/course-sessions/{$session->id}/start", ['otp' => $session->start_otp])
            ->assertStatus(200);
        $this->withHeaders($this->auth($this->trainerToken))
            ->postJson("/api/trainer/course-sessions/{$session->id}/complete", ['otp' => $session->completion_otp])
            ->assertStatus(200);

        $response = $this->withHeaders($this->auth($this->clientToken))
            ->postJson("/api/trainer/course-bookings/{$courseBooking->id}/review", [
                'rating'  => 5,
                'comment' => 'Great course!',
            ]);

        $response->assertStatus(201)->assertJsonPath('success', true);
        $this->assertDatabaseHas('trainer_reviews', [
            'course_booking_id' => $courseBooking->id,
            'trainer_id'        => $this->trainer->id,
            'rating'            => 5,
        ]);
    }

    public function test_unauthenticated_cannot_book_course()
    {
        $course = $this->createCourse(1);

        $response = $this->postJson("/api/trainers/{$this->trainer->id}/courses/{$course->id}/book", [
            'sessions' => [
                ['session_number' => 1, 'booking_date' => $this->firstMonday()->toDateString(), 'start_time' => '08:00'],
            ],
        ]);

        $response->assertStatus(401);
    }

    // ================================================================
    // SCENARIO 6 — Deleting a course must not silently destroy active bookings
    // ================================================================

    public function test_trainer_can_delete_draft_course_without_bookings()
    {
        $course = $this->createCourse(1);
        $course->update(['status' => 'draft']);

        $response = $this->withHeaders($this->auth($this->trainerToken))
            ->deleteJson("/api/trainer/courses/{$course->id}");

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseMissing('trainer_courses', ['id' => $course->id]);
    }

    public function test_trainer_cannot_delete_course_with_active_bookings()
    {
        $courseBooking = $this->createConfirmedCourseBooking(1);
        $courseBooking->course->update(['status' => 'draft']);

        $response = $this->withHeaders($this->auth($this->trainerToken))
            ->deleteJson("/api/trainer/courses/{$courseBooking->course_id}");

        $response->assertStatus(400)->assertJsonPath('success', false);
        // Must not have cascaded — the course and its booking are both still there.
        $this->assertDatabaseHas('trainer_courses', ['id' => $courseBooking->course_id]);
        $this->assertDatabaseHas('trainer_course_bookings', ['id' => $courseBooking->id]);
    }

    public function test_trainer_can_delete_course_once_its_bookings_are_cancelled()
    {
        Http::fake(['*paytabs*' => Http::response(['payment_status' => 'Refunded'], 200)]);

        $courseBooking = $this->createConfirmedCourseBooking(1);
        $courseId = $courseBooking->course_id;
        $courseBooking->course->update(['status' => 'draft']);

        $this->withHeaders($this->auth($this->clientToken))
            ->postJson("/api/trainer/course-bookings/{$courseBooking->id}/cancel")
            ->assertStatus(200);

        $response = $this->withHeaders($this->auth($this->trainerToken))
            ->deleteJson("/api/trainer/courses/{$courseId}");

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseMissing('trainer_courses', ['id' => $courseId]);
    }

    public function test_admin_cannot_delete_course_with_active_bookings()
    {
        $courseBooking = $this->createConfirmedCourseBooking(1);
        $admin = User::where('role_id', 1)->first() ?? User::factory()->create(['role_id' => 1]);
        $adminToken = JWTAuth::fromUser($admin);

        $response = $this->withHeaders($this->auth($adminToken))
            ->deleteJson("/api/admin/trainer-courses/{$courseBooking->course_id}");

        $response->assertStatus(400)->assertJsonPath('success', false);
        $this->assertDatabaseHas('trainer_courses', ['id' => $courseBooking->course_id]);
        $this->assertDatabaseHas('trainer_course_bookings', ['id' => $courseBooking->id]);
    }
}
