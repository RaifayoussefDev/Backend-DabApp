<?php

namespace Tests\Feature\Listing;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * GET /api/listings/pending-follow-up — the in-app "did it sell?" bottom sheet.
 *
 * Must keep surfacing a listing right after its reminder was just sent, not
 * only while it's "due" by the schedule: SendListingFollowUps advances
 * next_follow_up_at to the NEXT cycle in the same write that sends the push,
 * so a naive "is it due" check goes false the instant the push fires — a
 * seller tapping the push they just received would land on "nothing pending".
 */
class PendingFollowUpTest extends TestCase
{
    use DatabaseTransactions;

    private function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer ' . JWTAuth::fromUser($user)];
    }

    private function listingFor(User $seller, array $overrides = []): Listing
    {
        return Listing::create(array_merge([
            'seller_id' => $seller->id,
            'status' => 'published',
            'step' => 3,
            'title' => 'A valid listing title',
            'description' => 'A description long enough for validation.',
            'price' => 100,
            'category_id' => 1,
            'auction_enabled' => false,
            'published_at' => now()->subDays(40),
        ], $overrides));
    }

    public function test_shows_a_listing_thats_due_by_schedule(): void
    {
        $seller = User::factory()->create();
        $listing = $this->listingFor($seller);
        $listing->forceFill(['next_follow_up_at' => now()->subMinute()])->saveQuietly();

        $response = $this->withHeaders($this->authHeaders($seller))
            ->getJson('/api/listings/pending-follow-up');

        $response->assertOk()->assertJsonPath('data.id', $listing->id);
    }

    public function test_still_shows_a_listing_just_reminded_but_not_yet_answered(): void
    {
        $seller = User::factory()->create();
        $listing = $this->listingFor($seller);
        // Exact state right after SendListingFollowUps just ran for it: reminder
        // sent, schedule already advanced to the next cycle, no answer yet.
        $listing->forceFill([
            'follow_up_sent_at' => now(),
            'follow_up_responded_at' => null,
            'next_follow_up_at' => now()->addDays(30),
        ])->saveQuietly();

        $response = $this->withHeaders($this->authHeaders($seller))
            ->getJson('/api/listings/pending-follow-up');

        $response->assertOk()->assertJsonPath('data.id', $listing->id);
    }

    public function test_hides_a_listing_once_answered(): void
    {
        $seller = User::factory()->create();
        $listing = $this->listingFor($seller);
        $listing->forceFill([
            'follow_up_sent_at' => now()->subDay(),
            'follow_up_responded_at' => now(),
            'follow_up_response' => 'not_sold',
            'next_follow_up_at' => now()->addDays(30),
        ])->saveQuietly();

        $response = $this->withHeaders($this->authHeaders($seller))
            ->getJson('/api/listings/pending-follow-up');

        $response->assertOk()->assertJsonPath('data', null);
    }

    public function test_hides_a_sold_listing_even_if_never_answered(): void
    {
        $seller = User::factory()->create();
        $listing = $this->listingFor($seller);
        $listing->forceFill([
            'follow_up_sent_at' => now()->subDay(),
            'follow_up_response' => 'sold',
            'next_follow_up_at' => now()->addDays(30),
        ])->saveQuietly();

        $response = $this->withHeaders($this->authHeaders($seller))
            ->getJson('/api/listings/pending-follow-up');

        $response->assertOk()->assertJsonPath('data', null);
    }

    public function test_ignores_another_sellers_listing(): void
    {
        $seller = User::factory()->create();
        $other = User::factory()->create();
        $listing = $this->listingFor($other);
        $listing->forceFill(['next_follow_up_at' => now()->subMinute()])->saveQuietly();

        $response = $this->withHeaders($this->authHeaders($seller))
            ->getJson('/api/listings/pending-follow-up');

        $response->assertOk()->assertJsonPath('data', null);
    }
}
