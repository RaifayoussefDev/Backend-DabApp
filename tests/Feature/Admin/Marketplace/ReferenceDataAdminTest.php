<?php

namespace Tests\Feature\Admin\Marketplace;

use App\Models\Marketplace\Brand;
use App\Models\Marketplace\Category;
use App\Models\Marketplace\Product;
use App\Models\Marketplace\ShippingProvider;
use App\Models\Marketplace\Vendor;
use App\Models\Marketplace\VendorShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Step 1 admin endpoints: categories, brands, shipping providers.
 * DatabaseTransactions: nothing is persisted, safe on the dev database.
 */
class ReferenceDataAdminTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): array
    {
        $user = User::factory()->create(['email' => Str::uuid() . '@mp-test.local', 'role_id' => 1, 'is_active' => true]);

        return ['Authorization' => 'Bearer ' . JWTAuth::fromUser($user)];
    }

    private function vendor(): Vendor
    {
        $user = User::factory()->create(['email' => Str::uuid() . '@mp-test.local']);

        return Vendor::create(['user_id' => $user->id, 'shop_name' => 'Shop', 'slug' => 'shop-' . Str::random(8), 'status' => 'active']);
    }

    /** A URL shaped exactly like what POST /api/marketplace/upload-image returns, without hitting the real pipeline. */
    private function fakeMarketplaceImageUrl(string $subfolder): string
    {
        return url('storage/marketplace/' . $subfolder . '/' . Str::random(20) . '.jpg');
    }

    // ------------------------------------------------------------------ access

    public function test_requires_a_token_and_an_admin_role(): void
    {
        $this->getJson('/api/admin/marketplace/categories')->assertUnauthorized();

        $regular = User::factory()->create(['email' => Str::uuid() . '@mp-test.local', 'role_id' => 2, 'is_active' => true]);
        $this->withHeaders(['Authorization' => 'Bearer ' . JWTAuth::fromUser($regular)])
            ->getJson('/api/admin/marketplace/categories')->assertForbidden();
        $this->withHeaders(['Authorization' => 'Bearer ' . JWTAuth::fromUser($regular)])
            ->postJson('/api/admin/marketplace/brands', ['name' => 'X'])->assertForbidden();
    }

    // ------------------------------------------------------------------ categories

    public function test_create_category_generates_a_unique_slug_and_next_position(): void
    {
        $h = $this->admin();
        $name = 'Test cat ' . Str::random(6);

        $first = $this->withHeaders($h)->postJson('/api/admin/marketplace/categories', ['name' => $name, 'name_ar' => 'اختبار'])
            ->assertCreated()->assertJsonPath('data.name_ar', 'اختبار')->assertJsonPath('data.is_active', true);
        $second = $this->withHeaders($h)->postJson('/api/admin/marketplace/categories', ['name' => $name])->assertCreated();

        $this->assertSame(Str::slug($name), $first->json('data.slug'));
        $this->assertSame(Str::slug($name) . '-1', $second->json('data.slug'));
        $this->assertGreaterThan($first->json('data.order_position'), $second->json('data.order_position'));
    }

    public function test_create_category_validates_input(): void
    {
        $h = $this->admin();
        $parent = Category::create(['name' => 'P', 'slug' => 'p-' . Str::random(6)]);

        $this->withHeaders($h)->postJson('/api/admin/marketplace/categories', [])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->withHeaders($h)->postJson('/api/admin/marketplace/categories', ['name' => 'X', 'parent_id' => 99999999])
            ->assertStatus(422)->assertJsonValidationErrors('parent_id');
        $this->withHeaders($h)->postJson('/api/admin/marketplace/categories', ['name' => 'X', 'slug' => $parent->slug])
            ->assertStatus(422)->assertJsonValidationErrors('slug');
    }

    /** Images are never uploaded inline: the admin panel calls POST /api/marketplace/upload-image first, then sends the URL string here. */
    public function test_category_image_path_set_replace_and_clear(): void
    {
        $h = $this->admin();
        $url = $this->fakeMarketplaceImageUrl('categories');

        $created = $this->withHeaders($h)->postJson('/api/admin/marketplace/categories', [
            'name' => 'With image ' . Str::random(4), 'image_path' => $url,
        ])->assertCreated();
        $this->assertSame($url, $created->json('data.image_path'));
        $this->assertSame($url, $created->json('data.image_url'), 'a full URL is returned as-is, never re-wrapped');

        $replacement = $this->fakeMarketplaceImageUrl('categories');
        $replaced = $this->withHeaders($h)->putJson('/api/admin/marketplace/categories/' . $created->json('data.id'), ['image_path' => $replacement])
            ->assertOk();
        $this->assertSame($replacement, $replaced->json('data.image_path'));

        $removed = $this->withHeaders($h)->putJson('/api/admin/marketplace/categories/' . $created->json('data.id'), ['image_path' => null])->assertOk();
        $this->assertNull($removed->json('data.image_path'));

        // omitting the field entirely leaves it untouched
        $untouched = $this->withHeaders($h)->postJson('/api/admin/marketplace/categories', ['name' => 'No touch ' . Str::random(4), 'image_path' => $url]);
        $this->withHeaders($h)->putJson('/api/admin/marketplace/categories/' . $untouched->json('data.id'), ['description' => 'x'])
            ->assertOk()->assertJsonPath('data.image_path', $url);
    }

    public function test_category_image_path_must_come_from_the_upload_endpoint(): void
    {
        $h = $this->admin();

        $this->withHeaders($h)->postJson('/api/admin/marketplace/categories', ['name' => 'X', 'image_path' => 'https://evil.example.com/tracker.png'])
            ->assertStatus(422)->assertJsonValidationErrors('image_path');
    }

    public function test_update_category_rejects_moving_it_under_its_own_descendant(): void
    {
        $h = $this->admin();
        $root = Category::create(['name' => 'Root', 'slug' => 'root-' . Str::random(6)]);
        $child = Category::create(['name' => 'Child', 'slug' => 'child-' . Str::random(6), 'parent_id' => $root->id]);
        $grand = Category::create(['name' => 'Grand', 'slug' => 'grand-' . Str::random(6), 'parent_id' => $child->id]);

        $this->withHeaders($h)->putJson('/api/admin/marketplace/categories/' . $root->id, ['parent_id' => $grand->id])
            ->assertStatus(422)->assertJsonValidationErrors('parent_id');
        $this->withHeaders($h)->putJson('/api/admin/marketplace/categories/' . $root->id, ['parent_id' => $root->id])
            ->assertStatus(422);

        // A legal move works, and null moves it back to the top level
        $this->withHeaders($h)->putJson('/api/admin/marketplace/categories/' . $grand->id, ['parent_id' => $root->id])
            ->assertOk()->assertJsonPath('data.parent_id', $root->id);
        $this->withHeaders($h)->putJson('/api/admin/marketplace/categories/' . $grand->id, ['parent_id' => null])
            ->assertOk()->assertJsonPath('data.parent_id', null);
    }

    public function test_update_can_deactivate_and_keep_its_own_slug(): void
    {
        $h = $this->admin();
        $cat = Category::create(['name' => 'A', 'slug' => 'a-' . Str::random(6)]);

        $this->withHeaders($h)->putJson('/api/admin/marketplace/categories/' . $cat->id, ['slug' => $cat->slug, 'is_active' => false, 'name_ar' => 'ا'])
            ->assertOk()->assertJsonPath('data.is_active', false)->assertJsonPath('data.name_ar', 'ا');
    }

    public function test_delete_category_is_blocked_by_children_and_products_then_allowed(): void
    {
        $h = $this->admin();
        $parent = Category::create(['name' => 'Parent', 'slug' => 'parent-' . Str::random(6)]);
        $child = Category::create(['name' => 'Child', 'slug' => 'child-' . Str::random(6), 'parent_id' => $parent->id]);

        $this->withHeaders($h)->deleteJson('/api/admin/marketplace/categories/' . $parent->id)
            ->assertStatus(409)->assertJsonPath('code', 'HAS_CHILDREN');

        Product::create([
            'vendor_id' => $this->vendor()->id, 'category_id' => $child->id, 'reference' => 'R-' . Str::random(8),
            'slug' => 'p-' . Str::random(8), 'name' => 'P', 'price' => 10, 'status' => 'active',
        ]);
        $this->withHeaders($h)->deleteJson('/api/admin/marketplace/categories/' . $child->id)
            ->assertStatus(409)->assertJsonPath('code', 'HAS_PRODUCTS');

        $empty = Category::create(['name' => 'Empty', 'slug' => 'empty-' . Str::random(6)]);
        $this->withHeaders($h)->deleteJson('/api/admin/marketplace/categories/' . $empty->id)->assertOk();
        $this->assertSoftDeleted('marketplace_categories', ['id' => $empty->id]);
        $this->withHeaders($h)->getJson('/api/admin/marketplace/categories/' . $empty->id)->assertNotFound();
    }

    public function test_list_filters_search_and_pagination(): void
    {
        $h = $this->admin();
        $tag = Str::random(8);
        $root = Category::create(['name' => "Alpha $tag", 'slug' => "alpha-$tag"]);
        Category::create(['name' => "Beta $tag", 'slug' => "beta-$tag", 'parent_id' => $root->id, 'is_active' => false]);
        Category::create(['name' => "Gamma $tag", 'slug' => "gamma-$tag", 'parent_id' => $root->id]);

        $all = $this->withHeaders($h)->getJson('/api/admin/marketplace/categories?search=' . $tag)->assertOk();
        $this->assertCount(3, $all->json('data'));
        $this->assertArrayNotHasKey('meta', $all->json());

        $children = $this->withHeaders($h)->getJson('/api/admin/marketplace/categories?search=' . $tag . '&parent_id=' . $root->id)->json('data');
        $this->assertCount(2, $children);

        $inactive = $this->withHeaders($h)->getJson('/api/admin/marketplace/categories?search=' . $tag . '&is_active=0')->json('data');
        $this->assertSame(["beta-$tag"], collect($inactive)->pluck('slug')->all());

        $page = $this->withHeaders($h)->getJson('/api/admin/marketplace/categories?search=' . $tag . '&per_page=2')->assertOk();
        $this->assertCount(2, $page->json('data'));
        $this->assertSame(3, $page->json('meta.total'));
        $this->assertSame(2, $page->json('meta.last_page'));

        $rootRow = collect($all->json('data'))->firstWhere('slug', "alpha-$tag");
        $this->assertSame(2, $rootRow['children_count']);
    }

    public function test_reorder_categories(): void
    {
        $h = $this->admin();
        $a = Category::create(['name' => 'A', 'slug' => 'a-' . Str::random(6), 'order_position' => 0]);
        $b = Category::create(['name' => 'B', 'slug' => 'b-' . Str::random(6), 'order_position' => 1]);

        $this->withHeaders($h)->postJson('/api/admin/marketplace/categories/reorder', ['items' => [
            ['id' => $a->id, 'order_position' => 5], ['id' => $b->id, 'order_position' => 2],
        ]])->assertOk();

        $this->assertSame(5, $a->fresh()->order_position);
        $this->assertSame(2, $b->fresh()->order_position);

        $this->withHeaders($h)->postJson('/api/admin/marketplace/categories/reorder', ['items' => [['id' => 99999999, 'order_position' => 1]]])
            ->assertStatus(422);
    }

    // ------------------------------------------------------------------ brands

    public function test_brand_crud_with_logo(): void
    {
        $h = $this->admin();
        $name = 'Brand ' . Str::random(6);
        $logo = $this->fakeMarketplaceImageUrl('brands');

        $created = $this->withHeaders($h)->postJson('/api/admin/marketplace/brands', [
            'name' => $name, 'is_featured' => 1, 'logo_path' => $logo,
        ])->assertCreated()
            ->assertJsonPath('data.is_featured', true)->assertJsonPath('data.is_active', true)->assertJsonPath('data.products_count', 0);
        $id = $created->json('data.id');
        $this->assertSame($logo, $created->json('data.logo_path'));
        $this->assertSame(Str::slug($name), $created->json('data.slug'));

        $this->withHeaders($h)->putJson("/api/admin/marketplace/brands/$id", ['is_active' => false, 'is_featured' => false, 'logo_path' => null])
            ->assertOk()->assertJsonPath('data.is_active', false)->assertJsonPath('data.is_featured', false)->assertJsonPath('data.logo_path', null);

        $this->withHeaders($h)->getJson("/api/admin/marketplace/brands/$id")->assertOk()->assertJsonPath('data.name', $name);

        $this->withHeaders($h)->deleteJson("/api/admin/marketplace/brands/$id")->assertOk();
        $this->assertSoftDeleted('marketplace_brands', ['id' => $id]);
        $this->withHeaders($h)->getJson("/api/admin/marketplace/brands/$id")->assertNotFound();
    }

    public function test_brand_validation_and_list_filters(): void
    {
        $h = $this->admin();
        $tag = Str::random(8);
        Brand::create(['name' => "Feat $tag", 'slug' => "feat-$tag", 'is_featured' => true]);
        Brand::create(['name' => "Plain $tag", 'slug' => "plain-$tag", 'is_active' => false]);

        $this->withHeaders($h)->postJson('/api/admin/marketplace/brands', [])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->withHeaders($h)->postJson('/api/admin/marketplace/brands', ['name' => 'X', 'slug' => "feat-$tag"])
            ->assertStatus(422)->assertJsonValidationErrors('slug');

        $featured = $this->withHeaders($h)->getJson("/api/admin/marketplace/brands?search=$tag&is_featured=1")->json('data');
        $this->assertSame(["feat-$tag"], collect($featured)->pluck('slug')->all());
        $inactive = $this->withHeaders($h)->getJson("/api/admin/marketplace/brands?search=$tag&is_active=0")->json('data');
        $this->assertSame(["plain-$tag"], collect($inactive)->pluck('slug')->all());
    }

    // ------------------------------------------------------------------ shipping providers

    public function test_shipping_provider_crud_and_unique_code(): void
    {
        $h = $this->admin();
        $code = 'test-' . strtolower(Str::random(6));

        $created = $this->withHeaders($h)->postJson('/api/admin/marketplace/shipping-providers', [
            'name' => 'Test carrier', 'code' => strtoupper($code), 'requires_api_key' => true,
        ])->assertCreated()->assertJsonPath('data.code', $code)->assertJsonPath('data.requires_api_key', true)->assertJsonPath('data.vendor_methods_count', 0);
        $id = $created->json('data.id');

        $this->withHeaders($h)->postJson('/api/admin/marketplace/shipping-providers', ['name' => 'Dup', 'code' => $code])
            ->assertStatus(422)->assertJsonValidationErrors('code');

        $this->withHeaders($h)->putJson("/api/admin/marketplace/shipping-providers/$id", ['code' => $code, 'is_active' => false, 'name' => 'Renamed'])
            ->assertOk()->assertJsonPath('data.is_active', false)->assertJsonPath('data.name', 'Renamed');

        $ids = collect($this->withHeaders($h)->getJson('/api/admin/marketplace/shipping-providers?is_active=0')->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($id));

        $this->withHeaders($h)->deleteJson("/api/admin/marketplace/shipping-providers/$id")->assertOk();
        $this->assertSoftDeleted('marketplace_shipping_providers', ['id' => $id]);
    }

    public function test_shipping_provider_in_use_cannot_be_deleted(): void
    {
        $h = $this->admin();
        $provider = ShippingProvider::create(['name' => 'Used', 'code' => 'used-' . strtolower(Str::random(6))]);
        VendorShippingMethod::create(['vendor_id' => $this->vendor()->id, 'provider_id' => $provider->id, 'flat_rate' => 20]);

        $this->withHeaders($h)->getJson("/api/admin/marketplace/shipping-providers/{$provider->id}")
            ->assertOk()->assertJsonPath('data.vendor_methods_count', 1);
        $this->withHeaders($h)->deleteJson("/api/admin/marketplace/shipping-providers/{$provider->id}")
            ->assertStatus(409)->assertJsonPath('code', 'IN_USE');
    }
}
