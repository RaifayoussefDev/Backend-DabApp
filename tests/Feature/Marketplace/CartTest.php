<?php

namespace Tests\Feature\Marketplace;

use App\Models\Marketplace\Category;
use App\Models\Marketplace\Product;
use App\Models\Marketplace\Vendor;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Cart: works for both guests (X-Cart-Session header) and logged-in users (Bearer token), never reserves
 * stock itself (that happens when an order is created - see Checkout). DatabaseTransactions: safe on the dev DB.
 */
class CartTest extends TestCase
{
    use DatabaseTransactions;

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

    private function vendor(): Vendor
    {
        return Vendor::create([
            'user_id' => $this->user()->id, 'shop_name' => 'Shop ' . Str::random(6), 'slug' => 'shop-' . Str::random(6), 'status' => 'active',
        ]);
    }

    private function category(): Category
    {
        return Category::create(['name' => '0 Cart Cat ' . Str::random(6), 'slug' => '0-cart-cat-' . Str::random(6)]);
    }

    private function product(array $attrs = []): Product
    {
        return Product::create($attrs + [
            'vendor_id' => $this->vendor()->id, 'category_id' => $this->category()->id,
            'reference' => '0-CART-' . Str::random(10), 'slug' => '0-cart-' . Str::random(10),
            'name' => '0 Cart Product ' . Str::random(6), 'price' => 100, 'stock_quantity' => 10, 'status' => 'active',
        ]);
    }

    private function cartSession(): array
    {
        return ['X-Cart-Session' => (string) Str::uuid()];
    }

    // ------------------------------------------------------------------ empty cart / identity

    public function test_empty_cart_shape_for_a_guest_with_no_session_and_no_history(): void
    {
        $this->getJson('/api/marketplace/cart')->assertOk()
            ->assertJson(['data' => ['id' => null, 'items' => [], 'items_count' => 0, 'subtotal' => 0]]);
    }

    public function test_empty_cart_shape_for_a_fresh_user(): void
    {
        $this->withHeaders($this->as($this->user()))->getJson('/api/marketplace/cart')->assertOk()
            ->assertJson(['data' => ['id' => null, 'items' => []]]);
    }

    public function test_guest_add_without_a_session_header_is_400(): void
    {
        $product = $this->product();

        $this->postJson('/api/marketplace/cart/items', ['product_id' => $product->id])
            ->assertStatus(400)->assertJson(['code' => 'MISSING_CART_SESSION']);
    }

    public function test_two_different_guest_sessions_never_see_each_others_cart(): void
    {
        $product = $this->product();
        $sessionA = $this->cartSession();
        $sessionB = $this->cartSession();

        $this->withHeaders($sessionA)->postJson('/api/marketplace/cart/items', ['product_id' => $product->id])->assertCreated();

        $this->withHeaders($sessionB)->getJson('/api/marketplace/cart')->assertOk()->assertJson(['data' => ['items' => []]]);
    }

    // ------------------------------------------------------------------ add item

    public function test_guest_adds_an_item_and_sees_it_in_the_cart(): void
    {
        $product = $this->product(['price' => 199]);
        $session = $this->cartSession();

        $data = $this->withHeaders($session)->postJson('/api/marketplace/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertCreated()->json('data');

        $this->assertCount(1, $data['items']);
        $this->assertSame(2, $data['items'][0]['quantity']);
        $this->assertEquals(199, $data['items'][0]['unit_price']);
        $this->assertEquals(398, $data['items'][0]['line_total']);
        $this->assertSame(2, $data['items_count']);
        $this->assertEquals(398, $data['subtotal']);

        $this->withHeaders($session)->getJson('/api/marketplace/cart')->assertOk()->assertJsonCount(1, 'data.items');
    }

    public function test_user_adds_an_item_with_just_a_token_no_session_header_needed(): void
    {
        $product = $this->product();

        $this->withHeaders($this->as($this->user()))->postJson('/api/marketplace/cart/items', ['product_id' => $product->id])
            ->assertCreated()->assertJsonCount(1, 'data.items');
    }

    public function test_adding_the_same_product_again_increases_quantity_not_a_new_line(): void
    {
        $product = $this->product();
        $session = $this->cartSession();

        $this->withHeaders($session)->postJson('/api/marketplace/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertCreated();
        $data = $this->withHeaders($session)->postJson('/api/marketplace/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertCreated()->json('data');

        $this->assertCount(1, $data['items']);
        $this->assertSame(3, $data['items'][0]['quantity']);
    }

    public function test_unknown_product_is_404(): void
    {
        $this->withHeaders($this->cartSession())->postJson('/api/marketplace/cart/items', ['product_id' => 999999999])->assertNotFound();
    }

    public function test_insufficient_stock_is_409(): void
    {
        $product = $this->product(['stock_quantity' => 2]);

        $this->withHeaders($this->cartSession())->postJson('/api/marketplace/cart/items', ['product_id' => $product->id, 'quantity' => 3])
            ->assertStatus(409)->assertJson(['code' => 'INSUFFICIENT_STOCK', 'available' => 2]);
    }

    // ------------------------------------------------------------------ variants

    public function test_a_product_with_variants_requires_variant_id(): void
    {
        $product = $this->product();
        $product->variants()->create(['sku' => '0-CARTV-' . Str::random(8), 'price' => 50, 'stock_quantity' => 5]);

        $this->withHeaders($this->cartSession())->postJson('/api/marketplace/cart/items', ['product_id' => $product->id])
            ->assertStatus(422)->assertJsonValidationErrors('variant_id');
    }

    public function test_a_product_without_variants_refuses_a_variant_id(): void
    {
        $product = $this->product();

        $this->withHeaders($this->cartSession())->postJson('/api/marketplace/cart/items', ['product_id' => $product->id, 'variant_id' => 999])
            ->assertStatus(422)->assertJsonValidationErrors('variant_id');
    }

    public function test_adding_with_a_variant_uses_the_variants_own_price_and_stock(): void
    {
        $product = $this->product(['price' => 999]); // fallback price, must be ignored
        $variant = $product->variants()->create(['sku' => '0-CARTV-' . Str::random(8), 'option1_name' => 'Color', 'option1_value' => 'Black', 'price' => 45, 'stock_quantity' => 3]);

        $data = $this->withHeaders($this->cartSession())->postJson('/api/marketplace/cart/items', [
            'product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 2,
        ])->assertCreated()->json('data');

        $this->assertEquals(45, $data['items'][0]['unit_price']);
        $this->assertSame('Black', $data['items'][0]['variant']['option1_value']);
    }

    public function test_variant_stock_is_tracked_separately_from_the_products_own_stock(): void
    {
        $product = $this->product(['stock_quantity' => 999]); // plenty on the product itself, irrelevant once variants exist
        $variant = $product->variants()->create(['sku' => '0-CARTV-' . Str::random(8), 'price' => 45, 'stock_quantity' => 3]);

        $this->withHeaders($this->cartSession())->postJson('/api/marketplace/cart/items', [
            'product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 4,
        ])->assertStatus(409)->assertJson(['code' => 'INSUFFICIENT_STOCK', 'available' => 3]);
    }

    public function test_unknown_variant_is_404(): void
    {
        $product = $this->product();
        $product->variants()->create(['sku' => '0-CARTV-' . Str::random(8), 'price' => 50, 'stock_quantity' => 5]);

        $this->withHeaders($this->cartSession())->postJson('/api/marketplace/cart/items', ['product_id' => $product->id, 'variant_id' => 999999])
            ->assertNotFound();
    }

    // ------------------------------------------------------------------ update / remove / clear

    public function test_update_item_quantity(): void
    {
        $product = $this->product(['stock_quantity' => 10]);
        $session = $this->cartSession();
        $itemId = $this->withHeaders($session)->postJson('/api/marketplace/cart/items', ['product_id' => $product->id])->json('data.items.0.id');

        $data = $this->withHeaders($session)->putJson("/api/marketplace/cart/items/{$itemId}", ['quantity' => 5])->assertOk()->json('data');

        $this->assertSame(5, $data['items'][0]['quantity']);
    }

    public function test_update_beyond_stock_is_409(): void
    {
        $product = $this->product(['stock_quantity' => 3]);
        $session = $this->cartSession();
        $itemId = $this->withHeaders($session)->postJson('/api/marketplace/cart/items', ['product_id' => $product->id])->json('data.items.0.id');

        $this->withHeaders($session)->putJson("/api/marketplace/cart/items/{$itemId}", ['quantity' => 10])
            ->assertStatus(409)->assertJson(['code' => 'INSUFFICIENT_STOCK', 'available' => 3]);
    }

    public function test_another_sessions_item_id_is_404(): void
    {
        $product = $this->product();
        $itemId = $this->withHeaders($this->cartSession())->postJson('/api/marketplace/cart/items', ['product_id' => $product->id])->json('data.items.0.id');

        $this->withHeaders($this->cartSession())->putJson("/api/marketplace/cart/items/{$itemId}", ['quantity' => 2])->assertNotFound();
    }

    public function test_remove_item(): void
    {
        $product = $this->product();
        $session = $this->cartSession();
        $itemId = $this->withHeaders($session)->postJson('/api/marketplace/cart/items', ['product_id' => $product->id])->json('data.items.0.id');

        $data = $this->withHeaders($session)->deleteJson("/api/marketplace/cart/items/{$itemId}")->assertOk()->json('data');

        $this->assertCount(0, $data['items']);
    }

    public function test_clear_cart(): void
    {
        $session = $this->cartSession();
        $this->withHeaders($session)->postJson('/api/marketplace/cart/items', ['product_id' => $this->product()->id])->assertCreated();
        $this->withHeaders($session)->postJson('/api/marketplace/cart/items', ['product_id' => $this->product()->id])->assertCreated();

        $data = $this->withHeaders($session)->deleteJson('/api/marketplace/cart')->assertOk()->json('data');

        $this->assertCount(0, $data['items']);
        $this->assertSame(0, $data['subtotal']);
    }

    // ------------------------------------------------------------------ price changes

    public function test_price_changed_flag_when_the_product_price_moves_after_adding(): void
    {
        $product = $this->product(['price' => 100]);
        $session = $this->cartSession();
        $this->withHeaders($session)->postJson('/api/marketplace/cart/items', ['product_id' => $product->id])->assertCreated();

        $product->update(['price' => 150]);

        $data = $this->withHeaders($session)->getJson('/api/marketplace/cart')->assertOk()->json('data');

        $this->assertEquals(100, $data['items'][0]['unit_price'], 'the snapshot stays frozen');
        $this->assertEquals(150, $data['items'][0]['current_price']);
        $this->assertTrue($data['items'][0]['price_changed']);
        $this->assertEquals(100, $data['items'][0]['line_total'], 'line total uses the frozen price, not the new one');
    }

    // ------------------------------------------------------------------ merge on login

    public function test_merge_moves_guest_items_into_the_account_cart(): void
    {
        $guestSession = $this->cartSession();
        $productA = $this->product(['price' => 50]);
        $productB = $this->product(['price' => 75]);
        $this->withHeaders($guestSession)->postJson('/api/marketplace/cart/items', ['product_id' => $productA->id, 'quantity' => 2])->assertCreated();
        $this->withHeaders($guestSession)->postJson('/api/marketplace/cart/items', ['product_id' => $productB->id])->assertCreated();

        $user = $this->user();
        $data = $this->withHeaders($this->as($user))->postJson('/api/marketplace/cart/merge', ['session_id' => $guestSession['X-Cart-Session']])
            ->assertOk()->json('data');

        $this->assertCount(2, $data['items']);
        $this->assertSame(3, $data['items_count']);

        // the old guest session cart is empty now
        $this->withHeaders($guestSession)->getJson('/api/marketplace/cart')->assertOk()->assertJson(['data' => ['items' => []]]);
    }

    public function test_merge_adds_quantities_when_the_same_product_is_in_both_carts(): void
    {
        $product = $this->product(['stock_quantity' => 10]);
        $guestSession = $this->cartSession();
        $this->withHeaders($guestSession)->postJson('/api/marketplace/cart/items', ['product_id' => $product->id, 'quantity' => 2])->assertCreated();

        $user = $this->user();
        $auth = $this->as($user);
        $this->withHeaders($auth)->postJson('/api/marketplace/cart/items', ['product_id' => $product->id, 'quantity' => 3])->assertCreated();

        $data = $this->withHeaders($auth)->postJson('/api/marketplace/cart/merge', ['session_id' => $guestSession['X-Cart-Session']])
            ->assertOk()->json('data');

        $this->assertCount(1, $data['items']);
        $this->assertSame(5, $data['items'][0]['quantity']);
    }

    public function test_merge_with_no_guest_cart_is_a_harmless_noop(): void
    {
        $this->withHeaders($this->as($this->user()))->postJson('/api/marketplace/cart/merge', ['session_id' => (string) Str::uuid()])
            ->assertOk()->assertJson(['data' => ['items' => []]]);
    }
}
