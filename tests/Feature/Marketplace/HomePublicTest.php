<?php

namespace Tests\Feature\Marketplace;

use App\Models\Marketplace\Category;
use App\Models\Marketplace\Product;
use App\Models\Marketplace\Vendor;
use App\Models\MotorcycleBrand;
use App\Models\MotorcycleType;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * GET /api/marketplace/home: one call for the Marketplace home screen, product-and-category discovery
 * with a featured Shops row (per the Figma), plus the app's existing vehicle types/brands.
 * DatabaseTransactions: nothing is persisted, safe on the dev database.
 */
class HomePublicTest extends TestCase
{
    use DatabaseTransactions;

    private function vendor(array $attrs = []): Vendor
    {
        $tag = Str::random(6);

        return Vendor::create($attrs + [
            'user_id' => User::factory()->create(['email' => Str::uuid() . '@mp-test.local'])->id,
            'shop_name' => "Shop $tag", 'slug' => "shop-$tag", 'status' => 'active',
        ]);
    }

    public function test_shape_has_all_four_sections(): void
    {
        $response = $this->getJson('/api/marketplace/home')->assertOk();

        $this->assertEqualsCanonicalizing(['shops', 'categories', 'bike_types', 'bike_brands'], array_keys($response->json('data')));
        foreach (['shops', 'categories', 'bike_types', 'bike_brands'] as $key) {
            $this->assertIsArray($response->json("data.$key"), "$key must be a list");
        }
    }

    public function test_shops_are_featured_first_then_best_rated_and_inactive_never_appear(): void
    {
        $featured = $this->vendor(['is_featured' => true, 'rating_avg' => 3.0]);
        $topRated = $this->vendor(['is_featured' => false, 'rating_avg' => 4.9]);
        $pending = $this->vendor(['status' => 'pending', 'is_featured' => true]);

        $shops = collect($this->getJson('/api/marketplace/home')->json('data.shops'));
        $slugs = $shops->pluck('slug');

        $this->assertSame($featured->slug, $shops->first()['slug'], 'featured comes before a higher rating');
        $this->assertTrue($slugs->contains($topRated->slug));
        $this->assertFalse($slugs->contains($pending->slug), 'pending vendors are never shown, featured or not');

        // same card shape as GET /marketplace/vendors, including is_featured
        $this->assertEqualsCanonicalizing(
            ['id', 'slug', 'shop_name', 'shop_name_ar', 'logo_url', 'cover_image_url', 'brand_color', 'is_featured', 'rating_avg', 'reviews_count', 'products_count', 'city'],
            array_keys($shops->first())
        );
    }

    public function test_shops_row_never_leaks_private_fields(): void
    {
        $this->vendor(['is_featured' => true, 'iban' => 'SA0000000000000000000000', 'bank_name' => 'Secret Bank', 'commission_override' => 9]);

        $raw = $this->getJson('/api/marketplace/home')->getContent();
        foreach (['iban', 'bank_name', 'commission_override', 'SA0000000000000000000000', 'Secret Bank'] as $secret) {
            $this->assertStringNotContainsString($secret, $raw, "$secret must not be public");
        }
    }

    public function test_categories_are_top_level_only(): void
    {
        $root = Category::create(['name' => 'Home Root ' . Str::random(5), 'slug' => 'home-root-' . Str::random(6)]);
        $child = Category::create(['name' => 'Child', 'slug' => 'home-child-' . Str::random(6), 'parent_id' => $root->id]);
        $inactiveRoot = Category::create(['name' => 'Hidden root', 'slug' => 'home-hidden-' . Str::random(6), 'is_active' => false]);

        $vendor = $this->vendor();
        Product::create(['vendor_id' => $vendor->id, 'category_id' => $child->id, 'reference' => 'H-' . Str::random(8), 'slug' => 'h-' . Str::random(8), 'name' => 'P', 'price' => 5, 'status' => 'active']);

        $categories = collect($this->getJson('/api/marketplace/home')->json('data.categories'));
        $slugs = $categories->pluck('slug');

        $this->assertTrue($slugs->contains($root->slug));
        $this->assertFalse($slugs->contains($child->slug), 'children do not appear at the top level');
        $this->assertFalse($slugs->contains($inactiveRoot->slug));

        $row = $categories->firstWhere('slug', $root->slug);
        $this->assertSame(1, $row['children_count']);
        $this->assertSame(1, $row['products_count'], 'rolls up from the child');
        $this->assertArrayNotHasKey('children', $row, 'home never nests: use GET /marketplace/categories?parent_id= to drill down');
    }

    public function test_bike_types_and_brands_come_from_the_existing_vehicle_tables(): void
    {
        $type = MotorcycleType::create(['name' => 'Home Test Type ' . Str::random(5)]);
        // Home orders brands alphabetically within a small limit, and the shared table already has 500+ real
        // rows: a "0" prefix guarantees this one sorts first regardless of what else exists.
        $shownBrand = MotorcycleBrand::create(['name' => '0 Home Test Brand ' . Str::random(5), 'is_displayed' => true]);
        $hiddenBrand = MotorcycleBrand::create(['name' => '0 Hidden Brand ' . Str::random(5), 'is_displayed' => false]);

        $data = $this->getJson('/api/marketplace/home')->json('data');

        $this->assertTrue(collect($data['bike_types'])->pluck('name')->contains($type->name));
        $this->assertEqualsCanonicalizing(['id', 'name', 'name_ar', 'icon'], array_keys($data['bike_types'][0]));

        $brandNames = collect($data['bike_brands'])->pluck('name');
        $this->assertTrue($brandNames->contains($shownBrand->name));
        $this->assertFalse($brandNames->contains($hiddenBrand->name), 'is_displayed=false brands are hidden');
        $this->assertEqualsCanonicalizing(['id', 'name'], array_keys($data['bike_brands'][0]));
    }
}
