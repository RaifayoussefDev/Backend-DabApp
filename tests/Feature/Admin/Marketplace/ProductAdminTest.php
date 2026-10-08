<?php

namespace Tests\Feature\Admin\Marketplace;

use App\Models\Marketplace\Brand;
use App\Models\Marketplace\Category;
use App\Models\Marketplace\Order;
use App\Models\Marketplace\OrderItem;
use App\Models\Marketplace\Product;
use App\Models\Marketplace\ProductAttribute;
use App\Models\Marketplace\Vendor;
use App\Models\MotorcycleBrand;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Step 3 admin endpoints: products (CRUD, nested images/attributes/compatibility, stats).
 * No vendor self-service yet, so the admin creates/edits every product. DatabaseTransactions: safe on the dev database.
 */
class ProductAdminTest extends TestCase
{
    use DatabaseTransactions;

    private ?User $admin = null;

    private function headers(): array
    {
        $this->admin ??= User::factory()->create(['email' => Str::uuid() . '@mp-test.local', 'role_id' => 1, 'is_active' => true]);

        return ['Authorization' => 'Bearer ' . JWTAuth::fromUser($this->admin)];
    }

    private function vendor(): Vendor
    {
        return Vendor::create([
            'user_id' => User::factory()->create(['email' => Str::uuid() . '@mp-test.local'])->id,
            'shop_name' => 'Shop ' . Str::random(6), 'slug' => 'shop-' . Str::random(6), 'status' => 'active',
        ]);
    }

    private function category(): Category
    {
        return Category::create(['name' => '0 Cat ' . Str::random(6), 'slug' => '0-cat-' . Str::random(6)]);
    }

    /** A URL shaped exactly like what POST /api/marketplace/upload-image returns, without hitting the real pipeline. */
    private function fakeImageUrl(): string
    {
        return url('storage/marketplace/products/' . Str::random(20) . '.jpg');
    }

    // ------------------------------------------------------------------ access

    public function test_requires_a_token_and_an_admin_role(): void
    {
        $this->getJson('/api/admin/marketplace/products')->assertUnauthorized();

        $regular = ['Authorization' => 'Bearer ' . JWTAuth::fromUser(User::factory()->create(['email' => Str::uuid() . '@mp-test.local', 'role_id' => 2, 'is_active' => true]))];
        $this->withHeaders($regular)->getJson('/api/admin/marketplace/products')->assertForbidden();
    }

    // ------------------------------------------------------------------ create

    public function test_create_with_images_attributes_and_compatibility(): void
    {
        $vendor = $this->vendor();
        $category = $this->category();
        $brand = Brand::create(['name' => 'Michelin', 'slug' => 'michelin-' . Str::random(6)]);
        $motoBrand = MotorcycleBrand::create(['name' => '0 Admin Moto Brand ' . Str::random(5)]);
        $cover = $this->fakeImageUrl();
        $second = $this->fakeImageUrl();

        $response = $this->withHeaders($this->headers())->postJson('/api/admin/marketplace/products', [
            'vendor_id' => $vendor->id, 'category_id' => $category->id, 'brand_id' => $brand->id,
            'reference' => '0-ADM-' . Str::random(8), 'name' => 'Admin Created Tyre', 'price' => 420,
            'stock_quantity' => 14, 'condition' => 'new', 'status' => 'active',
            'images' => [
                ['image_path' => $cover, 'is_cover' => true, 'order_position' => 0],
                ['image_path' => $second, 'is_cover' => false, 'order_position' => 1],
            ],
            'attributes' => [['label' => 'Width', 'value' => '120 mm']],
            'compatibility' => [['moto_brand_id' => $motoBrand->id, 'is_universal' => false]],
        ])->assertCreated();

        $data = $response->json('data');
        $this->assertSame('active', $data['status']);
        $this->assertNotNull($data['published_at'], 'status=active on create stamps published_at');
        $this->assertCount(2, $data['images']);
        $this->assertCount(1, $data['attributes']);
        $this->assertSame('Width', $data['attributes'][0]['label']);
        $this->assertCount(1, $data['compatibility']);
        $this->assertSame($motoBrand->name, $data['compatibility'][0]['brand']);

        // and it is visible on the storefront
        $this->getJson('/api/marketplace/products/' . $data['slug'])->assertOk();
    }

    public function test_create_validation_errors(): void
    {
        $this->withHeaders($this->headers())->postJson('/api/admin/marketplace/products', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['vendor_id', 'category_id', 'reference', 'name', 'price']);
    }

    public function test_reference_must_be_unique(): void
    {
        $vendor = $this->vendor();
        $category = $this->category();
        $ref = '0-DUP-' . Str::random(8);
        Product::create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'reference' => $ref, 'slug' => '0-dup-' . Str::random(8), 'name' => 'X', 'price' => 10]);

        $this->withHeaders($this->headers())->postJson('/api/admin/marketplace/products', [
            'vendor_id' => $vendor->id, 'category_id' => $category->id, 'reference' => $ref, 'name' => 'Y', 'price' => 20,
        ])->assertStatus(422)->assertJsonValidationErrors(['reference']);
    }

    // ------------------------------------------------------------------ update

    public function test_update_replaces_children_arrays_and_stamps_published_at_once(): void
    {
        $vendor = $this->vendor();
        $category = $this->category();
        $product = Product::create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'reference' => '0-UPD-' . Str::random(8), 'slug' => '0-upd-' . Str::random(8), 'name' => 'Draft Product', 'price' => 10, 'status' => 'draft']);
        ProductAttribute::create(['product_id' => $product->id, 'label' => 'Old', 'value' => 'Old value']);

        $firstActivate = $this->withHeaders($this->headers())->putJson("/api/admin/marketplace/products/{$product->id}", [
            'status' => 'active',
            'attributes' => [['label' => 'New', 'value' => 'New value']],
        ])->assertOk();

        $this->assertSame('New', $firstActivate->json('data.attributes.0.label'));
        $this->assertCount(1, $firstActivate->json('data.attributes'), 'old attribute rows were replaced, not appended');
        $publishedAt = $firstActivate->json('data.published_at');
        $this->assertNotNull($publishedAt);

        // staying active on a later update must not move published_at again
        $secondUpdate = $this->withHeaders($this->headers())->putJson("/api/admin/marketplace/products/{$product->id}", ['price' => 55])->assertOk();
        $this->assertSame($publishedAt, $secondUpdate->json('data.published_at'));
        $this->assertEquals(55, $secondUpdate->json('data.price'));
    }

    public function test_update_images_sets_a_cover_even_if_none_was_marked(): void
    {
        $vendor = $this->vendor();
        $category = $this->category();
        $product = Product::create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'reference' => '0-COV-' . Str::random(8), 'slug' => '0-cov-' . Str::random(8), 'name' => 'Cover Test', 'price' => 10]);

        $response = $this->withHeaders($this->headers())->putJson("/api/admin/marketplace/products/{$product->id}", [
            'images' => [['image_path' => $this->fakeImageUrl(), 'order_position' => 0]],
        ])->assertOk();

        $this->assertTrue($response->json('data.images.0.is_cover'));
    }

    public function test_create_with_variants_computes_price_range_in_stock_and_default(): void
    {
        $vendor = $this->vendor();
        $category = $this->category();
        $tag = Str::random(8);

        $response = $this->withHeaders($this->headers())->postJson('/api/admin/marketplace/products', [
            'vendor_id' => $vendor->id, 'category_id' => $category->id, 'reference' => "0-VAR-$tag",
            'name' => 'Variant Gloves', 'price' => 1, 'status' => 'active',
            'variants' => [
                ['sku' => "0-VAR-$tag-BLK-XS", 'option1_name' => 'Color', 'option1_value' => 'Black', 'color_hex' => '#000000', 'option2_name' => 'Size', 'option2_value' => 'XS', 'price' => 34.99, 'compare_at_price' => 49.99, 'stock_quantity' => 10, 'is_default' => true],
                ['sku' => "0-VAR-$tag-GRN-XS", 'option1_name' => 'Color', 'option1_value' => 'Green', 'option2_name' => 'Size', 'option2_value' => 'XS', 'price' => 120.99, 'stock_quantity' => 0],
            ],
        ])->assertCreated();

        $data = $response->json('data');
        $this->assertTrue($data['has_variants']);
        $this->assertSame(34.99, $data['price'], 'card price = cheapest variant');
        $this->assertSame(120.99, $data['price_max']);
        $this->assertSame(49.99, $data['compare_at_price'], 'compare_at_price comes from the cheapest variant');
        $this->assertTrue($data['in_stock'], 'at least one variant (Black) has stock');
        $this->assertCount(2, $data['variants']);
        $this->assertTrue(collect($data['variants'])->firstWhere('option1_value', 'Black')['is_default']);

        // visible on the storefront with the same pricing
        $public = $this->getJson('/api/marketplace/products/' . $data['slug'])->assertOk()->json('data');
        $this->assertSame(34.99, $public['price']);
        $this->assertSame(120.99, $public['price_max']);
        $this->assertCount(2, $public['variants']);
    }

    public function test_variants_sku_must_be_unique_across_products_but_not_within_the_same_product_resend(): void
    {
        $vendor = $this->vendor();
        $category = $this->category();
        $tag = Str::random(8);
        $sku = "0-SKU-$tag";

        $other = $this->withHeaders($this->headers())->postJson('/api/admin/marketplace/products', [
            'vendor_id' => $vendor->id, 'category_id' => $category->id, 'reference' => "0-OTH-$tag", 'name' => 'Other', 'price' => 10,
            'variants' => [['sku' => $sku, 'price' => 10]],
        ])->assertCreated();

        $this->withHeaders($this->headers())->postJson('/api/admin/marketplace/products', [
            'vendor_id' => $vendor->id, 'category_id' => $category->id, 'reference' => "0-DUPSKU-$tag", 'name' => 'Dup sku', 'price' => 10,
            'variants' => [['sku' => $sku, 'price' => 20]],
        ])->assertStatus(422)->assertJsonValidationErrors(['variants.0.sku']);

        // resending the SAME sku on an update of the product that already owns it is fine (full-replace, not a new conflict)
        $this->withHeaders($this->headers())->putJson("/api/admin/marketplace/products/{$other['data']['id']}", [
            'variants' => [['sku' => $sku, 'price' => 11]],
        ])->assertOk()->assertJsonPath('data.variants.0.price', 11);
    }

    public function test_removing_all_variants_reverts_to_the_products_own_price(): void
    {
        $vendor = $this->vendor();
        $category = $this->category();
        $product = Product::create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'reference' => '0-REV-' . Str::random(8), 'slug' => '0-rev-' . Str::random(8), 'name' => 'Revert Test', 'price' => 10]);
        $product->variants()->create(['sku' => '0-REV-SKU-' . Str::random(8), 'price' => 99, 'stock_quantity' => 5]);

        $response = $this->withHeaders($this->headers())->putJson("/api/admin/marketplace/products/{$product->id}", [
            'price' => 15, 'variants' => [],
        ])->assertOk();

        $this->assertFalse($response->json('data.has_variants'));
        $this->assertEquals(15, $response->json('data.price'));
        $this->assertEmpty($response->json('data.variants'));
    }

    public function test_update_404_for_unknown_product(): void
    {
        $this->withHeaders($this->headers())->putJson('/api/admin/marketplace/products/999999', ['price' => 10])->assertNotFound();
    }

    // ------------------------------------------------------------------ list, stats, delete

    public function test_index_filters_by_status_and_search(): void
    {
        $vendor = $this->vendor();
        $category = $this->category();
        $tag = Str::random(8);
        $active = Product::create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'reference' => "0-IDX-$tag-A", 'slug' => "0-idx-$tag-a", 'name' => "Idx $tag Active", 'price' => 10, 'status' => 'active']);
        $draft = Product::create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'reference' => "0-IDX-$tag-B", 'slug' => "0-idx-$tag-b", 'name' => "Idx $tag Draft", 'price' => 10, 'status' => 'draft']);

        $onlyActive = collect($this->withHeaders($this->headers())->getJson("/api/admin/marketplace/products?search=$tag&status=active")->json('data'))->pluck('slug');
        $this->assertSame([$active->slug], $onlyActive->all());

        $both = collect($this->withHeaders($this->headers())->getJson("/api/admin/marketplace/products?search=$tag")->json('data'))->pluck('slug');
        $this->assertTrue($both->contains($active->slug) && $both->contains($draft->slug));
    }

    public function test_stats_counts_per_status(): void
    {
        $vendor = $this->vendor();
        $category = $this->category();
        Product::create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'reference' => '0-ST-' . Str::random(8), 'slug' => '0-st-' . Str::random(8), 'name' => 'Stats Active', 'price' => 10, 'status' => 'active']);
        Product::create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'reference' => '0-ST-' . Str::random(8), 'slug' => '0-st-' . Str::random(8), 'name' => 'Stats Draft', 'price' => 10, 'status' => 'draft']);

        $data = $this->withHeaders($this->headers())->getJson('/api/admin/marketplace/products/stats')->assertOk()->json('data');

        $this->assertGreaterThanOrEqual(1, $data['active']);
        $this->assertGreaterThanOrEqual(1, $data['draft']);
        $this->assertSame($data['total'], $data['draft'] + $data['pending_review'] + $data['active'] + $data['inactive'] + $data['rejected']);
    }

    public function test_delete_soft_deletes_a_product_with_no_orders(): void
    {
        $vendor = $this->vendor();
        $category = $this->category();
        $product = Product::create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'reference' => '0-DEL-' . Str::random(8), 'slug' => '0-del-' . Str::random(8), 'name' => 'Delete Me', 'price' => 10]);

        $this->withHeaders($this->headers())->deleteJson("/api/admin/marketplace/products/{$product->id}")->assertOk();

        $this->assertSoftDeleted('marketplace_products', ['id' => $product->id]);
    }

    public function test_delete_is_blocked_once_the_product_has_orders(): void
    {
        $vendor = $this->vendor();
        $category = $this->category();
        $product = Product::create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'reference' => '0-ORD-' . Str::random(8), 'slug' => '0-ord-' . Str::random(8), 'name' => 'Has Orders', 'price' => 10]);
        $order = Order::create(['order_number' => '0-ORDNUM-' . Str::random(10), 'user_id' => User::factory()->create(['email' => Str::uuid() . '@mp-test.local'])->id]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'vendor_id' => $vendor->id, 'product_name' => 'Has Orders', 'product_reference' => $product->reference, 'quantity' => 1, 'unit_price' => 10, 'total_price' => 10]);

        $this->withHeaders($this->headers())->deleteJson("/api/admin/marketplace/products/{$product->id}")
            ->assertStatus(409)
            ->assertJson(['code' => 'HAS_ORDERS']);
    }
}
