<?php

namespace Tests\Feature\Listing;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * `listings:backfill-follow-up-schedule` — arms next_follow_up_at for
 * published listings the recurring "did it sell?" schedule never picked up
 * (Listing::booted() only fills it on save, so a row untouched since before
 * the feature existed stays NULL forever otherwise).
 */
class BackfillFollowUpScheduleTest extends TestCase
{
    use DatabaseTransactions;

    private function publishedListing(array $overrides = []): Listing
    {
        $seller = User::factory()->create(['is_active' => true]);

        $listing = Listing::create(array_merge([
            'seller_id' => $seller->id,
            'status' => 'published',
            'step' => 3,
            'title' => 'A valid listing title',
            'description' => 'A description long enough for validation.',
            'price' => 100,
            'category_id' => 1,
            'auction_enabled' => false,
            'published_at' => now(),
        ], $overrides));

        return $listing;
    }

    /** Simulate a listing that predates the feature: published, but next_follow_up_at never armed. */
    private function unarmed(Listing $listing): Listing
    {
        $listing->forceFill(['next_follow_up_at' => null])->saveQuietly();
        return $listing->fresh();
    }

    public function test_arms_old_backlog_listings_without_firing_them_immediately(): void
    {
        $old = $this->unarmed($this->publishedListing(['published_at' => now()->subDays(40)]));
        $recent = $this->unarmed($this->publishedListing(['published_at' => now()->subDays(2)]));

        $this->artisan('listings:backfill-follow-up-schedule')
            ->expectsOutputToContain('Armed 2 listing(s).')
            ->assertExitCode(0);

        $old->refresh();
        $recent->refresh();

        // Both armed...
        $this->assertNotNull($old->next_follow_up_at);
        $this->assertNotNull($recent->next_follow_up_at);

        // ...but neither is due right this second — the old one's overdue J+7 date
        // (day 33) is floored to +30 days out, not "now".
        $this->assertTrue($old->next_follow_up_at->isFuture());
        $this->assertTrue($recent->next_follow_up_at->isFuture());

        // Recent listing keeps its natural J+7 date (published_at + 7d).
        $this->assertTrue($recent->next_follow_up_at->isSameDay($recent->published_at->copy()->addDays(7)));
    }

    public function test_skips_sold_draft_and_already_armed_listings(): void
    {
        $sold = $this->unarmed($this->publishedListing([
            'published_at' => now()->subDays(40),
            'follow_up_response' => 'sold',
        ]));
        $draft = $this->publishedListing(['status' => 'draft', 'published_at' => null]);
        $draft->forceFill(['next_follow_up_at' => null])->saveQuietly();

        $alreadyArmedAt = now()->addDays(5)->startOfSecond(); // avoid a sub-second DB-roundtrip mismatch
        $alreadyArmed = $this->publishedListing(['published_at' => now()->subDays(1)]);
        $alreadyArmed->forceFill(['next_follow_up_at' => $alreadyArmedAt])->saveQuietly();

        $this->artisan('listings:backfill-follow-up-schedule')
            ->expectsOutputToContain('Nothing to backfill')
            ->assertExitCode(0);

        $this->assertNull($sold->fresh()->next_follow_up_at);
        $this->assertNull($draft->fresh()->next_follow_up_at);
        $this->assertTrue($alreadyArmed->fresh()->next_follow_up_at->equalTo($alreadyArmedAt));
    }

    public function test_dry_run_reports_without_writing(): void
    {
        $old = $this->unarmed($this->publishedListing(['published_at' => now()->subDays(40)]));

        $this->artisan('listings:backfill-follow-up-schedule --dry-run')
            ->expectsOutputToContain('1 published listing(s) would be armed')
            ->assertExitCode(0);

        $this->assertNull($old->fresh()->next_follow_up_at);
    }
}
