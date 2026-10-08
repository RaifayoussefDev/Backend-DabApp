<?php

namespace Tests\Feature\Marketplace;

use App\Models\Marketplace\Brand;
use App\Models\Marketplace\Category;
use App\Models\Marketplace\Product;
use App\Models\Marketplace\ShippingProvider;
use App\Models\Marketplace\Vendor;
use App\Models\Marketplace\VendorShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * The examples in /marketplace/documentation are what the mobile developer copies. This test keeps them honest:
 * every documented example must have the same fields as what the API really returns.
 * (Only the structure is compared: keys and nesting, not the values.)
 */
class SwaggerExamplesTest extends TestCase
{
    use DatabaseTransactions;

    private static ?array $doc = null;

    private function doc(): array
    {
        if (self::$doc === null) {
            Artisan::call('l5-swagger:generate', ['documentation' => 'marketplace']);
            self::$doc = json_decode(file_get_contents(storage_path('api-docs/marketplace-docs.json')), true);
        }

        return self::$doc;
    }

    /** All examples documented for one response: name => value. */
    private function examples(string $path, string $verb, string $status): array
    {
        $response = $this->doc()['paths'][$path][$verb]['responses'][$status] ?? [];

        // shared responses (401, 422) are referenced from components
        if (isset($response['$ref'])) {
            $response = $this->doc()['components']['responses'][basename($response['$ref'])] ?? [];
        }

        $content = $response['content']['application/json'] ?? null;
        $this->assertNotNull($content, "$verb $path $status has no documented body");

        $found = [];
        if (isset($content['example'])) {
            $found['default'] = $content['example'];
        }
        foreach ($content['examples'] ?? [] as $name => $example) {
            $found[$name] = $example['value'];
        }
        $this->assertNotEmpty($found, "$verb $path $status has no example");

        return $found;
    }

    /** Same keys, same nesting. Lists are compared on their first element, nulls and empty lists are skipped. */
    private function assertSameShape(mixed $documented, mixed $actual, string $where): void
    {
        if ($documented === null || $actual === null || (is_array($documented) && ! is_array($actual)) || (! is_array($documented) && is_array($actual))) {
            if ($documented !== null && $actual !== null) {
                $this->fail("$where: documented as " . gettype($documented) . ' but the API returns ' . gettype($actual));
            }

            return;
        }
        if (! is_array($documented)) {
            return;
        }

        // validation errors are a free map: field name => list of messages. Compare the form, not the field names.
        if (str_ends_with($where, '.errors')) {
            foreach ([$documented, $actual] as $errors) {
                foreach ($errors as $field => $messages) {
                    $this->assertTrue(is_string($field) && is_array($messages) && array_is_list($messages), "$where: errors must map a field to a list of messages");
                }
            }

            return;
        }

        if (array_is_list($documented) || array_is_list($actual)) {
            if ($documented && $actual) {
                $this->assertSameShape($documented[0], $actual[0], "$where[0]");
            }

            return;
        }

        $missingInDocs = array_diff(array_keys($actual), array_keys($documented));
        $extraInDocs = array_diff(array_keys($documented), array_keys($actual));
        $this->assertSame([], array_values($missingInDocs), "$where: the API returns fields the example does not show");
        $this->assertSame([], array_values($extraInDocs), "$where: the example shows fields the API does not return");

        foreach ($documented as $key => $value) {
            $this->assertSameShape($value, $actual[$key], "$where.$key");
        }
    }

    private function check(string $path, string $verb, string $status, string $example, mixed $actual): void
    {
        $examples = $this->examples($path, $verb, $status);
        $this->assertArrayHasKey($example, $examples, "$verb $path $status has no example named '$example' (has: " . implode(', ', array_keys($examples)) . ')');
        $this->assertSameShape($examples[$example], $actual, "$verb $path $status [$example]");
    }

    private function user(): User
    {
        return User::factory()->create(['email' => Str::uuid() . '@mp-test.local', 'role_id' => 2, 'is_active' => true]);
    }

    private function as(User $user): array
    {
        $token = JWTAuth::fromUser($user);
        JWTAuth::unsetToken();
        $this->app->make('tymon.jwt')->unsetToken();
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer ' . $token];
    }

    // ------------------------------------------------------------------

    public function test_category_examples_match_the_api(): void
    {
        $root = Category::create(['name' => 'Parts', 'name_ar' => 'قطع', 'slug' => 'ex-parts-' . Str::random(5)]);
        $tyres = Category::create(['name' => 'Tyres', 'name_ar' => 'إطارات', 'slug' => 'ex-tyres-' . Str::random(5), 'parent_id' => $root->id]);
        Category::create(['name' => 'Front', 'slug' => 'ex-front-' . Str::random(5), 'parent_id' => $tyres->id]);

        $top = $this->getJson('/api/marketplace/categories')->assertOk()->json();
        $this->check('/api/marketplace/categories', 'get', '200', 'top_level', $top);

        $children = $this->getJson('/api/marketplace/categories?parent_id=' . $root->id)->assertOk()->json();
        $this->check('/api/marketplace/categories', 'get', '200', 'children', $children);

        $tree = $this->getJson('/api/marketplace/categories?tree=1')->assertOk()->json();
        $rootRow = collect($tree['data'])->firstWhere('id', $root->id);
        $this->check('/api/marketplace/categories', 'get', '200', 'tree', ['data' => [$rootRow]]);

        $detail = $this->getJson('/api/marketplace/categories/' . $tyres->slug)->assertOk()->json();
        $this->check('/api/marketplace/categories/{idOrSlug}', 'get', '200', 'default', $detail);

        $this->check('/api/marketplace/categories', 'get', '404', 'default', $this->getJson('/api/marketplace/categories?parent_id=99999999')->assertNotFound()->json());
        $this->check('/api/marketplace/categories/{idOrSlug}', 'get', '404', 'default', $this->getJson('/api/marketplace/categories/nope')->assertNotFound()->json());
    }

    public function test_brand_examples_match_the_api(): void
    {
        Brand::create(['name' => 'Ex brand', 'slug' => 'ex-brand-' . Str::random(5), 'is_featured' => true]);

        $this->check('/api/marketplace/brands', 'get', '200', 'all', $this->getJson('/api/marketplace/brands?limit=3')->assertOk()->json());
        $this->check('/api/marketplace/brands', 'get', '200', 'empty', $this->getJson('/api/marketplace/brands?search=zzzzzz-' . Str::random(6))->assertOk()->json());
    }

    public function test_vendor_examples_match_the_api(): void
    {
        $vendor = Vendor::create([
            'user_id' => $this->user()->id, 'shop_name' => 'Ex shop', 'slug' => 'ex-shop-' . Str::random(5), 'status' => 'active',
            'country_id' => 1, 'city_id' => 1, 'description' => 'Tyres',
        ]);
        $category = Category::create(['name' => 'Cat', 'slug' => 'ex-cat-' . Str::random(5)]);
        Product::create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'reference' => 'EX-' . Str::random(8), 'slug' => 'ex-p-' . Str::random(8), 'name' => 'P', 'price' => 5, 'status' => 'active']);
        $provider = ShippingProvider::create(['name' => 'Ex carrier', 'code' => 'ex-' . strtolower(Str::random(6))]);
        VendorShippingMethod::create(['vendor_id' => $vendor->id, 'provider_id' => $provider->id, 'flat_rate' => 20, 'estimated_days_min' => 1, 'estimated_days_max' => 3]);

        $list = $this->getJson('/api/marketplace/vendors?search=Ex shop')->assertOk()->json();
        $this->check('/api/marketplace/vendors', 'get', '200', 'default', $list);

        $shop = $this->getJson('/api/marketplace/vendors/' . $vendor->slug)->assertOk()->json();
        $this->check('/api/marketplace/vendors/{idOrSlug}', 'get', '200', 'default', $shop);

        $this->check('/api/marketplace/vendors/{idOrSlug}', 'get', '404', 'default', $this->getJson('/api/marketplace/vendors/nope')->assertNotFound()->json());
    }

    public function test_vendor_account_examples_match_the_api(): void
    {
        $user = $this->user();
        $payload = ['shop_name' => 'Ex applicant ' . Str::random(4), 'phone' => '+966500000000', 'country_id' => 1, 'city_id' => 1];

        // guest + validation
        $this->check('/api/marketplace/vendor/me', 'get', '401', 'default', $this->getJson('/api/marketplace/vendor/me')->assertUnauthorized()->json()) ;
        $this->check('/api/marketplace/vendor/apply', 'post', '422', 'default', $this->withHeaders($this->as($user))->postJson('/api/marketplace/vendor/apply', ['country_id' => 2, 'city_id' => 1])->assertStatus(422)->json());

        // no shop yet
        $this->check('/api/marketplace/vendor/me', 'get', '404', 'default', $this->withHeaders($this->as($user))->getJson('/api/marketplace/vendor/me')->assertNotFound()->json());
        $this->check('/api/marketplace/vendor/me', 'put', '404', 'default', $this->withHeaders($this->as($user))->putJson('/api/marketplace/vendor/me', ['shop_name' => 'x'])->assertNotFound()->json());

        // apply, then every state of "my shop"
        $created = $this->withHeaders($this->as($user))->postJson('/api/marketplace/vendor/apply', $payload)->assertCreated()->json();
        $this->check('/api/marketplace/vendor/apply', 'post', '201', 'default', $created);

        $again = $this->withHeaders($this->as($user))->postJson('/api/marketplace/vendor/apply', $payload)->assertStatus(409)->json();
        $this->check('/api/marketplace/vendor/apply', 'post', '409', 'already_applied', $again);

        $updated = $this->withHeaders($this->as($user))->putJson('/api/marketplace/vendor/me', ['description' => 'New'])->assertOk()->json();
        $this->check('/api/marketplace/vendor/me', 'put', '200', 'default', $updated);

        $vendor = Vendor::where('user_id', $user->id)->first();

        $this->check('/api/marketplace/vendor/me', 'get', '200', 'pending', $this->withHeaders($this->as($user))->getJson('/api/marketplace/vendor/me')->json());

        $vendor->update(['status' => 'active']);
        $this->check('/api/marketplace/vendor/me', 'get', '200', 'active', $this->withHeaders($this->as($user))->getJson('/api/marketplace/vendor/me')->json());
        $this->check('/api/marketplace/vendor/apply', 'post', '409', 'already_vendor', $this->withHeaders($this->as($user))->postJson('/api/marketplace/vendor/apply', $payload)->assertStatus(409)->json());

        $vendor->update(['status' => 'suspended', 'status_reason' => 'Fake parts']);
        $this->check('/api/marketplace/vendor/apply', 'post', '409', 'suspended', $this->withHeaders($this->as($user))->postJson('/api/marketplace/vendor/apply', $payload)->assertStatus(409)->json());
        $this->check('/api/marketplace/vendor/me', 'put', '403', 'default', $this->withHeaders($this->as($user))->putJson('/api/marketplace/vendor/me', ['shop_name' => 'x'])->assertForbidden()->json());

        $vendor->update(['status' => 'rejected', 'status_reason' => 'Photos are missing']);
        $this->check('/api/marketplace/vendor/me', 'get', '200', 'rejected', $this->withHeaders($this->as($user))->getJson('/api/marketplace/vendor/me')->json());
        $this->check('/api/marketplace/vendor/me', 'put', '409', 'default', $this->withHeaders($this->as($user))->putJson('/api/marketplace/vendor/me', ['shop_name' => 'x'])->assertStatus(409)->json());

        $resent = $this->withHeaders($this->as($user))->postJson('/api/marketplace/vendor/apply', $payload)->assertOk()->json();
        $this->check('/api/marketplace/vendor/apply', 'post', '200', 'default', $resent);
    }
}
