<?php

namespace Tests\Feature\Notifications;

use App\Models\Listing;
use App\Models\Notification;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Full soom lifecycle with phone-only users (no email): the emails fail, the
 * push/in-app notifications must still go out.
 */
class SoomNotificationTest extends TestCase
{
    use DatabaseTransactions;

    private function as(User $user): static
    {
        // A bearer header isn't enough: the JWT guard keeps the first user across requests in one test
        return $this->actingAs($user, 'api');
    }

    private function sent(User $user, string $type): int
    {
        return Notification::where('user_id', $user->id)->where('type', $type)->count();
    }

    public function test_soom_lifecycle_notifies_each_party(): void
    {
        Mail::fake();

        $seller = User::factory()->create(['email' => null]);
        $buyerA = User::factory()->create(['email' => null]);
        $buyerB = User::factory()->create(['email' => null]);

        $listing = Listing::create([
            'seller_id' => $seller->id,
            'status' => 'published',
            'step' => 3,
            'title' => 'Yamaha R1',
            'description' => 'A description long enough for validation.',
            'price' => 5000,
            'category_id' => 1,
            'auction_enabled' => true,
            'allow_submission' => true,
            'minimum_bid' => 1000,
        ]);

        // A offers → seller notified
        $this->as($buyerA)->postJson("/api/listings/{$listing->id}/soom", ['amount' => 1000])->assertCreated();
        $this->assertSame(1, $this->sent($seller, 'soom_new_negotiation'));

        // B outbids → seller notified, A outbid
        $this->as($buyerB)->postJson("/api/listings/{$listing->id}/soom", ['amount' => 1050])->assertCreated();
        $this->assertSame(2, $this->sent($seller, 'soom_new_negotiation'));
        $this->assertSame(1, $this->sent($buyerA, 'soom_outbid'));

        // A raises their offer → seller notified, B outbid
        $subA = Submission::where('listing_id', $listing->id)->where('user_id', $buyerA->id)->firstOrFail();
        $subB = Submission::where('listing_id', $listing->id)->where('user_id', $buyerB->id)->firstOrFail();
        $this->as($buyerA)->putJson("/api/submissions/{$subA->id}/edit", ['amount' => 1100])->assertOk();
        $this->assertSame(3, $this->sent($seller, 'soom_new_negotiation'));
        $this->assertSame(1, $this->sent($buyerB, 'soom_outbid'));

        // Seller rejects B, accepts A, validates the sale
        $this->as($seller)->patchJson("/api/submissions/{$subB->id}/reject", ['reason' => 'Too low'])->assertOk();
        $this->as($seller)->patchJson("/api/submissions/{$subA->id}/accept")->assertOk();
        $this->as($seller)->postJson("/api/submissions/{$subA->id}/validate-sale")->assertOk();

        $this->assertSame(1, $this->sent($buyerB, 'soom_rejected'));
        $this->assertSame(1, $this->sent($buyerA, 'soom_accepted'));
        $this->assertSame(1, $this->sent($buyerA, 'soom_won'));
        $this->assertSame(1, $this->sent($seller, 'listing_sold'));

        // soom_won used to render an empty title (it was given the Submission, not the Listing)
        $won = Notification::where('user_id', $buyerA->id)->where('type', 'soom_won')->first();
        $this->assertStringContainsString('Yamaha R1', $won->title . ' ' . $won->message);

        // Deep links: buyer back to the listing, seller to the soom box
        $this->assertSame("/listings/{$listing->id}", $won->action_url);
        $this->assertSame('/profile/soom-box', Notification::where('user_id', $seller->id)->where('type', 'soom_new_negotiation')->value('action_url'));
    }

    public function test_posting_a_listing_notifies_once_per_stage(): void
    {
        Mail::fake();
        $seller = User::factory()->create(['email' => null]);

        // Step 1 creates the draft, step 2 updates it — one "saved" notification only
        $res = $this->as($seller)->postJson('/api/listings', ['step' => 1, 'title' => 'Honda CBR', 'category_id' => 1]);
        $res->assertCreated();
        $listingId = $res->json('listing_id');
        $this->as($seller)->postJson('/api/listings', ['step' => 2, 'listing_id' => $listingId, 'title' => 'Honda CBR'])->assertCreated();

        $this->assertSame(1, $this->sent($seller, 'listing_created'));
        $this->assertSame(0, $this->sent($seller, 'listing_updated'));

        // Going live (payment / free path) → "now live"
        Listing::find($listingId)->update(['status' => 'published']);
        $this->assertSame(1, $this->sent($seller, 'listing_approved'));
    }

    public function test_new_soom_emails_the_seller_once(): void
    {
        Mail::fake();
        $seller = User::factory()->create(['email' => 'seller_' . uniqid() . '@test.local']);
        $buyer = User::factory()->create(['email' => null]);
        // Without events: going live emails "listing approved", which isn't what this test is about
        $listing = Listing::withoutEvents(fn () => Listing::create([
            'seller_id' => $seller->id, 'status' => 'published', 'step' => 3, 'title' => 'Shark helmet',
            'description' => 'A description long enough for validation.', 'price' => 900, 'category_id' => 1,
            'auction_enabled' => true, 'allow_submission' => true, 'minimum_bid' => 100,
        ]));

        $this->as($buyer)->postJson("/api/listings/{$listing->id}/soom", ['amount' => 100])->assertCreated();

        // The dedicated SoomCreatedMail only — not a second generic NotificationMail
        Mail::assertSent(\App\Mail\SoomCreatedMail::class, 1);
        Mail::assertNotSent(\App\Mail\NotificationMail::class);
        $this->assertSame(1, $this->sent($seller, 'soom_new_negotiation'));
    }

    /** Amounts use the listing's own currency (its country), not a hard-coded one. */
    public function test_soom_amounts_use_the_listing_currency(): void
    {
        $countries = \DB::table('currency_exchange_rates')->whereIn('currency_code', ['SAR', 'AED'])->pluck('country_id', 'currency_code');
        $this->assertCount(2, $countries, 'needs SAR and AED rows in currency_exchange_rates');

        foreach (['SAR' => 'ريال', 'AED' => 'درهم'] as $code => $arabic) {
            $this->app['auth']->forgetGuards();
            $sellerEn = User::factory()->create(['email' => null, 'language' => 'en']);
            $sellerAr = User::factory()->create(['email' => null, 'language' => 'ar']);
            $buyer = User::factory()->create(['email' => null]);

            foreach ([$sellerEn, $sellerAr] as $seller) {
                $listing = Listing::withoutEvents(fn () => Listing::create([
                    'seller_id' => $seller->id, 'status' => 'published', 'step' => 3, 'title' => "Bike {$code}",
                    'description' => 'A description long enough for validation.', 'price' => 900, 'category_id' => 1,
                    'auction_enabled' => true, 'allow_submission' => true, 'minimum_bid' => 100,
                    'country_id' => $countries[$code],
                ]));
                $this->as($buyer)->postJson("/api/listings/{$listing->id}/soom", ['amount' => 100])->assertCreated();

                $message = Notification::where('user_id', $seller->id)->where('type', 'soom_new_negotiation')->value('message');
                $expected = $seller->language === 'ar' ? $arabic : $code;
                $this->assertStringContainsString("100 {$expected}", $message, "{$code} / {$seller->language}");

                // The soom email shows the same currency
                $html = (new \App\Mail\SoomCreatedMail(Submission::where('listing_id', $listing->id)->first(), $listing, $buyer))->render();
                $this->assertStringContainsString("100.00 {$code}", $html);
                $this->assertStringContainsString("100.00 {$arabic}", $html);
            }
        }
    }
}
