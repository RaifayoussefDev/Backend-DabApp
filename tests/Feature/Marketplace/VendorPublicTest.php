<?php

namespace Tests\Feature\Marketplace;

use App\Models\Marketplace\Category;
use App\Models\Marketplace\Product;
use App\Models\Marketplace\ShippingProvider;
use App\Models\Marketplace\Vendor;
use App\Models\Marketplace\VendorShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Step 2 storefront endpoints: shop list + shop page (public, no token).
 * DatabaseTransactions: nothing is persisted, safe on the dev database.
 */
class VendorPublicTest extends TestCase
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

    private function product(Vendor $vendor, Category $category, string $status = 'active'): Product
    {
        return Product::create([
            'vendor_id' => $vendor->id, 'category_id' => $category->id, 'reference' => 'T-' . Str::random(10),
            'slug' => 'p-' . Str::random(10), 'name' => 'Product', 'price' => 50, 'stock_quantity' => 3, 'status' => $status,
        ]);
    }

    private function category(string $name = 'Cat'): Category
    {
        return Category::create(['name' => $name, 'slug' => Str::slug($name) . '-' . Str::random(6)]);
    }

    public function test_list_only_returns_active_vendors_with_card_fields(): void
    {
        $active = $this->vendor(['brand_color' => '#E63946', 'rating_avg' => 4.5, 'reviews_count' => 8, 'city_id' => 1, 'country_id' => 1]);
        $pending = $this->vendor(['status' => 'pending']);
        $suspended = $this->vendor(['status' => 'suspended']);
        $rejected = $this->vendor(['status' => 'rejected']);
        $deleted = $this->vendor();
        $deleted->delete();

        $rows = collect($this->getJson('/api/marketplace/vendors?per_page=50')->assertOk()->json('data'));
        $slugs = $rows->pluck('slug');

        $this->assertTrue($slugs->contains($active->slug));
        foreach ([$pending, $suspended, $rejected, $deleted] as $hidden) {
            $this->assertFalse($slugs->contains($hidden->slug), "{$hidden->status} vendor leaked");
        }

        $card = $rows->firstWhere('slug', $active->slug);
        $this->assertEqualsCanonicalizing(
            ['id', 'slug', 'shop_name', 'shop_name_ar', 'logo_url', 'cover_image_url', 'brand_color', 'is_featured', 'rating_avg', 'reviews_count', 'products_count', 'city'],
            array_keys($card)
        );
        $this->assertSame(4.5, $card['rating_avg']);
        $this->assertSame(8, $card['reviews_count']);
        $this->assertSame('#E63946', $card['brand_color']);
        $this->assertFalse($card['is_featured']);
        $this->assertSame(1, $card['city']['id']);
    }

    public function test_featured_filter(): void
    {
        $tag = Str::random(8);
        $featured = $this->vendor(['shop_name' => "Featured $tag", 'slug' => "featured-$tag", 'is_featured' => true]);
        $normal = $this->vendor(['shop_name' => "Normal $tag", 'slug' => "normal-$tag", 'is_featured' => false]);

        $all = collect($this->getJson("/api/marketplace/vendors?search=$tag")->json('data'))->pluck('slug');
        $this->assertTrue($all->contains($featured->slug) && $all->contains($normal->slug));

        $onlyFeatured = collect($this->getJson("/api/marketplace/vendors?search=$tag&featured=1")->json('data'))->pluck('slug');
        $this->assertSame([$featured->slug], $onlyFeatured->all());
    }

    public function test_products_count_only_counts_active_products(): void
    {
        $vendor = $this->vendor();
        $cat = $this->category();
        $this->product($vendor, $cat);
        $this->product($vendor, $cat);
        $this->product($vendor, $cat, 'draft');
        $this->product($vendor, $cat, 'pending_review');

        $card = collect($this->getJson('/api/marketplace/vendors?per_page=50')->json('data'))->firstWhere('slug', $vendor->slug);

        $this->assertSame(2, $card['products_count']);
    }

    public function test_search_filters_and_sorting(): void
    {
        $tag = Str::random(8);
        $low = $this->vendor(['shop_name' => "Zeta $tag", 'slug' => "zeta-$tag", 'rating_avg' => 3.0, 'country_id' => 1, 'city_id' => 1]);
        $high = $this->vendor(['shop_name' => "Alpha $tag", 'slug' => "alpha-$tag", 'shop_name_ar' => "متجر $tag", 'rating_avg' => 4.9, 'country_id' => 1, 'city_id' => 2]);
        $this->product($low, $this->category());
        $this->product($low, $this->category());

        $bySearch = $this->getJson("/api/marketplace/vendors?search=$tag")->assertOk()->json('data');
        $this->assertCount(2, $bySearch);
        $this->assertCount(1, $this->getJson('/api/marketplace/vendors?search=' . urlencode("متجر $tag"))->json('data'), 'Arabic name is searchable');

        $this->assertSame([$high->slug, $low->slug], collect($this->getJson("/api/marketplace/vendors?search=$tag&sort=rating")->json('data'))->pluck('slug')->all());
        $this->assertSame([$high->slug, $low->slug], collect($this->getJson("/api/marketplace/vendors?search=$tag&sort=name")->json('data'))->pluck('slug')->all());
        $this->assertSame([$low->slug, $high->slug], collect($this->getJson("/api/marketplace/vendors?search=$tag&sort=products")->json('data'))->pluck('slug')->all());
        $this->assertSame([$high->slug, $low->slug], collect($this->getJson("/api/marketplace/vendors?search=$tag&sort=newest")->json('data'))->pluck('slug')->all());

        $this->assertSame([$high->slug], collect($this->getJson("/api/marketplace/vendors?search=$tag&city_id=2")->json('data'))->pluck('slug')->all());
    }

    public function test_pagination_meta_and_per_page_cap(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->vendor();
        }

        $page = $this->getJson('/api/marketplace/vendors?per_page=2')->assertOk();
        $this->assertCount(2, $page->json('data'));
        $this->assertSame(2, $page->json('meta.per_page'));
        $this->assertGreaterThanOrEqual(3, $page->json('meta.total'));

        $this->assertSame(50, $this->getJson('/api/marketplace/vendors?per_page=9999')->json('meta.per_page'));
    }

    public function test_shop_page_by_slug_and_id_with_categories_and_shipping(): void
    {
        $vendor = $this->vendor(['description' => 'We sell tyres', 'country_id' => 1, 'city_id' => 1, 'bank_name' => 'Secret Bank', 'iban' => 'SA0000000000000000000000', 'commission_override' => 9.5]);
        $tyres = $this->category('Tyres');
        $brakes = $this->category('Brakes');
        $this->product($vendor, $tyres);
        $this->product($vendor, $tyres);
        $this->product($vendor, $brakes);
        $this->product($vendor, $brakes, 'draft');

        $dhl = ShippingProvider::create(['name' => 'DHL', 'code' => 'dhl-' . strtolower(Str::random(6)), 'requires_api_key' => true]);
        $manual = ShippingProvider::create(['name' => 'Manual', 'code' => 'manual-' . strtolower(Str::random(6))]);
        $off = ShippingProvider::create(['name' => 'Off', 'code' => 'off-' . strtolower(Str::random(6)), 'is_active' => false]);
        VendorShippingMethod::create(['vendor_id' => $vendor->id, 'provider_id' => $dhl->id, 'uses_own_api' => true, 'credentials' => ['api_key' => 'SECRET-KEY'], 'flat_rate' => 35, 'estimated_days_min' => 2, 'estimated_days_max' => 4]);
        VendorShippingMethod::create(['vendor_id' => $vendor->id, 'provider_id' => $manual->id, 'flat_rate' => 15]);
        VendorShippingMethod::create(['vendor_id' => $vendor->id, 'provider_id' => $off->id, 'flat_rate' => 1]);                     // provider disabled
        VendorShippingMethod::create(['vendor_id' => $vendor->id, 'provider_id' => $dhl->id, 'flat_rate' => 99, 'is_active' => false]); // method disabled

        $bySlug = $this->getJson('/api/marketplace/vendors/' . $vendor->slug)->assertOk();
        $byId = $this->getJson('/api/marketplace/vendors/' . $vendor->id)->assertOk();
        $this->assertSame($bySlug->json('data.id'), $byId->json('data.id'));

        $data = $bySlug->json('data');
        $this->assertSame('We sell tyres', $data['description']);
        $this->assertSame(3, $data['products_count']);
        $this->assertSame('Saudi Arabia', $data['country']['name']);

        $cats = collect($data['categories'])->keyBy('id');
        $this->assertSame(2, $cats[$tyres->id]['products_count']);
        $this->assertSame(1, $cats[$brakes->id]['products_count'], 'draft products are not counted');

        // cheapest first, only enabled methods on enabled providers
        $this->assertEquals([15, 35], collect($data['shipping_methods'])->pluck('flat_rate')->all());
        $this->assertSame(['days' => [2, 4]], ['days' => [$data['shipping_methods'][1]['estimated_days_min'], $data['shipping_methods'][1]['estimated_days_max']]]);

        // nothing private leaks
        $raw = $bySlug->getContent();
        foreach (['SECRET-KEY', 'credentials', 'iban', 'Secret Bank', 'bank_name', 'commission_override', 'approved_at', 'status_reason'] as $secret) {
            $this->assertStringNotContainsString($secret, $raw, "$secret must not be public");
        }
    }

    public function test_shop_page_404_for_unknown_or_not_active_vendors(): void
    {
        foreach (['pending', 'suspended', 'rejected'] as $status) {
            $vendor = $this->vendor(['status' => $status]);
            $this->getJson('/api/marketplace/vendors/' . $vendor->slug)->assertNotFound();
            $this->getJson('/api/marketplace/vendors/' . $vendor->id)->assertNotFound();
        }

        $this->getJson('/api/marketplace/vendors/does-not-exist')->assertNotFound();
        $this->getJson('/api/marketplace/vendors/99999999')->assertNotFound();
    }
}
