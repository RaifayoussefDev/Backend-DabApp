<?php

namespace Tests\Feature\Marketplace;

use App\Models\Marketplace\Vendor;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Step 2b: a logged-in user applies to become a vendor, then an admin approves.
 * DatabaseTransactions: nothing is persisted, safe on the dev database.
 */
class VendorAccountTest extends TestCase
{
    use DatabaseTransactions;

    private function user(array $attrs = []): User
    {
        return User::factory()->create($attrs + ['email' => Str::uuid() . '@mp-test.local', 'role_id' => 2, 'is_active' => true]);
    }

    private function as(User $user): array
    {
        $token = JWTAuth::fromUser($user);

        // Within one test the JWT guard and the JWT singleton keep the first user / token they saw. A real
        // request is always fresh, so reset both to be able to act as several users in the same test.
        JWTAuth::unsetToken();
        $this->app->make('tymon.jwt')->unsetToken();
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer ' . $token];
    }

    private function payload(array $extra = []): array
    {
        return $extra + ['shop_name' => 'My Shop ' . Str::random(5), 'phone' => '+966500000000', 'country_id' => 1, 'city_id' => 1];
    }

    /** A URL shaped exactly like what POST /api/marketplace/upload-image returns, without hitting the real pipeline. */
    private function fakeMarketplaceImageUrl(): string
    {
        return url('storage/marketplace/vendors/' . Str::random(20) . '.jpg');
    }

    // ------------------------------------------------------------------ guest

    public function test_guest_gets_the_structured_401_to_open_the_login_modal(): void
    {
        foreach ([['postJson', '/api/marketplace/vendor/apply'], ['getJson', '/api/marketplace/vendor/me'], ['putJson', '/api/marketplace/vendor/me']] as [$method, $url]) {
            $this->{$method}($url, $this->payload())->assertUnauthorized()->assertJson(['requires_auth' => true, 'action' => 'login']);
        }
    }

    // ------------------------------------------------------------------ apply

    public function test_apply_creates_a_pending_shop_with_branding_and_ignores_privileged_fields(): void
    {
        $user = $this->user();
        $logo = $this->fakeMarketplaceImageUrl();
        $cover = $this->fakeMarketplaceImageUrl();

        $response = $this->withHeaders($this->as($user))->postJson('/api/marketplace/vendor/apply', $this->payload([
            'shop_name' => 'Fast Parts ' . ($tag = Str::random(4)), 'shop_name_ar' => 'قطع سريعة', 'brand_color' => '#E63946',
            'bank_name' => 'Al Rajhi', 'iban' => 'SA0380000000608010167519',
            // things a user must never be able to set
            'status' => 'active', 'commission_override' => 0, 'slug' => 'hacked', 'user_id' => 1, 'approved_at' => now()->toDateTimeString(),
            'logo_path' => $logo, 'cover_image_path' => $cover,
        ]))->assertCreated();

        $data = $response->json('data');
        $this->assertSame('pending', $data['status']);
        $this->assertFalse($data['can_sell']);
        $this->assertSame('fast-parts-' . strtolower($tag), $data['slug']);
        $this->assertNull($data['approved_at']);
        $this->assertSame('Riyadh', $data['city']['name']);
        $this->assertSame($logo, $data['logo_url']);

        $vendor = Vendor::find($data['id']);
        $this->assertSame($user->id, $vendor->user_id);
        $this->assertNull($vendor->commission_override);
        $this->assertNull($vendor->approved_by);
        $this->assertSame('#E63946', $vendor->brand_color);
        $this->assertSame($logo, $vendor->logo_path);
        $this->assertSame($cover, $vendor->cover_image_path);

        // pending shops are invisible in the storefront
        $this->getJson('/api/marketplace/vendors/' . $vendor->slug)->assertNotFound();
        $this->assertStringNotContainsString('commission', $response->getContent());
    }

    public function test_apply_rejects_a_logo_not_from_the_upload_endpoint(): void
    {
        $this->withHeaders($this->as($this->user()))->postJson('/api/marketplace/vendor/apply', $this->payload([
            'logo_path' => 'https://evil.example.com/tracker.png',
        ]))->assertStatus(422)->assertJsonValidationErrors('logo_path');
    }

    public function test_apply_validation(): void
    {
        $h = $this->as($this->user());

        $this->withHeaders($h)->postJson('/api/marketplace/vendor/apply', [])->assertStatus(422)
            ->assertJsonValidationErrors(['shop_name', 'phone', 'country_id', 'city_id']);
        $this->withHeaders($h)->postJson('/api/marketplace/vendor/apply', $this->payload(['brand_color' => 'red']))->assertStatus(422)->assertJsonValidationErrors('brand_color');
        $this->withHeaders($h)->postJson('/api/marketplace/vendor/apply', $this->payload(['email' => 'nope']))->assertStatus(422)->assertJsonValidationErrors('email');
        // Riyadh does not belong to the UAE
        $this->withHeaders($h)->postJson('/api/marketplace/vendor/apply', $this->payload(['country_id' => 2, 'city_id' => 1]))->assertStatus(422)->assertJsonValidationErrors('city_id');
        $this->assertSame(0, Vendor::where('shop_name', 'like', 'My Shop%')->where('user_id', $this->user()->id)->count());
    }

    public function test_two_shops_with_the_same_name_get_distinct_slugs(): void
    {
        $name = 'Twin Shop ' . Str::random(5);

        $a = $this->withHeaders($this->as($this->user()))->postJson('/api/marketplace/vendor/apply', $this->payload(['shop_name' => $name]))->assertCreated();
        $b = $this->withHeaders($this->as($this->user()))->postJson('/api/marketplace/vendor/apply', $this->payload(['shop_name' => $name]))->assertCreated();

        $this->assertNotSame($a->json('data.slug'), $b->json('data.slug'));
    }

    public function test_one_shop_per_user_with_a_clear_code_for_each_state(): void
    {
        $user = $this->user();
        $h = $this->as($user);
        $vendor = Vendor::create(['user_id' => $user->id, 'shop_name' => 'S', 'slug' => 's-' . Str::random(6), 'status' => 'pending']);

        $this->withHeaders($h)->postJson('/api/marketplace/vendor/apply', $this->payload())->assertStatus(409)->assertJsonPath('code', 'ALREADY_APPLIED');

        $vendor->update(['status' => 'active']);
        $this->withHeaders($h)->postJson('/api/marketplace/vendor/apply', $this->payload())->assertStatus(409)->assertJsonPath('code', 'ALREADY_VENDOR');

        $vendor->update(['status' => 'suspended']);
        $this->withHeaders($h)->postJson('/api/marketplace/vendor/apply', $this->payload())->assertStatus(409)->assertJsonPath('code', 'VENDOR_SUSPENDED');

        $this->assertSame(1, Vendor::where('user_id', $user->id)->count());
    }

    public function test_a_rejected_application_can_be_sent_again_on_the_same_shop(): void
    {
        $user = $this->user();
        $h = $this->as($user);
        $vendor = Vendor::create(['user_id' => $user->id, 'shop_name' => 'Old name', 'slug' => 'old-' . Str::random(6), 'status' => 'rejected', 'status_reason' => 'Missing phone']);

        $this->withHeaders($h)->getJson('/api/marketplace/vendor/me')->assertOk()
            ->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.status_reason', 'Missing phone');
        // editing is refused, resubmitting is the way
        $this->withHeaders($h)->putJson('/api/marketplace/vendor/me', ['shop_name' => 'x'])->assertStatus(409)->assertJsonPath('code', 'APPLICATION_REJECTED');

        $this->withHeaders($h)->postJson('/api/marketplace/vendor/apply', $this->payload(['shop_name' => 'New name']))
            ->assertOk()->assertJsonPath('data.id', $vendor->id)->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.status_reason', null)->assertJsonPath('data.shop_name', 'New name');
        $this->assertSame(1, Vendor::where('user_id', $user->id)->count());
    }

    public function test_a_user_whose_shop_was_deleted_can_apply_again(): void
    {
        $user = $this->user();
        Vendor::create(['user_id' => $user->id, 'shop_name' => 'Gone', 'slug' => 'gone-' . Str::random(6), 'status' => 'active'])->delete();

        $this->withHeaders($this->as($user))->getJson('/api/marketplace/vendor/me')->assertNotFound()->assertJsonPath('code', 'NO_VENDOR');
        $this->withHeaders($this->as($user))->postJson('/api/marketplace/vendor/apply', $this->payload())->assertCreated();
    }

    // ------------------------------------------------------------------ me

    public function test_me_returns_only_my_own_shop(): void
    {
        $mine = $this->user();
        $other = $this->user();
        $this->withHeaders($this->as($mine))->getJson('/api/marketplace/vendor/me')->assertNotFound()->assertJsonPath('code', 'NO_VENDOR');

        $vendor = Vendor::create(['user_id' => $other->id, 'shop_name' => 'Not mine', 'slug' => 'nm-' . Str::random(6), 'status' => 'active', 'commission_override' => 9]);
        $this->withHeaders($this->as($mine))->getJson('/api/marketplace/vendor/me')->assertNotFound();

        $own = $this->withHeaders($this->as($other))->getJson('/api/marketplace/vendor/me')->assertOk()
            ->assertJsonPath('data.id', $vendor->id)->assertJsonPath('data.can_sell', true)->assertJsonPath('data.products_count', 0);
        $this->assertStringNotContainsString('commission', $own->getContent());
    }

    // ------------------------------------------------------------------ update

    public function test_update_my_shop_partially_with_images_and_protected_fields(): void
    {
        $user = $this->user();
        $h = $this->as($user);
        $created = $this->withHeaders($h)->postJson('/api/marketplace/vendor/apply', $this->payload(['shop_name' => 'Before']))->assertCreated();
        $id = $created->json('data.id');
        $slug = $created->json('data.slug');

        $this->withHeaders($h)->putJson('/api/marketplace/vendor/me', [
            'shop_name' => 'After', 'brand_color' => '#1D3557', 'city_id' => 2,
            'status' => 'active', 'commission_override' => 1, 'slug' => 'hijack', 'user_id' => 99,
        ])->assertOk()->assertJsonPath('data.shop_name', 'After')->assertJsonPath('data.city.name', 'Jeddah')
            ->assertJsonPath('data.status', 'pending')->assertJsonPath('data.slug', $slug);
        $vendor = Vendor::find($id);
        $this->assertNull($vendor->commission_override);
        $this->assertSame($user->id, $vendor->user_id);
        $this->assertSame('+966500000000', $vendor->phone, 'untouched fields stay');

        // logo_path is a string from POST /api/marketplace/upload-image: set, replace, clear
        $firstLogo = $this->fakeMarketplaceImageUrl();
        $first = $this->withHeaders($h)->putJson('/api/marketplace/vendor/me', ['logo_path' => $firstLogo])->assertOk();
        $this->assertSame($firstLogo, $first->json('data.logo_url'));

        $secondLogo = $this->fakeMarketplaceImageUrl();
        $this->withHeaders($h)->putJson('/api/marketplace/vendor/me', ['logo_path' => $secondLogo])
            ->assertOk()->assertJsonPath('data.logo_url', $secondLogo);

        $this->withHeaders($h)->putJson('/api/marketplace/vendor/me', ['logo_path' => null])->assertOk()->assertJsonPath('data.logo_url', null);
    }

    public function test_update_validation_and_guards(): void
    {
        $user = $this->user();
        $h = $this->as($user);
        $vendor = Vendor::create(['user_id' => $user->id, 'shop_name' => 'S', 'slug' => 's-' . Str::random(6), 'status' => 'active', 'country_id' => 1, 'city_id' => 1, 'phone' => '1']);

        $this->withHeaders($h)->putJson('/api/marketplace/vendor/me', ['shop_name' => ''])->assertStatus(422)->assertJsonValidationErrors('shop_name');
        // country is kept from the record: a city of another country is refused
        $this->withHeaders($h)->putJson('/api/marketplace/vendor/me', ['city_id' => 9999999])->assertStatus(422)->assertJsonValidationErrors('city_id');
        $this->withHeaders($h)->putJson('/api/marketplace/vendor/me', ['country_id' => 2, 'city_id' => 1])->assertStatus(422)->assertJsonValidationErrors('city_id');

        $vendor->update(['status' => 'suspended', 'status_reason' => 'Fake parts']);
        $this->withHeaders($h)->putJson('/api/marketplace/vendor/me', ['shop_name' => 'x'])
            ->assertForbidden()->assertJsonPath('code', 'VENDOR_SUSPENDED')->assertJsonPath('reason', 'Fake parts');
        $this->withHeaders($this->as($this->user()))->putJson('/api/marketplace/vendor/me', ['shop_name' => 'x'])->assertNotFound();
    }

    // ------------------------------------------------------------------ the whole loop

    public function test_full_flow_apply_then_admin_approves_then_shop_goes_public(): void
    {
        $user = $this->user();
        $admin = $this->user(['role_id' => 1]);

        $created = $this->withHeaders($this->as($user))->postJson('/api/marketplace/vendor/apply', $this->payload(['shop_name' => 'Loop Shop ' . Str::random(4)]))->assertCreated();
        $id = $created->json('data.id');
        $slug = $created->json('data.slug');

        // it shows up in the admin queue as pending
        $queue = $this->withHeaders($this->as($admin))->getJson('/api/admin/marketplace/vendors?status=pending&search=' . $slug)->assertOk()->json('data');
        $this->assertSame([$id], collect($queue)->pluck('id')->all());
        $this->assertSame($user->id, $queue[0]['owner']['id']);

        $this->getJson("/api/marketplace/vendors/$slug")->assertNotFound();
        $this->withHeaders($this->as($user))->getJson('/api/marketplace/vendor/me')->assertJsonPath('data.can_sell', false);

        $this->withHeaders($this->as($admin))->postJson("/api/admin/marketplace/vendors/$id/approve")->assertOk();

        $this->withHeaders($this->as($user))->getJson('/api/marketplace/vendor/me')->assertOk()
            ->assertJsonPath('data.status', 'active')->assertJsonPath('data.can_sell', true);
        $this->getJson("/api/marketplace/vendors/$slug")->assertOk()->assertJsonPath('data.id', $id);

        // and the rejection path shows the reason to the applicant
        $other = $this->user();
        $rejectedId = $this->withHeaders($this->as($other))->postJson('/api/marketplace/vendor/apply', $this->payload())->json('data.id');
        $this->withHeaders($this->as($admin))->postJson("/api/admin/marketplace/vendors/$rejectedId/reject", ['reason' => 'Photos missing'])->assertOk();
        $this->withHeaders($this->as($other))->getJson('/api/marketplace/vendor/me')
            ->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.status_reason', 'Photos missing');
    }
}
