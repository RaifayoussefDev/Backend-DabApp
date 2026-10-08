<?php

namespace Tests\Feature\Marketplace;

use App\Models\Marketplace\Brand;
use App\Models\Marketplace\Category;
use App\Models\Marketplace\Product;
use App\Models\Marketplace\ProductAttribute;
use App\Models\Marketplace\ProductMotorcycle;
use App\Models\Marketplace\Vendor;
use App\Models\MotorcycleBrand;
use App\Models\MotorcycleModel;
use App\Models\MotorcycleType;
use App\Models\MotorcycleYear;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Step 4: filter facets (GET /marketplace/products/filters) and the list's attributes[]/rating_min filters.
 * DatabaseTransactions: nothing is persisted, safe on the dev database.
 */
class ProductFiltersTest extends TestCase
{
    use DatabaseTransactions;

    private function vendor(): Vendor
    {
        return Vendor::create([
            'user_id' => User::factory()->create(['email' => Str::uuid() . '@mp-test.local'])->id,
            'shop_name' => '0 Shop ' . Str::random(6), 'slug' => '0-shop-' . Str::random(6), 'status' => 'active',
        ]);
    }

    private function category(): Category
    {
        return Category::create(['name' => '0 Filter Cat ' . Str::random(6), 'slug' => '0-filter-cat-' . Str::random(6)]);
    }

    private function product(Vendor $vendor, Category $category, array $attrs = []): Product
    {
        return Product::create($attrs + [
            'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'reference' => '0-F-' . Str::random(10), 'slug' => '0-f-' . Str::random(10),
            'name' => '0 Filter Product ' . Str::random(6), 'price' => 50, 'status' => 'active',
        ]);
    }

    public function test_filters_returns_price_brand_attribute_and_rating_facets_scoped_to_the_category(): void
    {
        $vendor = $this->vendor();
        $category = $this->category();
        $brand = Brand::create(['name' => '0 Facet Brand ' . Str::random(6), 'slug' => '0-facet-brand-' . Str::random(6)]);
        $outsideCategory = $this->category();

        $cheap = $this->product($vendor, $category, ['brand_id' => $brand->id, 'price' => 80, 'rating_avg' => 4.9]);
        ProductAttribute::create(['product_id' => $cheap->id, 'label' => 'Grip Style', 'value' => 'Open Ended']);

        $expensive = $this->product($vendor, $category, ['price' => 500, 'rating_avg' => 3.5]);
        ProductAttribute::create(['product_id' => $expensive->id, 'label' => 'Grip Style', 'value' => 'Close Ended']);

        $this->product($vendor, $outsideCategory, ['price' => 999999]); // out of scope, must not leak into price/other facets

        $data = $this->getJson("/api/marketplace/products/filters?category_id={$category->id}")->assertOk()->json('data');

        $this->assertEquals(80, $data['price']['min']);
        $this->assertEquals(500, $data['price']['max']);
        $this->assertTrue(collect($data['brands'])->contains(fn ($b) => $b['id'] === $brand->id && $b['count'] === 1));
        $this->assertArrayHasKey('Grip Style', $data['attributes']);
        $this->assertEqualsCanonicalizing(
            ['Open Ended', 'Close Ended'],
            collect($data['attributes']['Grip Style'])->pluck('value')->all()
        );
        // cheap=4.9 qualifies for "4 or more" but not "5"; expensive=3.5 qualifies for "3 or more" but not "4"
        $this->assertTrue(collect($data['ratings'])->contains(fn ($r) => $r['min'] === 4 && $r['count'] === 1));
        $this->assertTrue(collect($data['ratings'])->contains(fn ($r) => $r['min'] === 3 && $r['count'] === 2));
        $this->assertFalse(collect($data['ratings'])->contains(fn ($r) => $r['min'] === 5), 'no product rated 5, so the bucket is omitted');
    }

    public function test_filters_price_accounts_for_variant_range_not_the_fallback_column(): void
    {
        $vendor = $this->vendor();
        $category = $this->category();
        $product = $this->product($vendor, $category, ['price' => 1]); // fallback price, should be ignored
        $product->variants()->create(['sku' => '0-FV-' . Str::random(10), 'price' => 34.99]);
        $product->variants()->create(['sku' => '0-FV-' . Str::random(10), 'price' => 120.99]);

        $data = $this->getJson("/api/marketplace/products/filters?category_id={$category->id}")->assertOk()->json('data');

        $this->assertSame(34.99, $data['price']['min']);
        $this->assertSame(120.99, $data['price']['max']);
    }

    public function test_filters_unknown_category_is_404(): void
    {
        $this->getJson('/api/marketplace/products/filters?category_id=999999999')->assertNotFound();
    }

    public function test_filters_includes_bike_compatibility_years(): void
    {
        $vendor = $this->vendor();
        $category = $this->category();
        $motoBrand = MotorcycleBrand::create(['name' => '0 Year Brand ' . Str::random(5)]);
        $motoType = MotorcycleType::create(['name' => '0 Year Type ' . Str::random(5)]);
        $motoModel = MotorcycleModel::create(['name' => '0 Year Model ' . Str::random(5), 'brand_id' => $motoBrand->id, 'type_id' => $motoType->id]);
        $year = MotorcycleYear::create(['model_id' => $motoModel->id, 'year' => 2024]);

        $product = $this->product($vendor, $category);
        ProductMotorcycle::create(['product_id' => $product->id, 'moto_brand_id' => $motoBrand->id, 'moto_year_id' => $year->id]);

        $data = $this->getJson("/api/marketplace/products/filters?category_id={$category->id}")->assertOk()->json('data');

        $this->assertTrue(collect($data['years'])->contains(fn ($y) => $y['year'] === 2024 && $y['count'] === 1));
        $this->assertTrue(
            collect($data['moto_brands'])->contains(fn ($b) => $b['id'] === $motoBrand->id && $b['name'] === $motoBrand->name && $b['count'] === 1),
            'moto_brands is the bike manufacturer facet (Honda/BMW/KTM), separate from the parts-brand "brands" facet'
        );
    }

    public function test_list_attribute_filter_matches_only_that_value(): void
    {
        $vendor = $this->vendor();
        $category = $this->category();
        $tag = Str::random(8);

        $open = $this->product($vendor, $category, ['name' => "Attr $tag A"]);
        ProductAttribute::create(['product_id' => $open->id, 'label' => 'Finish', 'value' => 'Matte']);
        $closed = $this->product($vendor, $category, ['name' => "Attr $tag B"]);
        ProductAttribute::create(['product_id' => $closed->id, 'label' => 'Finish', 'value' => 'Glossy']);

        $slugs = collect($this->getJson('/api/marketplace/products?search=' . urlencode($tag) . '&attributes[Finish][]=Matte')->json('data'))->pluck('slug');

        $this->assertTrue($slugs->contains($open->slug));
        $this->assertFalse($slugs->contains($closed->slug));
    }

    public function test_list_rating_min_filter(): void
    {
        $vendor = $this->vendor();
        $category = $this->category();
        $tag = Str::random(8);

        $highRated = $this->product($vendor, $category, ['name' => "Rate $tag A", 'rating_avg' => 4.8]);
        $lowRated = $this->product($vendor, $category, ['name' => "Rate $tag B", 'rating_avg' => 2.1]);

        $slugs = collect($this->getJson('/api/marketplace/products?search=' . urlencode($tag) . '&rating_min=4')->json('data'))->pluck('slug');

        $this->assertTrue($slugs->contains($highRated->slug));
        $this->assertFalse($slugs->contains($lowRated->slug));
    }
}
