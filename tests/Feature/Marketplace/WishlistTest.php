<?php

namespace Tests\Feature\Marketplace;

use App\Models\Marketplace\Category;
use App\Models\Marketplace\Product;
use App\Models\Marketplace\Vendor;
use App\Models\Marketplace\Wishlist;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Step 3 wishlist (favorites). Access: User (auth:api).
 * DatabaseTransactions: nothing is persisted, safe on the dev database.
 */
class WishlistTest extends TestCase
{
    use DatabaseTransactions;

    private function user(): User
    {
        return User::factory()->create(['email' => Str::uuid() . '@mp-test.local']);
    }

    private function headers(User $user): array
    {
        JWTAuth::unsetToken();
        app('tymon.jwt')->unsetToken();
        app('auth')->forgetGuards();

        return ['Authorization' => 'Bearer ' . JWTAuth::fromUser($user)];
    }

    private function product(): Product
    {
        $vendor = Vendor::create([
            'user_id' => User::factory()->create(['email' => Str::uuid() . '@mp-test.local'])->id,
            'shop_name' => 'Shop ' . Str::random(6), 'slug' => 'shop-' . Str::random(6), 'status' => 'active',
        ]);
        $category = Category::create(['name' => 'Cat', 'slug' => 'cat-' . Str::random(6)]);

        return Product::create([
            'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'reference' => '0-W-' . Str::random(10), 'slug' => '0-w-' . Str::random(10),
            'name' => '0 Wishlist Product', 'price' => 99, 'status' => 'active',
        ]);
    }

    public function test_requires_auth(): void
    {
        $product = $this->product();

        $this->getJson('/api/marketplace/wishlist')->assertUnauthorized();
        $this->postJson("/api/marketplace/wishlist/{$product->id}")->assertUnauthorized();
        $this->deleteJson("/api/marketplace/wishlist/{$product->id}")->assertUnauthorized();
    }

    public function test_add_list_and_remove(): void
    {
        $user = $this->user();
        $headers = $this->headers($user);
        $product = $this->product();

        $this->assertEmpty($this->withHeaders($headers)->getJson('/api/marketplace/wishlist')->json('data'));

        $this->withHeaders($headers)->postJson("/api/marketplace/wishlist/{$product->id}")->assertCreated();

        $data = $this->withHeaders($headers)->getJson('/api/marketplace/wishlist')->assertOk()->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($product->slug, $data[0]['slug']);

        $this->withHeaders($headers)->deleteJson("/api/marketplace/wishlist/{$product->id}")->assertOk();
        $this->assertEmpty($this->withHeaders($headers)->getJson('/api/marketplace/wishlist')->json('data'));
    }

    public function test_adding_twice_does_not_duplicate_and_re_adding_after_remove_restores(): void
    {
        $user = $this->user();
        $headers = $this->headers($user);
        $product = $this->product();

        $this->withHeaders($headers)->postJson("/api/marketplace/wishlist/{$product->id}")->assertCreated();
        $this->withHeaders($headers)->postJson("/api/marketplace/wishlist/{$product->id}")->assertCreated();
        $this->assertSame(1, Wishlist::where('user_id', $user->id)->where('product_id', $product->id)->count());

        $this->withHeaders($headers)->deleteJson("/api/marketplace/wishlist/{$product->id}")->assertOk();
        $this->assertSame(0, Wishlist::where('user_id', $user->id)->where('product_id', $product->id)->count(), 'soft-deleted, excluded from the default scope');

        $this->withHeaders($headers)->postJson("/api/marketplace/wishlist/{$product->id}")->assertCreated();
        $this->assertSame(1, Wishlist::where('user_id', $user->id)->where('product_id', $product->id)->count(), 'restored, not a second row');
        $this->assertSame(1, Wishlist::withTrashed()->where('user_id', $user->id)->where('product_id', $product->id)->count(), 'never a duplicate, even soft-deleted ones');
    }

    public function test_removing_something_not_in_the_wishlist_is_still_ok(): void
    {
        $headers = $this->headers($this->user());
        $product = $this->product();

        $this->withHeaders($headers)->deleteJson("/api/marketplace/wishlist/{$product->id}")->assertOk();
    }

    public function test_adding_an_unknown_product_is_404(): void
    {
        $headers = $this->headers($this->user());

        $this->withHeaders($headers)->postJson('/api/marketplace/wishlist/999999')->assertNotFound();
    }

    public function test_wishlist_is_private_per_user(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $product = $this->product();

        $this->withHeaders($this->headers($owner))->postJson("/api/marketplace/wishlist/{$product->id}")->assertCreated();

        $this->assertEmpty($this->withHeaders($this->headers($other))->getJson('/api/marketplace/wishlist')->json('data'));
    }
}
