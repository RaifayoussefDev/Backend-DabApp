<?php

namespace Tests\Feature\Marketplace;

use App\Models\Marketplace\Category;
use App\Models\Marketplace\Product;
use App\Models\Marketplace\ProductReview;
use App\Models\Marketplace\Vendor;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Step 4: product reviews (list + rating breakdown = public, writing a review = user auth:api).
 * DatabaseTransactions: nothing is persisted, safe on the dev database.
 */
class ProductReviewTest extends TestCase
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
            'shop_name' => '0 Shop ' . Str::random(6), 'slug' => '0-shop-' . Str::random(6), 'status' => 'active',
        ]);
        $category = Category::create(['name' => '0 Review Cat', 'slug' => '0-review-cat-' . Str::random(6)]);

        return Product::create([
            'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'reference' => '0-RV-' . Str::random(10), 'slug' => '0-rv-' . Str::random(10),
            'name' => '0 Reviewed Product', 'price' => 99, 'status' => 'active',
        ]);
    }

    public function test_write_a_review_updates_the_products_rating_counters(): void
    {
        $product = $this->product();
        $headers = $this->headers($this->user());

        $response = $this->withHeaders($headers)->postJson("/api/marketplace/products/{$product->slug}/reviews", [
            'rating' => 5, 'title' => 'Great Product!!', 'comment' => 'Works fine and everything is great',
            'tags' => ['Build Quality', 'Delivery Time'], 'images' => [], 'is_anonymous' => false,
        ])->assertCreated();

        $this->assertSame(5, $response->json('data.rating'));
        $this->assertSame(['Build Quality', 'Delivery Time'], $response->json('data.tags'));
        $this->assertFalse($response->json('data.verified_purchase'), 'no orders exist yet, always false for now');
        $this->assertNotNull($response->json('data.author.name'));

        $fresh = $product->fresh();
        $this->assertEquals(5, $fresh->rating_avg);
        $this->assertSame(1, $fresh->reviews_count);
    }

    public function test_anonymous_review_hides_the_author(): void
    {
        $product = $this->product();
        $headers = $this->headers($this->user());

        $response = $this->withHeaders($headers)->postJson("/api/marketplace/products/{$product->slug}/reviews", [
            'rating' => 4, 'is_anonymous' => true,
        ])->assertCreated();

        $this->assertNull($response->json('data.author'));
    }

    public function test_one_review_per_user_per_product(): void
    {
        $product = $this->product();
        $user = $this->user();

        $this->withHeaders($this->headers($user))->postJson("/api/marketplace/products/{$product->slug}/reviews", ['rating' => 5])->assertCreated();

        $this->withHeaders($this->headers($user))->postJson("/api/marketplace/products/{$product->slug}/reviews", ['rating' => 1])
            ->assertStatus(409)->assertJson(['code' => 'ALREADY_REVIEWED']);
    }

    public function test_requires_auth_to_write(): void
    {
        $product = $this->product();

        $this->postJson("/api/marketplace/products/{$product->slug}/reviews", ['rating' => 5])->assertUnauthorized();
    }

    public function test_validation_errors(): void
    {
        $product = $this->product();
        $headers = $this->headers($this->user());

        $this->withHeaders($headers)->postJson("/api/marketplace/products/{$product->slug}/reviews", ['rating' => 6])
            ->assertStatus(422)->assertJsonValidationErrors(['rating']);
        $this->withHeaders($headers)->postJson("/api/marketplace/products/{$product->slug}/reviews", [])
            ->assertStatus(422)->assertJsonValidationErrors(['rating']);
    }

    public function test_write_to_unknown_product_is_404(): void
    {
        $headers = $this->headers($this->user());

        $this->withHeaders($headers)->postJson('/api/marketplace/products/does-not-exist/reviews', ['rating' => 5])->assertNotFound();
    }

    public function test_list_sort_and_rating_breakdown(): void
    {
        $product = $this->product();

        ProductReview::create(['product_id' => $product->id, 'user_id' => $this->user()->id, 'rating' => 5]);
        ProductReview::create(['product_id' => $product->id, 'user_id' => $this->user()->id, 'rating' => 5]);
        ProductReview::create(['product_id' => $product->id, 'user_id' => $this->user()->id, 'rating' => 2]);
        ProductReview::create(['product_id' => $product->id, 'user_id' => $this->user()->id, 'rating' => 3, 'is_approved' => false]); // hidden

        $response = $this->getJson("/api/marketplace/products/{$product->slug}/reviews")->assertOk();

        $this->assertCount(3, $response->json('data'), 'unapproved reviews are excluded');
        $this->assertSame(['5' => 2, '4' => 0, '3' => 0, '2' => 1, '1' => 0], $response->json('summary.breakdown'));

        $highest = $this->getJson("/api/marketplace/products/{$product->slug}/reviews?sort=highest")->json('data');
        $this->assertSame(5, $highest[0]['rating']);

        $lowest = $this->getJson("/api/marketplace/products/{$product->slug}/reviews?sort=lowest")->json('data');
        $this->assertSame(2, $lowest[0]['rating']);
    }

    public function test_list_unknown_product_is_404(): void
    {
        $this->getJson('/api/marketplace/products/does-not-exist/reviews')->assertNotFound();
    }
}
