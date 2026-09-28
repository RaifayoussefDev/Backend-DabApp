<?php

namespace Tests\Feature\Admin\Marketplace;

use App\Models\Marketplace\Category;
use App\Models\Marketplace\Order;
use App\Models\Marketplace\OrderItem;
use App\Models\Marketplace\Product;
use App\Models\Marketplace\ShippingProvider;
use App\Models\Marketplace\Vendor;
use App\Models\Marketplace\VendorShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Step 2 admin endpoints: vendors (CRUD, approval workflow, stats) and their shipping methods.
 * DatabaseTransactions: nothing is persisted, safe on the dev database.
 */
class VendorAdminTest extends TestCase
{
    use DatabaseTransactions;

    private ?User $admin = null;

    private function headers(): array
    {
        $this->admin ??= User::factory()->create(['email' => Str::uuid() . '@mp-test.local', 'role_id' => 1, 'is_active' => true]);

        return ['Authorization' => 'Bearer ' . JWTAuth::fromUser($this->admin)];
    }

    private function vendor(array $attrs = []): Vendor
    {
        $tag = Str::random(6);

        return Vendor::create($attrs + [
            'user_id' => User::factory()->create(['email' => Str::uuid() . '@mp-test.local'])->id, 'shop_name' => "Shop $tag", 'slug' => "shop-$tag", 'status' => 'pending',
        ]);
    }

    /** A URL shaped exactly like what POST /api/marketplace/upload-image returns, without hitting the real pipeline. */
    private function fakeMarketplaceImageUrl(string $subfolder): string
    {
        return url('storage/marketplace/' . $subfolder . '/' . Str::random(20) . '.jpg');
    }

    private function provider(array $attrs = []): ShippingProvider
    {
        return ShippingProvider::create($attrs + ['name' => 'Carrier', 'code' => 'c-' . strtolower(Str::random(8))]);
    }

    // ------------------------------------------------------------------ access

    public function test_requires_a_token_and_an_admin_role(): void
    {
        $this->getJson('/api/admin/marketplace/vendors')->assertUnauthorized();

        $regular = ['Authorization' => 'Bearer ' . JWTAuth::fromUser(User::factory()->create(['email' => Str::uuid() . '@mp-test.local', 'role_id' => 2, 'is_active' => true]))];
        $vendor = $this->vendor();

        $this->withHeaders($regular)->getJson('/api/admin/marketplace/vendors')->assertForbidden();
        $this->withHeaders($regular)->postJson("/api/admin/marketplace/vendors/{$vendor->id}/approve")->assertForbidden();
        $this->withHeaders($regular)->getJson("/api/admin/marketplace/vendors/{$vendor->id}/shipping-methods")->assertForbidden();
        $this->assertSame('pending', $vendor->fresh()->status);
    }

    // ------------------------------------------------------------------ create

    public function test_create_vendor_with_branding_defaults_to_pending(): void
    {
        $owner = User::factory()->create(['email' => Str::uuid() . '@mp-test.local']);
        $logo = $this->fakeMarketplaceImageUrl('vendors');
        $cover = $this->fakeMarketplaceImageUrl('vendors');

        $response = $this->withHeaders($this->headers())->postJson('/api/admin/marketplace/vendors', [
            'user_id' => $owner->id, 'shop_name' => 'Moto Parts ' . ($tag = Str::random(5)), 'shop_name_ar' => 'قطع الغيار',
            'brand_color' => '#E63946', 'country_id' => 1, 'city_id' => 1, 'commission_override' => 12.5,
            'bank_name' => 'Al Rajhi', 'iban' => 'SA0380000000608010167519',
            'logo_path' => $logo, 'cover_image_path' => $cover,
        ])->assertCreated();

        $data = $response->json('data');
        $this->assertSame('pending', $data['status']);
        $this->assertNull($data['approved_at']);
        $this->assertSame('moto-parts-' . strtolower($tag), $data['slug']);
        $this->assertSame('#E63946', $data['brand_color']);
        $this->assertEquals(12.5, $data['commission_override']);
        $this->assertSame($owner->id, $data['owner']['id']);
        $this->assertSame('SA0380000000608010167519', $data['iban'], 'detail shows the IBAN');
        $this->assertSame($logo, $data['logo_path']);
        $this->assertSame($cover, $data['cover_image_path']);

        // IBAN is stored encrypted
        $this->assertNotSame('SA0380000000608010167519', DB::table('marketplace_vendors')->where('id', $data['id'])->value('iban'));
    }

    public function test_create_vendor_rejects_a_logo_not_from_the_upload_endpoint(): void
    {
        $owner = User::factory()->create(['email' => Str::uuid() . '@mp-test.local']);

        $this->withHeaders($this->headers())->postJson('/api/admin/marketplace/vendors', [
            'user_id' => $owner->id, 'shop_name' => 'X', 'logo_path' => 'https://evil.example.com/tracker.png',
        ])->assertStatus(422)->assertJsonValidationErrors('logo_path');
    }

    public function test_create_as_active_stamps_approval(): void
    {
        $response = $this->withHeaders($this->headers())->postJson('/api/admin/marketplace/vendors', [
            'user_id' => User::factory()->create(['email' => Str::uuid() . '@mp-test.local'])->id, 'shop_name' => 'Live shop', 'status' => 'active',
        ])->assertCreated()->assertJsonPath('data.status', 'active');

        $this->assertNotNull($response->json('data.approved_at'));
        $this->assertSame($this->admin->id, Vendor::find($response->json('data.id'))->approved_by);
    }

    public function test_create_validation(): void
    {
        $h = $this->headers();
        $existing = $this->vendor();
        $user = User::factory()->create(['email' => Str::uuid() . '@mp-test.local']);

        $this->withHeaders($h)->postJson('/api/admin/marketplace/vendors', [])->assertStatus(422)->assertJsonValidationErrors(['user_id', 'shop_name']);
        $this->withHeaders($h)->postJson('/api/admin/marketplace/vendors', ['user_id' => 99999999, 'shop_name' => 'X'])->assertStatus(422)->assertJsonValidationErrors('user_id');
        $this->withHeaders($h)->postJson('/api/admin/marketplace/vendors', ['user_id' => $existing->user_id, 'shop_name' => 'X'])
            ->assertStatus(422)->assertJsonValidationErrors('user_id');
        $this->withHeaders($h)->postJson('/api/admin/marketplace/vendors', ['user_id' => $user->id, 'shop_name' => 'X', 'brand_color' => 'red'])
            ->assertStatus(422)->assertJsonValidationErrors('brand_color');
        $this->withHeaders($h)->postJson('/api/admin/marketplace/vendors', ['user_id' => $user->id, 'shop_name' => 'X', 'commission_override' => 101])
            ->assertStatus(422)->assertJsonValidationErrors('commission_override');
        $this->withHeaders($h)->postJson('/api/admin/marketplace/vendors', ['user_id' => $user->id, 'shop_name' => 'X', 'status' => 'banana'])
            ->assertStatus(422)->assertJsonValidationErrors('status');
        $this->withHeaders($h)->postJson('/api/admin/marketplace/vendors', ['user_id' => $user->id, 'shop_name' => 'X', 'slug' => $existing->slug])
            ->assertStatus(422)->assertJsonValidationErrors('slug');

        // a city must belong to the chosen country (Riyadh is Saudi Arabia, not the UAE)
        $this->withHeaders($h)->postJson('/api/admin/marketplace/vendors', ['user_id' => $user->id, 'shop_name' => 'X', 'country_id' => 2, 'city_id' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('city_id');
    }

    // ------------------------------------------------------------------ read

    public function test_list_filters_search_and_iban_is_never_in_the_list(): void
    {
        $h = $this->headers();
        $tag = Str::random(8);
        $owner = User::factory()->create(['email' => Str::uuid() . '@mp-test.local', 'email' => "owner-$tag@example.test"]);
        $a = $this->vendor(['user_id' => $owner->id, 'shop_name' => "Alpha $tag", 'slug' => "alpha-$tag", 'status' => 'active', 'iban' => 'SA111', 'bank_name' => 'B', 'commission_override' => 7, 'country_id' => 1, 'city_id' => 1]);
        $this->vendor(['shop_name' => "Beta $tag", 'slug' => "beta-$tag", 'status' => 'suspended', 'country_id' => 1, 'city_id' => 2]);

        $all = $this->withHeaders($h)->getJson("/api/admin/marketplace/vendors?search=$tag")->assertOk();
        $this->assertCount(2, $all->json('data'));
        $this->assertArrayNotHasKey('meta', $all->json());
        $this->assertStringNotContainsString('SA111', $all->getContent());
        $row = collect($all->json('data'))->firstWhere('slug', "alpha-$tag");
        $this->assertArrayNotHasKey('iban', $row);
        $this->assertSame('B', $row['bank_name']);
        $this->assertEquals(7, $row['commission_override']);

        $this->assertSame([$a->slug], collect($this->withHeaders($h)->getJson('/api/admin/marketplace/vendors?search=' . urlencode("owner-$tag@example.test"))->json('data'))->pluck('slug')->all(), 'search by owner email');
        $this->assertSame(["beta-$tag"], collect($this->withHeaders($h)->getJson("/api/admin/marketplace/vendors?search=$tag&status=suspended")->json('data'))->pluck('slug')->all());
        $this->assertSame(["beta-$tag"], collect($this->withHeaders($h)->getJson("/api/admin/marketplace/vendors?search=$tag&city_id=2")->json('data'))->pluck('slug')->all());

        $page = $this->withHeaders($h)->getJson("/api/admin/marketplace/vendors?search=$tag&per_page=1&sort=name")->assertOk();
        $this->assertSame(["alpha-$tag"], collect($page->json('data'))->pluck('slug')->all());
        $this->assertSame(2, $page->json('meta.total'));
        $this->assertSame(2, $page->json('meta.last_page'));
    }

    public function test_detail_and_stats(): void
    {
        $h = $this->headers();
        $before = $this->withHeaders($h)->getJson('/api/admin/marketplace/vendors/stats')->assertOk()->json('data');

        $vendor = $this->vendor(['iban' => 'SA999', 'status' => 'active']);
        $this->vendor(['status' => 'pending']);
        $this->vendor(['status' => 'rejected']);

        $after = $this->withHeaders($h)->getJson('/api/admin/marketplace/vendors/stats')->json('data');
        $this->assertSame($before['total'] + 3, $after['total']);
        $this->assertSame($before['active'] + 1, $after['active']);
        $this->assertSame($before['pending'] + 1, $after['pending']);
        $this->assertSame($before['rejected'] + 1, $after['rejected']);
        $this->assertSame($before['suspended'], $after['suspended']);

        $this->withHeaders($h)->getJson("/api/admin/marketplace/vendors/{$vendor->id}")->assertOk()
            ->assertJsonPath('data.iban', 'SA999')->assertJsonPath('data.products_count', 0);
        $this->withHeaders($h)->getJson('/api/admin/marketplace/vendors/99999999')->assertNotFound();
    }

    // ------------------------------------------------------------------ update

    public function test_update_partial_fields_images_and_commission(): void
    {
        $h = $this->headers();
        $vendor = $this->vendor(['commission_override' => 10, 'brand_color' => '#000000']);

        $this->withHeaders($h)->putJson("/api/admin/marketplace/vendors/{$vendor->id}", ['shop_name' => 'Renamed', 'brand_color' => '#1D3557'])
            ->assertOk()->assertJsonPath('data.shop_name', 'Renamed')->assertJsonPath('data.brand_color', '#1D3557');
        $this->assertEquals(10, $vendor->fresh()->commission_override, 'untouched fields stay');

        // logo_path / cover_image_path are strings from POST /api/marketplace/upload-image, set then replaced then cleared
        $logo = $this->fakeMarketplaceImageUrl('vendors');
        $cover = $this->fakeMarketplaceImageUrl('vendors');
        $first = $this->withHeaders($h)->putJson("/api/admin/marketplace/vendors/{$vendor->id}", ['logo_path' => $logo, 'cover_image_path' => $cover])->assertOk();
        $this->assertSame($logo, $first->json('data.logo_path'));
        $this->assertSame($cover, $first->json('data.cover_image_path'));

        $newLogo = $this->fakeMarketplaceImageUrl('vendors');
        $second = $this->withHeaders($h)->putJson("/api/admin/marketplace/vendors/{$vendor->id}", ['logo_path' => $newLogo])->assertOk();
        $this->assertSame($newLogo, $second->json('data.logo_path'));
        $this->assertSame($cover, $second->json('data.cover_image_path'), 'cover was not sent, so it stays');

        $removed = $this->withHeaders($h)->putJson("/api/admin/marketplace/vendors/{$vendor->id}", ['cover_image_path' => null, 'commission_override' => null])->assertOk();
        $this->assertNull($removed->json('data.cover_image_path'));
        $this->assertNull($removed->json('data.commission_override'), 'empty commission clears the override');
    }

    /** is_featured: the Marketplace home Shops row and the Featured badge on All Shops. */
    public function test_is_featured_toggle_and_filter(): void
    {
        $h = $this->headers();
        $vendor = $this->vendor();

        $this->withHeaders($h)->getJson("/api/admin/marketplace/vendors/{$vendor->id}")->assertJsonPath('data.is_featured', false);

        $this->withHeaders($h)->putJson("/api/admin/marketplace/vendors/{$vendor->id}", ['is_featured' => true])
            ->assertOk()->assertJsonPath('data.is_featured', true);
        $this->assertTrue((bool) $vendor->fresh()->is_featured);

        $ids = collect($this->withHeaders($h)->getJson('/api/admin/marketplace/vendors?is_featured=1')->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($vendor->id));

        $this->withHeaders($h)->putJson("/api/admin/marketplace/vendors/{$vendor->id}", ['is_featured' => false])
            ->assertOk()->assertJsonPath('data.is_featured', false);
    }

    public function test_create_vendor_can_be_featured_from_the_start(): void
    {
        $owner = User::factory()->create(['email' => Str::uuid() . '@mp-test.local']);

        $this->withHeaders($this->headers())->postJson('/api/admin/marketplace/vendors', [
            'user_id' => $owner->id, 'shop_name' => 'X', 'is_featured' => true,
        ])->assertCreated()->assertJsonPath('data.is_featured', true);
    }

    public function test_update_cannot_change_status_and_validates_owner(): void
    {
        $h = $this->headers();
        $vendor = $this->vendor();
        $other = $this->vendor();

        $this->withHeaders($h)->putJson("/api/admin/marketplace/vendors/{$vendor->id}", ['status' => 'active', 'shop_name' => 'Still pending'])->assertOk();
        $this->assertSame('pending', $vendor->fresh()->status, 'status only changes through approve / suspend / reject');

        $this->withHeaders($h)->putJson("/api/admin/marketplace/vendors/{$vendor->id}", ['user_id' => $other->user_id])
            ->assertStatus(422)->assertJsonValidationErrors('user_id');
        $this->withHeaders($h)->putJson("/api/admin/marketplace/vendors/{$vendor->id}", ['user_id' => $vendor->user_id, 'slug' => $vendor->slug])->assertOk();
        $this->withHeaders($h)->putJson('/api/admin/marketplace/vendors/99999999', ['shop_name' => 'X'])->assertNotFound();
    }

    // ------------------------------------------------------------------ workflow

    public function test_approve_suspend_reject_workflow(): void
    {
        $h = $this->headers();
        $vendor = $this->vendor();
        $base = "/api/admin/marketplace/vendors/{$vendor->id}";

        // pending: cannot be suspended, needs a reason to be rejected
        $this->withHeaders($h)->postJson("$base/suspend", ['reason' => 'x'])->assertStatus(409)->assertJsonPath('code', 'INVALID_STATE');
        $this->withHeaders($h)->postJson("$base/reject", [])->assertStatus(422)->assertJsonValidationErrors('reason');

        $this->withHeaders($h)->postJson("$base/reject", ['reason' => 'Missing registration'])
            ->assertOk()->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.status_reason', 'Missing registration');
        $this->withHeaders($h)->postJson("$base/reject", ['reason' => 'again'])->assertStatus(409);

        // rejected can be approved after fixing the file; approval clears the reason
        $approved = $this->withHeaders($h)->postJson("$base/approve")->assertOk()->assertJsonPath('data.status', 'active')->assertJsonPath('data.status_reason', null);
        $this->assertNotNull($approved->json('data.approved_at'));
        $this->assertSame($this->admin->id, $vendor->fresh()->approved_by);
        $this->withHeaders($h)->postJson("$base/approve")->assertStatus(409)->assertJsonPath('code', 'INVALID_STATE');

        // active can be suspended (reason required), and reactivated
        $this->withHeaders($h)->postJson("$base/suspend", [])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->withHeaders($h)->postJson("$base/suspend", ['reason' => 'Fake parts'])->assertOk()->assertJsonPath('data.status', 'suspended');
        $this->withHeaders($h)->postJson("$base/approve")->assertOk()->assertJsonPath('data.status', 'active');

        $this->withHeaders($h)->postJson('/api/admin/marketplace/vendors/99999999/approve')->assertNotFound();
    }

    public function test_suspending_hides_the_shop_and_its_products_from_the_storefront(): void
    {
        $h = $this->headers();
        $vendor = $this->vendor(['status' => 'active']);
        $category = Category::create(['name' => 'C', 'slug' => 'c-' . Str::random(6)]);
        $product = Product::create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'reference' => 'R-' . Str::random(8), 'slug' => 'p-' . Str::random(8), 'name' => 'P', 'price' => 5, 'status' => 'active']);

        $this->getJson("/api/marketplace/vendors/{$vendor->slug}")->assertOk();
        $this->assertTrue(Product::visible()->whereKey($product->id)->exists());

        $this->withHeaders($h)->postJson("/api/admin/marketplace/vendors/{$vendor->id}/suspend", ['reason' => 'test'])->assertOk();

        $this->getJson("/api/marketplace/vendors/{$vendor->slug}")->assertNotFound();
        $this->assertFalse(Product::visible()->whereKey($product->id)->exists());
    }

    // ------------------------------------------------------------------ delete

    public function test_delete_vendor_unless_it_has_orders(): void
    {
        $h = $this->headers();
        $empty = $this->vendor();
        $this->withHeaders($h)->deleteJson("/api/admin/marketplace/vendors/{$empty->id}")->assertOk();
        $this->assertSoftDeleted('marketplace_vendors', ['id' => $empty->id]);
        $this->withHeaders($h)->getJson("/api/admin/marketplace/vendors/{$empty->id}")->assertNotFound();

        $seller = $this->vendor(['status' => 'active']);
        $category = Category::create(['name' => 'C', 'slug' => 'c-' . Str::random(6)]);
        $product = Product::create(['vendor_id' => $seller->id, 'category_id' => $category->id, 'reference' => 'R-' . Str::random(8), 'slug' => 'p-' . Str::random(8), 'name' => 'P', 'price' => 5, 'status' => 'active']);
        $order = Order::create(['order_number' => 'T-' . Str::random(10), 'user_id' => User::factory()->create(['email' => Str::uuid() . '@mp-test.local'])->id]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'vendor_id' => $seller->id, 'product_name' => 'P', 'product_reference' => $product->reference, 'quantity' => 1, 'unit_price' => 5, 'total_price' => 5]);

        $this->withHeaders($h)->deleteJson("/api/admin/marketplace/vendors/{$seller->id}")->assertStatus(409)->assertJsonPath('code', 'HAS_ORDERS');
        $this->assertNull($seller->fresh()->deleted_at);
    }

    // ------------------------------------------------------------------ shipping methods

    public function test_shipping_method_crud_never_exposes_credentials(): void
    {
        $h = $this->headers();
        $vendor = $this->vendor();
        $dhl = $this->provider(['name' => 'DHL', 'requires_api_key' => true]);
        $url = "/api/admin/marketplace/vendors/{$vendor->id}/shipping-methods";

        $created = $this->withHeaders($h)->postJson($url, [
            'provider_id' => $dhl->id, 'flat_rate' => 35, 'uses_own_api' => true,
            'credentials' => ['api_key' => 'TOP-SECRET', 'account_number' => '123'], 'estimated_days_min' => 2, 'estimated_days_max' => 4,
        ])->assertCreated()->assertJsonPath('data.has_credentials', true)->assertJsonPath('data.provider.name', 'DHL')->assertJsonPath('data.is_active', true);
        $id = $created->json('data.id');

        $this->assertStringNotContainsString('TOP-SECRET', $created->getContent());
        $this->assertStringNotContainsString('TOP-SECRET', DB::table('marketplace_vendor_shipping_methods')->where('id', $id)->value('credentials'), 'stored encrypted');
        $this->assertSame('TOP-SECRET', VendorShippingMethod::find($id)->credentials['api_key'], 'but readable by the server');

        foreach ([$url, "$url/$id"] as $readUrl) {
            $this->assertStringNotContainsString('TOP-SECRET', $this->withHeaders($h)->getJson($readUrl)->assertOk()->getContent());
        }

        $this->withHeaders($h)->putJson("$url/$id", ['flat_rate' => 40, 'is_active' => false])
            ->assertOk()->assertJsonPath('data.flat_rate', '40.00')->assertJsonPath('data.is_active', false)->assertJsonPath('data.has_credentials', true);

        $this->withHeaders($h)->deleteJson("$url/$id")->assertOk();
        $this->assertSoftDeleted('marketplace_vendor_shipping_methods', ['id' => $id]);
        $this->withHeaders($h)->getJson("$url/$id")->assertNotFound();
    }

    public function test_shipping_method_validation(): void
    {
        $h = $this->headers();
        $vendor = $this->vendor();
        $other = $this->vendor();
        $provider = $this->provider();
        $inactive = $this->provider(['is_active' => false]);
        $url = "/api/admin/marketplace/vendors/{$vendor->id}/shipping-methods";

        $this->withHeaders($h)->postJson($url, [])->assertStatus(422)->assertJsonValidationErrors(['provider_id', 'flat_rate']);
        $this->withHeaders($h)->postJson($url, ['provider_id' => $inactive->id, 'flat_rate' => 1])->assertStatus(422)->assertJsonValidationErrors('provider_id');
        $this->withHeaders($h)->postJson($url, ['provider_id' => $provider->id, 'flat_rate' => -5])->assertStatus(422)->assertJsonValidationErrors('flat_rate');
        $this->withHeaders($h)->postJson($url, ['provider_id' => $provider->id, 'flat_rate' => 5, 'uses_own_api' => true])->assertStatus(422)->assertJsonValidationErrors('credentials');
        $this->withHeaders($h)->postJson($url, ['provider_id' => $provider->id, 'flat_rate' => 5, 'estimated_days_min' => 5, 'estimated_days_max' => 2])
            ->assertStatus(422)->assertJsonValidationErrors('estimated_days_max');

        $this->withHeaders($h)->postJson($url, ['provider_id' => $provider->id, 'flat_rate' => 5])->assertCreated();
        $this->withHeaders($h)->postJson($url, ['provider_id' => $provider->id, 'flat_rate' => 6])->assertStatus(422)->assertJsonValidationErrors('provider_id');
        // the same provider is fine for another vendor
        $this->withHeaders($h)->postJson("/api/admin/marketplace/vendors/{$other->id}/shipping-methods", ['provider_id' => $provider->id, 'flat_rate' => 6])->assertCreated();

        $this->withHeaders($h)->getJson('/api/admin/marketplace/vendors/99999999/shipping-methods')->assertNotFound();
        $this->withHeaders($h)->postJson('/api/admin/marketplace/vendors/99999999/shipping-methods', ['provider_id' => $provider->id, 'flat_rate' => 1])->assertNotFound();
    }

    public function test_shipping_method_credentials_lifecycle(): void
    {
        $h = $this->headers();
        $vendor = $this->vendor();
        $provider = $this->provider();
        $url = "/api/admin/marketplace/vendors/{$vendor->id}/shipping-methods";

        $id = $this->withHeaders($h)->postJson($url, ['provider_id' => $provider->id, 'flat_rate' => 20, 'uses_own_api' => true, 'credentials' => ['api_key' => 'K1']])->json('data.id');

        // replace the keys
        $this->withHeaders($h)->putJson("$url/$id", ['credentials' => ['api_key' => 'K2']])->assertOk()->assertJsonPath('data.has_credentials', true);
        $this->assertSame('K2', VendorShippingMethod::find($id)->credentials['api_key']);

        // cannot wipe the keys while it still uses its own API
        $this->withHeaders($h)->putJson("$url/$id", ['clear_credentials' => true])->assertStatus(422)->assertJsonValidationErrors('credentials');

        // turning the own API off wipes the keys
        $this->withHeaders($h)->putJson("$url/$id", ['uses_own_api' => false])->assertOk()
            ->assertJsonPath('data.uses_own_api', false)->assertJsonPath('data.has_credentials', false);
        $this->assertNull(DB::table('marketplace_vendor_shipping_methods')->where('id', $id)->value('credentials'));

        // and turning it back on without keys is refused
        $this->withHeaders($h)->putJson("$url/$id", ['uses_own_api' => true])->assertStatus(422)->assertJsonValidationErrors('credentials');
        $this->withHeaders($h)->putJson("$url/$id", ['uses_own_api' => true, 'credentials' => ['api_key' => 'K3']])->assertOk()->assertJsonPath('data.has_credentials', true);
    }

    public function test_shipping_method_of_another_vendor_is_not_reachable(): void
    {
        $h = $this->headers();
        $mine = $this->vendor();
        $theirs = $this->vendor();
        $method = VendorShippingMethod::create(['vendor_id' => $theirs->id, 'provider_id' => $this->provider()->id, 'flat_rate' => 9]);

        $url = "/api/admin/marketplace/vendors/{$mine->id}/shipping-methods/{$method->id}";
        $this->withHeaders($h)->getJson($url)->assertNotFound();
        $this->withHeaders($h)->putJson($url, ['flat_rate' => 1])->assertNotFound();
        $this->withHeaders($h)->deleteJson($url)->assertNotFound();
        $this->assertEquals(9, $method->fresh()->flat_rate);
    }
}
