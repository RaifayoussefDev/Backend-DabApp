<?php

namespace Tests\Feature\Trainer;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * GET /api/courses/cities used an unqualified IlluminateSupportFacadesDB::raw() and always returned 500.
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
}
