<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class GuideCategoryTest extends TestCase
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    public function test_admin_can_create_category_with_arabic_fields()
    {
        $admin = User::factory()->create(['role_id' => 1, 'is_active' => true]); // Ensure user is active
        $token = JWTAuth::fromUser($admin);

        $payload = [
            'name' => 'Test Category ' . uniqid(),
            'name_ar' => 'فئة اختبار',
            'description' => 'Description EN',
            'description_ar' => 'وصف عربي',
            'icon' => 'test-icon',
            'color' => '#000000',
            'order_position' => 1
        ];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/admin/guide-categories', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name_ar' => 'فئة اختبار',
                'description_ar' => 'وصف عربي'
            ]);

        $this->assertDatabaseHas('guide_categories', [
            'name' => $payload['name'],
            'name_ar' => 'فئة اختبار',
            'description_ar' => 'وصف عربي'
        ]);
    }

    public function test_admin_can_update_category_with_arabic_fields()
    {
        $admin = User::factory()->create(['role_id' => 1, 'is_active' => true]);
        $token = JWTAuth::fromUser($admin);

        $category = \App\Models\GuideCategory::create([
            'name' => 'Old Name ' . uniqid(),
            'slug' => 'old-name-' . uniqid(),
        ]);

        $payload = [
            'name_ar' => 'اسم معدل',
            'description_ar' => 'وصف معدل'
        ];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->putJson("/api/admin/guide-categories/{$category->id}", $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('guide_categories', [
            'id' => $category->id,
            'name_ar' => 'اسم معدل',
            'description_ar' => 'وصف معدل'
        ]);
    }

    public function test_admin_can_list_guide_categories_with_arabic_fields()
    {
        $admin = User::factory()->create(['role_id' => 1, 'is_active' => true]);
        $token = JWTAuth::fromUser($admin);

        \App\Models\GuideCategory::create([
            'name' => 'List Test ' . uniqid(),
            'name_ar' => 'فئة للقائمة',
            'slug' => 'list-test-' . uniqid(),
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/admin/guide-categories');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'name_ar' => 'فئة للقائمة'
            ]);
    }
}
