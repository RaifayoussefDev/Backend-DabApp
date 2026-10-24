<?php

namespace Tests\Feature\Trainer;

use App\Models\City;
use App\Models\Trainer;
use App\Models\TrainerCourse;
use App\Models\TrainerLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * GET /api/courses/cities used an unqualified IlluminateSupportFacadesDB::raw() (always 500), and once that was
 * fixed it still returned 500 as soon as a published course existed: the aggregate ran through the TrainerCourse
 * model, whose appended price accessors need columns the query doesn't select.
 */
class CoursesCitiesEndpointTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cities_with_courses_returns_a_list(): void
    {
        $this->getJson('/api/courses/cities')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_cities_with_a_published_course_are_counted(): void
    {
        $city = City::first();
        $user = User::factory()->create(['role_id' => 2, 'is_active' => true, 'email' => 'citiestest_' . Str::random(8) . '@test.local']);
        $trainer = Trainer::create([
            'user_id' => $user->id, 'name' => 'Cities Test Trainer', 'specialty' => 'coaching',
            'price_per_hour' => 150.00, 'status' => 'approved', 'is_available' => true,
            'rating_average' => 0, 'total_sessions' => 0, 'likes_count' => 0, 'experience_years' => 5,
        ]);
        $location = TrainerLocation::create([
            'trainer_id' => $trainer->id, 'location_name' => 'Cities Test Arena', 'city_id' => $city->id, 'is_available' => true,
        ]);
        TrainerCourse::create([
            'trainer_id' => $trainer->id, 'price_type' => 'total', 'title' => 'Cities Test Course',
            'hours_per_session' => 2, 'total_sessions' => 1, 'original_price' => 300.00,
            'location_id' => $location->id, 'can_travel' => false, 'status' => 'published', 'is_active' => true,
        ]);

        $row = collect($this->getJson('/api/courses/cities')->assertOk()->json('data'))->firstWhere('id', $city->id);

        $this->assertNotNull($row);
        $this->assertSame($city->name, $row['name']);
        $this->assertGreaterThanOrEqual(1, (int) $row['courses_count']);
    }
}
