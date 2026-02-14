<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\GuideTag;
use Tests\TestCase;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class GuideTagTest extends TestCase
{
    public function test_admin_can_delete_guide_tag()
    {
        $admin = User::first();
        if (!$admin || $admin->role_id != 1) {
            $admin = User::factory()->create(['role_id' => 1]);
        }

        $token = JWTAuth::fromUser($admin);

        $name = 'Test Tag ' . Str::random(10);
        $slug = Str::slug($name);

        $tag = GuideTag::create([
            'name' => $name,
            'slug' => $slug
        ]);

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->deleteJson("/api/admin/guide-tags/{$tag->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('guide_tags', ['id' => $tag->id]);
    }

    public function test_admin_can_bulk_delete_guide_tags()
    {
        $admin = User::first();
        if (!$admin || $admin->role_id != 1) {
            $admin = User::factory()->create(['role_id' => 1]);
        }

        $token = JWTAuth::fromUser($admin);

        $name1 = 'Bulk Tag ' . Str::random(10);
        $tag1 = GuideTag::create(['name' => $name1, 'slug' => Str::slug($name1)]);

        $name2 = 'Bulk Tag ' . Str::random(10);
        $tag2 = GuideTag::create(['name' => $name2, 'slug' => Str::slug($name2)]);

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->postJson("/api/admin/guide-tags/bulk-delete", [
                'ids' => [$tag1->id, $tag2->id]
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('guide_tags', ['id' => $tag1->id]);
        $this->assertDatabaseMissing('guide_tags', ['id' => $tag2->id]);
    }
}
