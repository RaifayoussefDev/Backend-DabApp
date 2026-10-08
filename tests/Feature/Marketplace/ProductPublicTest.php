<?php

namespace Tests\Feature\Marketplace;

use App\Models\Marketplace\Brand;
use App\Models\Marketplace\Category;
use App\Models\Marketplace\Product;
use App\Models\Marketplace\ProductAttribute;
use App\Models\Marketplace\ProductImage;
use App\Models\Marketplace\ProductMotorcycle;
use App\Models\Marketplace\Vendor;
use App\Models\MotorcycleBrand;
use App\Models\MotorcycleModel;
use App\Models\MotorcycleType;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Step 3 storefront: catalog list (search/filters/sort) + product detail. Public, no token.
 * DatabaseTransactions: nothing is persisted, safe on the dev database.
 */
class ProductPublicTest extends TestCase
{
    use DatabaseTransactions;

    private function vendor(array $attrs = []): Vendor
    {
        $tag = Str::random(6);

        return Vendor::create($attrs + [
            'user_id'   => User::factory()->create(['email' => Str::uuid() . '@mp-test.local'])->id,
            'shop_name' => "Shop $tag",
            'slug'      => "shop-$tag",
            'status'    => 'active',
        ]);
    }

    private function category(?Category $parent = null, string $name = 'Cat'): Category
    {
        return Category::create([
            'parent_id' => $parent?->id,
            'name'      => $name,
            'slug'      => Str::slug($name) . '-' . Str::random(6),
        ]);
    }

    private function product(Vendor $vendor, Category $category, array $attrs = []): Product
    {
        return Product::create($attrs + [
            'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'reference' => '0-T-' . Str::random(10), 'slug' => '0-p-' . Str::random(10),
            'name' => '0 Product ' . Str::random(6), 'price' => 50, 'stock_quantity' => 3, 'status' => 'active',
        ]);
    }

    public function test_list_only_returns_visible_products_with_card_fields(): void
    {
        $vendor = $this->vendor();
        $category = $this->category();
        $brand = Brand::create(['name' => 'Michelin', 'slug' => 'michelin-' . Str::random(6)]);

        $active = $this->product($vendor, $category, ['brand_id' => $brand->id, 'price' => 420, 'rating_avg' => 4.5, 'reviews_count' => 3, 'likes_count' => 12, 'is_featured' => true]);
        ProductImage::create(['product_id' => $active->id, 'image_path' => 'marketplace/products/x.jpg', 'is_cover' => true]);

        $draft = $this->product($vendor, $category, ['status' => 'draft']);
        $pendingVendor = $this->vendor(['status' => 'pending']);
        $hiddenByVendor = $this->product($pendingVendor, $category);
        $deleted = $this->product($vendor, $category);
        $deleted->delete();

        $rows = collect($this->getJson("/api/marketplace/products?vendor_id={$vendor->id}&per_page=50")->assertOk()->json('data'));
        $slugs = $rows->pluck('slug');

        $this->assertTrue($slugs->contains($active->slug));
        foreach ([$draft, $hiddenByVendor, $deleted] as $hidden) {
            $this->assertFalse($slugs->contains($hidden->slug), 'a non-visible product leaked');
        }

        $card = $rows->firstWhere('slug', $active->slug);
        $this->assertEqualsCanonicalizing(
            ['id', 'slug', 'reference', 'name', 'name_ar', 'price', 'price_max', 'compare_at_price', 'condition', 'in_stock', 'has_variants', 'cover_image_url', 'rating_avg', 'reviews_count', 'likes_count', 'is_featured', 'vendor', 'category', 'brand'],
            array_keys($card)
        );
        $this->assertEquals(420, $card['price']);
        $this->assertTrue($card['in_stock']);
        $this->assertTrue($card['is_featured']);
        $this->assertSame('Michelin', $card['brand']['name']);
        $this->assertSame($vendor->slug, $card['vendor']['slug']);
        $this->assertStringContainsString('x.jpg', $card['cover_image_url']);
    }

    public function test_category_filter_includes_sub_categories(): void
    {
        $vendor = $this->vendor();
        $parent = $this->category(null, 'Tyres');
        $child = $this->category($parent, 'Front tyres');
        $other = $this->category(null, 'Brakes');

        $inChild = $this->product($vendor, $child);
        $inOther = $this->product($vendor, $other);

        $slugs = collect($this->getJson("/api/marketplace/products?category_id={$parent->id}&vendor_id={$vendor->id}")->json('data'))->pluck('slug');

        $this->assertTrue($slugs->contains($inChild->slug), 'a product in the sub-category must be included');
        $this->assertFalse($slugs->contains($inOther->slug));
    }

    public function test_search_price_condition_and_stock_filters(): void
    {
        $vendor = $this->vendor();
        $category = $this->category();
        $tag = Str::random(8);

        $cheapUsed = $this->product($vendor, $category, ['name' => "Tyre $tag A", 'price' => 80, 'condition' => 'used', 'stock_quantity' => 0]);
        $expensiveNew = $this->product($vendor, $category, ['name' => "Tyre $tag B", 'price' => 500, 'condition' => 'new', 'stock_quantity' => 5]);

        $bySearch = collect($this->getJson("/api/marketplace/products?search=$tag")->json('data'))->pluck('slug');
        $this->assertTrue($bySearch->contains($cheapUsed->slug) && $bySearch->contains($expensiveNew->slug));

        $byPrice = collect($this->getJson("/api/marketplace/products?search=$tag&price_min=200")->json('data'))->pluck('slug');
        $this->assertSame([$expensiveNew->slug], $byPrice->all());

        $byCondition = collect($this->getJson("/api/marketplace/products?search=$tag&condition=used")->json('data'))->pluck('slug');
        $this->assertSame([$cheapUsed->slug], $byCondition->all());

        $byStock = collect($this->getJson("/api/marketplace/products?search=$tag&in_stock=1")->json('data'))->pluck('slug');
        $this->assertSame([$expensiveNew->slug], $byStock->all());
    }

    public function test_compatibility_filter_matches_explicit_and_universal_parts(): void
    {
        $vendor = $this->vendor();
        $category = $this->category();
        $brand = MotorcycleBrand::create(['name' => '0 Compat Brand ' . Str::random(5)]);
        $type = MotorcycleType::create(['name' => '0 Compat Type ' . Str::random(5)]);
        $model = MotorcycleModel::create(['name' => '0 Model ' . Str::random(5), 'brand_id' => $brand->id, 'type_id' => $type->id]);
        $otherBrand = MotorcycleBrand::create(['name' => '0 Other Brand ' . Str::random(5)]);

        $specific = $this->product($vendor, $category);
        ProductMotorcycle::create(['product_id' => $specific->id, 'moto_brand_id' => $brand->id, 'moto_model_id' => $model->id]);

        $universal = $this->product($vendor, $category);
        ProductMotorcycle::create(['product_id' => $universal->id, 'is_universal' => true]);

        $unrelated = $this->product($vendor, $category);
        ProductMotorcycle::create(['product_id' => $unrelated->id, 'moto_brand_id' => $otherBrand->id]);

        $slugs = collect($this->getJson("/api/marketplace/products?vendor_id={$vendor->id}&moto_brand_id={$brand->id}")->json('data'))->pluck('slug');

        $this->assertTrue($slugs->contains($specific->slug), 'explicit compatibility must match');
        $this->assertTrue($slugs->contains($universal->slug), 'a universal part must match any bike');
        $this->assertFalse($slugs->contains($unrelated->slug));
    }

    public function test_sorting(): void
    {
        $vendor = $this->vendor();
        $category = $this->category();
        $tag = Str::random(8);

        $cheap = $this->product($vendor, $category, ['name' => "Sort $tag A", 'price' => 50, 'rating_avg' => 3]);
        $expensive = $this->product($vendor, $category, ['name' => "Sort $tag B", 'price' => 500, 'rating_avg' => 4.8]);

        $this->assertSame([$cheap->slug, $expensive->slug], collect($this->getJson("/api/marketplace/products?search=$tag&sort=price_low")->json('data'))->pluck('slug')->all());
        $this->assertSame([$expensive->slug, $cheap->slug], collect($this->getJson("/api/marketplace/products?search=$tag&sort=price_high")->json('data'))->pluck('slug')->all());
        $this->assertSame([$expensive->slug, $cheap->slug], collect($this->getJson("/api/marketplace/products?search=$tag&sort=rating")->json('data'))->pluck('slug')->all());
    }

    public function test_pagination_meta_and_per_page_cap(): void
    {
        $vendor = $this->vendor();
        $category = $this->category();
        for ($i = 0; $i < 3; $i++) {
            $this->product($vendor, $category);
        }

        $page = $this->getJson("/api/marketplace/products?vendor_id={$vendor->id}&per_page=2")->assertOk();
        $this->assertCount(2, $page->json('data'));
        $this->assertSame(2, $page->json('meta.per_page'));
        $this->assertSame(50, $this->getJson('/api/marketplace/products?per_page=9999')->json('meta.per_page'));
    }

    public function test_unknown_category_filter_is_404(): void
    {
        $this->getJson('/api/marketplace/products?category_id=999999')->assertNotFound();
    }

    public function test_detail_by_slug_and_id_with_images_attributes_compatibility_and_vendor(): void
    {
        $vendor = $this->vendor(['description' => 'We sell tyres']);
        $category = $this->category();
        $brand = MotorcycleBrand::create(['name' => '0 Detail Brand ' . Str::random(5)]);
        $type = MotorcycleType::create(['name' => '0 Detail Type ' . Str::random(5)]);
        $model = MotorcycleModel::create(['name' => '0 Detail Model ' . Str::random(5), 'brand_id' => $brand->id, 'type_id' => $type->id]);

        $product = $this->product($vendor, $category, ['description' => 'Great tyre', 'stock_quantity' => 14]);
        ProductImage::create(['product_id' => $product->id, 'image_path' => 'marketplace/products/cover.jpg', 'is_cover' => true, 'order_position' => 0]);
        ProductImage::create(['product_id' => $product->id, 'image_path' => 'marketplace/products/second.jpg', 'is_cover' => false, 'order_position' => 1]);
        ProductAttribute::create(['product_id' => $product->id, 'label' => 'Width', 'value' => '120 mm']);
        ProductMotorcycle::create(['product_id' => $product->id, 'moto_brand_id' => $brand->id, 'moto_model_id' => $model->id]);

        $bySlug = $this->getJson('/api/marketplace/products/' . $product->slug)->assertOk();
        $byId = $this->getJson('/api/marketplace/products/' . $product->id)->assertOk();
        $this->assertSame($bySlug->json('data.id'), $byId->json('data.id'));

        $data = $bySlug->json('data');
        $this->assertSame('Great tyre', $data['description']);
        $this->assertSame(14, $data['stock_quantity']);
        $this->assertCount(2, $data['images']);
        $this->assertTrue(collect($data['images'])->firstWhere('is_cover', true) !== null);
        $this->assertSame('Width', $data['attributes'][0]['label']);
        $this->assertSame('120 mm', $data['attributes'][0]['value']);
        $this->assertSame($brand->name, $data['compatibility'][0]['brand']);
        $this->assertSame($model->name, $data['compatibility'][0]['model']);
        $this->assertSame($vendor->slug, $data['vendor']['slug']);
        $this->assertEqualsCanonicalizing(
            ['id', 'slug', 'shop_name', 'shop_name_ar', 'logo_url', 'cover_image_url', 'brand_color', 'is_featured', 'rating_avg', 'reviews_count', 'products_count', 'city'],
            array_keys($data['vendor']),
            'the product detail embeds the lightweight vendor card, not the full shop-page payload'
        );
    }

    public function test_detail_404_for_unknown_or_not_visible_products(): void
    {
        $vendor = $this->vendor();
        $category = $this->category();
        $draft = $this->product($vendor, $category, ['status' => 'draft']);

        $this->getJson('/api/marketplace/products/' . $draft->slug)->assertNotFound();
        $this->getJson('/api/marketplace/products/does-not-exist')->assertNotFound();
        $this->getJson('/api/marketplace/products/99999999')->assertNotFound();
    }
}
