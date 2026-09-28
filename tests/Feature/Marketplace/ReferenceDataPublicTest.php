<?php

namespace Tests\Feature\Marketplace;

use App\Models\Marketplace\Brand;
use App\Models\Marketplace\Category;
use App\Models\Marketplace\Product;
use App\Models\Marketplace\Vendor;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Step 1 storefront endpoints: categories + brands (public, no token).
 * DatabaseTransactions: nothing is persisted, safe on the dev database.
 */
class ReferenceDataPublicTest extends TestCase
{
    use DatabaseTransactions;

    private function category(array $attrs = []): Category
    {
        $name = $attrs['name'] ?? 'Cat ' . Str::random(6);

        return Category::create($attrs + [
            'name' => $name, 'slug' => Str::slug($name) . '-' . Str::random(4), 'is_active' => true,
        ]);
    }

    private function brand(array $attrs = []): Brand
    {
        $name = $attrs['name'] ?? 'Brand ' . Str::random(6);

        return Brand::create($attrs + [
            'name' => $name, 'slug' => Str::slug($name) . '-' . Str::random(4), 'is_active' => true,
        ]);
    }

    private function product(Category $category, ?Brand $brand = null, string $vendorStatus = 'active', string $status = 'active'): Product
    {
        $user = User::factory()->create(['email' => Str::uuid() . '@mp-test.local']);
        $vendor = Vendor::create([
            'user_id' => $user->id, 'shop_name' => 'Shop ' . Str::random(5), 'slug' => 'shop-' . Str::random(8), 'status' => $vendorStatus,
        ]);

        return Product::create([
            'vendor_id' => $vendor->id, 'category_id' => $category->id, 'brand_id' => $brand?->id,
            'reference' => 'T-' . Str::random(10), 'slug' => 'p-' . Str::random(10), 'name' => 'Product',
            'price' => 100, 'stock_quantity' => 5, 'status' => $status,
        ]);
    }

    public function test_top_level_list_returns_only_active_root_categories(): void
    {
        $active = $this->category(['name' => 'Active root']);
        $inactive = $this->category(['name' => 'Hidden root', 'is_active' => false]);
        $child = $this->category(['name' => 'Child', 'parent_id' => $active->id]);

        $response = $this->getJson('/api/marketplace/categories')->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug');
        $this->assertTrue($slugs->contains($active->slug));
        $this->assertFalse($slugs->contains($inactive->slug));
        $this->assertFalse($slugs->contains($child->slug), 'children must not appear at the top level');

        $row = collect($response->json('data'))->firstWhere('slug', $active->slug);
        $this->assertSame(1, $row['children_count']);
        $this->assertArrayNotHasKey('children', $row);
    }

    public function test_parent_id_returns_direct_children_and_tree_nests_every_level(): void
    {
        $root = $this->category();
        $child = $this->category(['parent_id' => $root->id]);
        $grandChild = $this->category(['parent_id' => $child->id]);

        $flat = $this->getJson('/api/marketplace/categories?parent_id=' . $root->id)->assertOk();
        $this->assertSame([$child->slug], collect($flat->json('data'))->pluck('slug')->all());

        $tree = $this->getJson('/api/marketplace/categories?parent_id=' . $root->id . '&tree=1')->assertOk();
        $this->assertSame($grandChild->slug, $tree->json('data.0.children.0.slug'));
    }

    public function test_unknown_or_inactive_parent_id_is_a_404(): void
    {
        $inactive = $this->category(['is_active' => false]);

        $this->getJson('/api/marketplace/categories?parent_id=99999999')->assertNotFound();
        $this->getJson('/api/marketplace/categories?parent_id=' . $inactive->id)->assertNotFound();
    }

    public function test_category_detail_by_id_and_slug_with_breadcrumb_and_children(): void
    {
        $root = $this->category(['name' => 'Parts']);
        $tyres = $this->category(['name' => 'Tyres', 'parent_id' => $root->id]);
        $front = $this->category(['name' => 'Front', 'parent_id' => $tyres->id]);

        $bySlug = $this->getJson('/api/marketplace/categories/' . $tyres->slug)->assertOk();
        $byId = $this->getJson('/api/marketplace/categories/' . $tyres->id)->assertOk();

        $this->assertSame($bySlug->json('data.id'), $byId->json('data.id'));
        $this->assertSame([$root->slug], collect($bySlug->json('data.breadcrumb'))->pluck('slug')->all());
        $this->assertSame([$front->slug], collect($bySlug->json('data.children'))->pluck('slug')->all());
    }

    public function test_inactive_category_detail_is_a_404(): void
    {
        $hidden = $this->category(['is_active' => false]);

        $this->getJson('/api/marketplace/categories/' . $hidden->slug)->assertNotFound();
        $this->getJson('/api/marketplace/categories/does-not-exist')->assertNotFound();
    }

    public function test_products_count_rolls_up_to_parents_and_ignores_hidden_products(): void
    {
        $root = $this->category();
        $child = $this->category(['parent_id' => $root->id]);

        $this->product($child);
        $this->product($child);
        $this->product($child, null, 'active', 'draft');      // draft: not counted
        $this->product($child, null, 'pending', 'active');    // vendor not approved: not counted

        $rootRow = collect($this->getJson('/api/marketplace/categories')->json('data'))->firstWhere('slug', $root->slug);
        $childRow = $this->getJson('/api/marketplace/categories/' . $child->slug)->json('data');

        $this->assertSame(2, $rootRow['products_count']);
        $this->assertSame(2, $childRow['products_count']);
    }

    public function test_brands_list_hides_inactive_and_puts_featured_first(): void
    {
        $normal = $this->brand(['name' => 'Aaa normal']);
        $featured = $this->brand(['name' => 'Zzz featured', 'is_featured' => true]);
        $inactive = $this->brand(['name' => 'Hidden brand', 'is_active' => false]);

        $slugs = collect($this->getJson('/api/marketplace/brands')->assertOk()->json('data'))->pluck('slug');

        $this->assertFalse($slugs->contains($inactive->slug));
        $this->assertTrue($slugs->search($featured->slug) < $slugs->search($normal->slug), 'featured brands come first');

        $onlyFeatured = collect($this->getJson('/api/marketplace/brands?featured=1')->json('data'))->pluck('slug');
        $this->assertTrue($onlyFeatured->contains($featured->slug));
        $this->assertFalse($onlyFeatured->contains($normal->slug));
    }

    public function test_brands_search_and_category_filter_with_counts(): void
    {
        $root = $this->category();
        $child = $this->category(['parent_id' => $root->id]);
        $other = $this->category();

        $inRoot = $this->brand(['name' => 'Findme brand']);
        $elsewhere = $this->brand(['name' => 'Other brand']);

        $this->product($child, $inRoot);
        $this->product($child, $inRoot);
        $this->product($other, $elsewhere);

        $search = $this->getJson('/api/marketplace/brands?search=Findme')->assertOk()->json('data');
        $this->assertSame([$inRoot->slug], collect($search)->pluck('slug')->all());

        // category_id includes descendants and hides brands with no product there
        $byCategory = collect($this->getJson('/api/marketplace/brands?category_id=' . $root->id)->assertOk()->json('data'));
        $this->assertSame([$inRoot->slug], $byCategory->pluck('slug')->all());
        $this->assertSame(2, $byCategory->first()['products_count']);
    }
}
