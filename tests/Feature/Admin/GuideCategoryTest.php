<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class GuideCategoryTest extends TestCase
{
    // use RefreshDatabase; // Be careful with RefreshDatabase if we don't want to wipe dev db. 
    // Usually standardized tests use in-memory sqlite or separate testing db. 
    // Given the user environment, I should be careful. 
    // I'll try to use a transaction or just create/delete. 
    // But `RefreshDatabase` is safest for clean state if configured correctly.
    // I will assume standard Laravel testing setup.

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_admin_can_list_guide_categories()
    {
        // Create an admin user
        // Assuming role_id 1 is Admin based on controller logic
        $admin = User::factory()->create([
            'role_id' => 1
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/guide-categories');

        $response->assertStatus(200);
    }
}
