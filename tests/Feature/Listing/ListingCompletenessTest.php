<?php

namespace Tests\Feature\Listing;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * [audit-fix] Covers the P0 audit findings: listings could previously be
 * published with price = 0 and/or no category, which is why some spare-parts
 * listings showed "0.00 SAR" and "Other BIKE PART" in production.
 */
class ListingCompletenessTest extends TestCase
{
    use DatabaseTransactions;

    private function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer ' . JWTAuth::fromUser($user)];
    }

    private function draftListing(array $overrides = []): Listing
    {
        $seller = User::factory()->create(['is_active' => true]);

        $listing = Listing::create(array_merge([
            'seller_id' => $seller->id,
            'status' => 'draft',
            'step' => 2,
            'title' => 'A valid listing title',
            'description' => 'A description long enough for validation.',
            'price' => 100,
            'category_id' => 1,
            'auction_enabled' => false,
        ], $overrides));

        $listing->setRelation('seller', $seller);

        return $listing;
    }

    public function test_publish_is_rejected_when_price_is_zero()
    {
        $listing = $this->draftListing(['price' => 0]);

        $response = $this->withHeaders($this->authHeaders($listing->seller))
            ->putJson("/api/listings/complete/{$listing->id}", [
                'step' => 3,
                'action' => 'complete',
                'amount' => 0,
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['field' => 'price']);

        $this->assertDatabaseHas('listings', [
            'id' => $listing->id,
            'status' => 'draft',
        ]);
    }

    public function test_publish_is_rejected_when_category_is_missing()
    {
        $listing = $this->draftListing(['category_id' => null]);

        $response = $this->withHeaders($this->authHeaders($listing->seller))
            ->putJson("/api/listings/complete/{$listing->id}", [
                'step' => 3,
                'action' => 'complete',
                'amount' => 0,
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['field' => 'category_id']);
    }

    public function test_publish_is_rejected_when_spare_part_category_is_missing()
    {
        // bike_part_category_id is a NOT NULL FK on spare_parts, so a spare part
        // row can never exist without one — the realistic failure case is that
        // the seller never completed the bike-part sub-step at all.
        $listing = $this->draftListing(['category_id' => 2]);

        $response = $this->withHeaders($this->authHeaders($listing->seller))
            ->putJson("/api/listings/complete/{$listing->id}", [
                'step' => 3,
                'action' => 'complete',
                'amount' => 0,
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['field' => 'bike_part_category_id']);
    }

    public function test_publish_succeeds_with_valid_title_price_and_category()
    {
        $listing = $this->draftListing();

        $response = $this->withHeaders($this->authHeaders($listing->seller))
            ->putJson("/api/listings/complete/{$listing->id}", [
                'step' => 3,
                'action' => 'complete',
                'amount' => 0,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('listings', [
            'id' => $listing->id,
            'status' => 'published',
        ]);
    }
}
