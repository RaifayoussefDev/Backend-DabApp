<?php

namespace Tests\Feature\Marketplace;

use App\Models\Marketplace\Brand;
use App\Models\Marketplace\Category;
use App\Models\Marketplace\Order;
use App\Models\Marketplace\OrderItem;
use App\Models\Marketplace\Product;
use App\Models\Marketplace\Vendor;
use App\Models\MotorcycleBrand;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Vendor self-service: a vendor manages their own products from the app (admin still creates/edits any
 * product from the Admin Panel - this is the storefront-side counterpart, scoped to the caller's own shop).
 * DatabaseTransactions: safe on the dev database.
 */
class VendorProductTest extends TestCase
{
    use DatabaseTransactions;

    private function user(array $attrs = []): User
    {
        return User::factory()->create($attrs + ['email' => Str::uuid() . '@mp-test.local', 'role_id' => 2, 'is_active' => true]);
    }

    private function as(User $user): array
    {
        $token = JWTAuth::fromUser($user);
        JWTAuth::unsetToken();
        $this->app->make('tymon.jwt')->unsetToken();
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer ' . $token];
    }

    private function activeVendor(): array
    {
        $user = $this->user();
        $vendor = Vendor::create([
            'user_id' => $user->id, 'shop_name' => 'Shop ' . Str::random(6), 'slug' => 'shop-' . Str::random(6), 'status' => 'active',
        ]);

        return [$user, $vendor];
    }

    private function category(): Category
    {
        return Category::create(['name' => '0 VP Cat ' . Str::random(6), 'slug' => '0-vp-cat-' . Str::random(6)]);
    }

    private function fakeImageUrl(): string
    {
        return url('storage/marketplace/products/' . Str::random(20) . '.jpg');
    }

    // ------------------------------------------------------------------ access

    public function test_requires_auth(): void
    {
        $this->getJson('/api/marketplace/vendor/products')->assertStatus(401);
    }

    public function test_no_shop_is_404(): void
    {
        $this->withHeaders($this->as($this->user()))->getJson('/api/marketplace/vendor/products')
            ->assertNotFound()->assertJson(['code' => 'NO_VENDOR']);
    }

    public function test_pending_shop_cannot_manage_products(): void
    {
        $user = $this->user();
        Vendor::create(['user_id' => $user->id, 'shop_name' => 'Pending Shop', 'slug' => 'pending-' . Str::random(6), 'status' => 'pending']);

        $this->withHeaders($this->as($user))->getJson('/api/marketplace/vendor/products')
            ->assertForbidden()->assertJson(['code' => 'VENDOR_NOT_ACTIVE', 'status' => 'pending']);
    }

    public function test_suspended_shop_cannot_manage_products(): void
    {
        $user = $this->user();
        Vendor::create(['user_id' => $user->id, 'shop_name' => 'Susp Shop', 'slug' => 'susp-' . Str::random(6), 'status' => 'suspended', 'status_reason' => 'Fake parts']);

        $this->withHeaders($this->as($user))->getJson('/api/marketplace/vendor/products')
            ->assertForbidden()->assertJson(['code' => 'VENDOR_SUSPENDED', 'reason' => 'Fake parts']);
    }

    // ------------------------------------------------------------------ create

    public function test_create_defaults_to_draft_and_is_not_visible_on_the_storefront(): void
    {
        [$user, $vendor] = $this->activeVendor();
        $category = $this->category();

        $response = $this->withHeaders($this->as($user))->postJson('/api/marketplace/vendor/products', [
            'category_id' => $category->id, 'reference' => '0-VP-' . Str::random(8), 'name' => 'Vendor Made Tyre', 'price' => 199,
        ])->assertCreated();

        $data = $response->json('data');
        $this->assertSame('draft', $data['status']);
        $this->assertSame($vendor->id, Product::find($data['id'])->vendor_id, 'product belongs to the caller\'s own shop, not an arbitrary vendor_id');

        $this->getJson('/api/marketplace/products/' . $data['slug'])->assertNotFound();
    }

    public function test_create_without_a_reference_generates_one_from_the_name(): void
    {
        [$user, $vendor] = $this->activeVendor();
        $category = $this->category();
        $name = 'Vendor Auto Ref ' . Str::random(6);

        $data = $this->withHeaders($this->as($user))->postJson('/api/marketplace/vendor/products', [
            'category_id' => $category->id, 'name' => $name, 'price' => 50,
        ])->assertCreated()->json('data');

        $this->assertSame(strtoupper(Str::slug($name)), $data['reference']);
    }

    public function test_create_with_images_attributes_compatibility_and_variants(): void
    {
        [$user, $vendor] = $this->activeVendor();
        $category = $this->category();
        $brand = Brand::create(['name' => 'Michelin', 'slug' => 'michelin-' . Str::random(6)]);
        $motoBrand = MotorcycleBrand::create(['name' => '0 VP Moto Brand ' . Str::random(5)]);
        $cover = $this->fakeImageUrl();

        $response = $this->withHeaders($this->as($user))->postJson('/api/marketplace/vendor/products', [
            'category_id' => $category->id, 'brand_id' => $brand->id,
            'reference' => '0-VP-' . Str::random(8), 'name' => 'Vendor Tyre With Children', 'price' => 420, 'status' => 'pending_review',
            'images' => [['image_path' => $cover, 'is_cover' => true]],
            'attributes' => [['label' => 'Width', 'value' => '120 mm']],
            'compatibility' => [['moto_brand_id' => $motoBrand->id, 'is_universal' => false]],
            'variants' => [['sku' => '0-VP-SKU-' . Str::random(8), 'price' => 34.99, 'stock_quantity' => 5]],
        ])->assertCreated();

        $data = $response->json('data');
        $this->assertSame('pending_review', $data['status']);
        $this->assertCount(1, $data['images']);
        $this->assertCount(1, $data['attributes']);
        $this->assertCount(1, $data['compatibility']);
        $this->assertSame($motoBrand->name, $data['compatibility'][0]['brand']);
        $this->assertTrue($data['has_variants']);
        $this->assertSame(34.99, $data['price']);
    }

    public function test_create_rejects_a_moderation_status(): void
    {
        [$user, $vendor] = $this->activeVendor();
        $category = $this->category();

        $this->withHeaders($this->as($user))->postJson('/api/marketplace/vendor/products', [
            'category_id' => $category->id, 'reference' => '0-VP-' . Str::random(8), 'name' => 'Sneaky Active Product', 'price' => 50,
            'status' => 'active',
        ])->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_is_featured_and_vendor_id_cannot_be_set_by_the_vendor(): void
    {
        [$user, $vendor] = $this->activeVendor();
        $otherVendor = Vendor::create(['user_id' => $this->user()->id, 'shop_name' => 'Other Shop', 'slug' => 'other-' . Str::random(6), 'status' => 'active']);
        $category = $this->category();

        $data = $this->withHeaders($this->as($user))->postJson('/api/marketplace/vendor/products', [
            'category_id' => $category->id, 'reference' => '0-VP-' . Str::random(8), 'name' => 'Not Featured', 'price' => 50,
            'vendor_id' => $otherVendor->id, 'is_featured' => true,
        ])->assertCreated()->json('data');

        $this->assertFalse($data['is_featured']);
        $this->assertSame($vendor->id, Product::find($data['id'])->vendor_id);
    }

    // ------------------------------------------------------------------ update

    public function test_update_another_vendors_product_is_404(): void
    {
        [$ownerUser, $owner] = $this->activeVendor();
        [$otherUser] = $this->activeVendor();
        $category = $this->category();
        $product = Product::create([
            'vendor_id' => $owner->id, 'category_id' => $category->id, 'reference' => '0-VP-' . Str::random(8),
            'slug' => '0-vp-' . Str::random(8), 'name' => 'Owners Product', 'price' => 50, 'status' => 'draft',
        ]);

        $this->withHeaders($this->as($otherUser))->putJson("/api/marketplace/vendor/products/{$product->id}", ['price' => 1])
            ->assertNotFound();
    }

    public function test_resubmitting_a_rejected_product_clears_the_reason(): void
    {
        [$user, $vendor] = $this->activeVendor();
        $category = $this->category();
        $product = Product::create([
            'vendor_id' => $vendor->id, 'category_id' => $category->id, 'reference' => '0-VP-' . Str::random(8),
            'slug' => '0-vp-' . Str::random(8), 'name' => 'Rejected Product', 'price' => 50,
            'status' => 'rejected', 'status_reason' => 'Blurry photos',
        ]);

        $data = $this->withHeaders($this->as($user))->putJson("/api/marketplace/vendor/products/{$product->id}", ['status' => 'pending_review'])
            ->assertOk()->json('data');

        $this->assertSame('pending_review', $data['status']);
        $this->assertNull($data['status_reason']);
    }

    public function test_update_rejects_a_moderation_status(): void
    {
        [$user, $vendor] = $this->activeVendor();
        $category = $this->category();
        $product = Product::create([
            'vendor_id' => $vendor->id, 'category_id' => $category->id, 'reference' => '0-VP-' . Str::random(8),
            'slug' => '0-vp-' . Str::random(8), 'name' => 'Draft Product', 'price' => 50, 'status' => 'draft',
        ]);

        $this->withHeaders($this->as($user))->putJson("/api/marketplace/vendor/products/{$product->id}", ['status' => 'active'])
            ->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_editing_an_active_product_without_touching_status_does_not_unpublish_it(): void
    {
        [$user, $vendor] = $this->activeVendor();
        $category = $this->category();
        $product = Product::create([
            'vendor_id' => $vendor->id, 'category_id' => $category->id, 'reference' => '0-VP-' . Str::random(8),
            'slug' => '0-vp-' . Str::random(8), 'name' => 'Live Product', 'price' => 50, 'status' => 'active', 'published_at' => now(),
        ]);

        $data = $this->withHeaders($this->as($user))->putJson("/api/marketplace/vendor/products/{$product->id}", ['price' => 75])
            ->assertOk()->json('data');

        $this->assertSame('active', $data['status']);
        $this->assertEquals(75, $data['price']);
        $this->getJson('/api/marketplace/products/' . $product->slug)->assertOk();
    }

    // ------------------------------------------------------------------ list & delete

    public function test_index_returns_only_my_products_of_any_status(): void
    {
        [$user, $vendor] = $this->activeVendor();
        [, $otherVendor] = $this->activeVendor();
        $category = $this->category();

        $mine = Product::create([
            'vendor_id' => $vendor->id, 'category_id' => $category->id, 'reference' => '0-VP-' . Str::random(8),
            'slug' => '0-vp-' . Str::random(8), 'name' => 'Mine Pending', 'price' => 50, 'status' => 'pending_review',
        ]);
        Product::create([
            'vendor_id' => $otherVendor->id, 'category_id' => $category->id, 'reference' => '0-VP-' . Str::random(8),
            'slug' => '0-vp-' . Str::random(8), 'name' => 'Not Mine', 'price' => 50, 'status' => 'active',
        ]);

        $slugs = collect($this->withHeaders($this->as($user))->getJson('/api/marketplace/vendor/products')->assertOk()->json('data'))->pluck('slug');

        $this->assertTrue($slugs->contains($mine->slug));
        $this->assertCount(1, $slugs);
    }

    public function test_delete_soft_deletes_a_product_with_no_orders(): void
    {
        [$user, $vendor] = $this->activeVendor();
        $category = $this->category();
        $product = Product::create([
            'vendor_id' => $vendor->id, 'category_id' => $category->id, 'reference' => '0-VP-' . Str::random(8),
            'slug' => '0-vp-' . Str::random(8), 'name' => 'Deletable', 'price' => 50, 'status' => 'draft',
        ]);

        $this->withHeaders($this->as($user))->deleteJson("/api/marketplace/vendor/products/{$product->id}")->assertOk();

        $this->assertSoftDeleted('marketplace_products', ['id' => $product->id]);
    }

    public function test_delete_is_blocked_once_the_product_has_orders(): void
    {
        [$user, $vendor] = $this->activeVendor();
        $category = $this->category();
        $product = Product::create([
            'vendor_id' => $vendor->id, 'category_id' => $category->id, 'reference' => '0-VP-' . Str::random(8),
            'slug' => '0-vp-' . Str::random(8), 'name' => 'Sold Once', 'price' => 50, 'status' => 'active',
        ]);
        $order = Order::create([
            'user_id' => $user->id, 'order_number' => '0-VP-ORD-' . Str::random(8), 'status' => 'pending_payment',
            'subtotal' => 50, 'total' => 50, 'currency' => 'SAR',
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id, 'vendor_id' => $vendor->id,
            'product_name' => $product->name, 'product_reference' => $product->reference,
            'quantity' => 1, 'unit_price' => 50, 'total_price' => 50,
        ]);

        $this->withHeaders($this->as($user))->deleteJson("/api/marketplace/vendor/products/{$product->id}")
            ->assertStatus(409)->assertJson(['code' => 'HAS_ORDERS']);
    }
}
