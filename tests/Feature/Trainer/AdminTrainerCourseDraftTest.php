<?php

namespace Tests\Feature\Trainer;

use App\Models\City;
use App\Models\Trainer;
use App\Models\TrainerCourse;
use App\Models\TrainerLocation;
use App\Models\User;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Admin — force a trainer course to draft with a reason (notifies the trainer)
 */
class AdminTrainerCourseDraftTest extends TestCase
{
    protected User $admin;
    protected User $trainerUser;
    protected Trainer $trainer;
    protected TrainerCourse $course;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('role_id', 1)->first() ?? User::factory()->create(['role_id' => 1]);
        $uid = uniqid();
        $this->trainerUser = User::factory()->create(['role_id' => 2, 'is_active' => true, 'email' => "draftcoursetrainer_{$uid}@test.local"]);
        $this->adminToken = JWTAuth::fromUser($this->admin);

        $this->trainer = Trainer::create([
            'user_id' => $this->trainerUser->id, 'name' => 'Draft Course Test Trainer', 'specialty' => 'coaching',
            'price_per_hour' => 150.00, 'status' => 'approved', 'is_available' => true,
            'rating_average' => 0, 'total_sessions' => 0, 'likes_count' => 0, 'experience_years' => 5,
        ]);
        $location = TrainerLocation::create([
            'trainer_id' => $this->trainer->id, 'location_name' => 'Draft Course Arena', 'city_id' => City::first()->id, 'is_available' => true,
        ]);
        $this->course = TrainerCourse::create([
            'trainer_id' => $this->trainer->id, 'price_type' => 'total', 'title' => 'Draft Test Course',
            'hours_per_session' => 2, 'total_sessions' => 1, 'original_price' => 300.00,
            'location_id' => $location->id, 'can_travel' => false, 'status' => 'published', 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->course->delete();
        TrainerLocation::where('trainer_id', $this->trainer->id)->delete();
        $this->trainer->delete();
        $this->trainerUser->delete();

        parent::tearDown();
    }

    private function auth(string $token): array
    {
        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_admin_can_set_published_course_to_draft_with_reason()
    {
        $response = $this->withHeaders($this->auth($this->adminToken))
            ->postJson("/api/admin/trainer-courses/{$this->course->id}/set-draft", [
                'reason' => 'Pricing does not match approved level rates',
            ]);

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('trainer_courses', [
            'id'           => $this->course->id,
            'status'       => 'draft',
            'draft_reason' => 'Pricing does not match approved level rates',
        ]);
    }

    public function test_set_draft_requires_reason()
    {
        $this->withHeaders($this->auth($this->adminToken))
            ->postJson("/api/admin/trainer-courses/{$this->course->id}/set-draft", [])
            ->assertStatus(422);
    }

    public function test_cannot_set_already_draft_course_to_draft()
    {
        $this->course->update(['status' => 'draft']);

        $this->withHeaders($this->auth($this->adminToken))
            ->postJson("/api/admin/trainer-courses/{$this->course->id}/set-draft", ['reason' => 'x'])
            ->assertStatus(400);
    }

    public function test_set_draft_returns_404_for_unknown_course()
    {
        $this->withHeaders($this->auth($this->adminToken))
            ->postJson('/api/admin/trainer-courses/99999999/set-draft', ['reason' => 'x'])
            ->assertStatus(404);
    }

    public function test_unauthenticated_cannot_set_draft()
    {
        $this->postJson("/api/admin/trainer-courses/{$this->course->id}/set-draft", ['reason' => 'x'])
            ->assertStatus(401);
    }

    public function test_publishing_again_clears_draft_reason()
    {
        $this->course->update(['status' => 'draft', 'draft_reason' => 'Some reason']);
        $token = JWTAuth::fromUser($this->trainerUser);

        $this->withHeaders($this->auth($token))
            ->postJson("/api/trainer/courses/{$this->course->id}/publish")
            ->assertStatus(200);

        $this->assertDatabaseHas('trainer_courses', [
            'id'           => $this->course->id,
            'status'       => 'published',
            'draft_reason' => null,
        ]);
    }
}
