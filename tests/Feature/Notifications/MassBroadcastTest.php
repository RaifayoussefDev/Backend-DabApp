<?php

namespace Tests\Feature\Notifications;

use App\Jobs\GuestMassNotificationJob;
use App\Jobs\MassNotificationJob;
use App\Mail\NotificationMail;
use App\Models\GuestNotificationToken;
use App\Models\Notification;
use App\Models\NotificationBatch;
use App\Models\NotificationLog;
use App\Models\NotificationPreference;
use App\Models\NotificationToken;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Admin broadcast (mass-send) at scale: the job must reach every eligible
 * recipient exactly once, in a handful of batched FCM calls, and leave correct
 * aggregate counters on the NotificationBatch row.
 */
class MassBroadcastTest extends TestCase
{
    use DatabaseTransactions;

    private FakeFirebaseService $firebase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->firebase = new FakeFirebaseService();
        $this->app->instance(FirebaseService::class, $this->firebase);

        Mail::fake();
    }

    private function pref(User $user, bool $push, bool $email): void
    {
        NotificationPreference::create([
            'user_id'             => $user->id,
            'push_enabled'        => $push,
            'email_enabled'       => $email,
            'quiet_hours_enabled' => false,
            'admin_custom'        => true,
        ]);
    }

    private function token(User $user, string $fcm, string $type = 'android'): NotificationToken
    {
        return NotificationToken::create([
            'user_id'      => $user->id,
            'device_type'  => $type,
            'device_name'  => $type . '-' . $fcm,
            'device_id'    => 'dev-' . $fcm,
            'fcm_token'    => $fcm,
            'is_active'    => true,
            'last_used_at' => now(),
        ]);
    }

    private function batch(array $overrides = []): NotificationBatch
    {
        return NotificationBatch::create(array_merge([
            'title_en'       => 'Hello',
            'body_en'        => 'World',
            'type'           => 'info',
            'audience'       => 'users',
            'channels'       => ['push'],
            'filters'        => [],
            'guest_filters'  => [],
            'total_targeted' => 0,
            'status'         => 'pending',
        ], $overrides));
    }

    private function content(): array
    {
        return ['title_en' => 'Hello', 'body_en' => 'World', 'type' => 'info'];
    }

    public function test_push_broadcast_batches_sends_and_writes_one_notification_per_user(): void
    {
        $a = User::factory()->create(['language' => 'en']); // 2 valid tokens -> reached
        $b = User::factory()->create();                     // 1 invalid token -> not reached
        $c = User::factory()->create();                     // push disabled    -> not reached
        $d = User::factory()->create();                     // no preference    -> not reached

        $this->pref($a, push: true, email: false);
        $this->pref($b, push: true, email: false);
        $this->pref($c, push: false, email: false);
        // $d: intentionally no preference row

        $this->token($a, 'tok-a1', 'android');
        $this->token($a, 'tok-a2', 'ios');
        $bToken = $this->token($b, 'tok-b-INVALID');
        $this->token($c, 'tok-c1');
        $this->token($d, 'tok-d1');

        $batch = $this->batch(['filters' => ['user_ids' => [$a->id, $b->id, $c->id, $d->id]]]);

        MassNotificationJob::dispatchSync($batch->id, ['user_ids' => [$a->id, $b->id, $c->id, $d->id]], $this->content(), ['push'], null);

        // One batched FCM call for the single chunk — not one per user/token.
        $this->assertCount(1, $this->firebase->calls);
        $this->assertCount(3, $this->firebase->calls[0], 'push items = A(2) + B(1); C and D excluded');

        // In-app Notification row for every targeted user, regardless of push eligibility.
        $this->assertSame(4, Notification::where('batch_id', $batch->id)->count());

        // Push logs only for the users a push was attempted for.
        $notifIds = Notification::where('batch_id', $batch->id)->pluck('id');
        $this->assertSame(3, NotificationLog::whereIn('notification_id', $notifIds)->where('channel', 'push')->count());
        $this->assertSame(2, NotificationLog::whereIn('notification_id', $notifIds)->where('status', 'sent')->count());

        // Invalid token deactivated.
        $this->assertFalse($bToken->fresh()->is_active);

        // A's notification marked push_sent; B's not.
        $this->assertTrue(Notification::where('batch_id', $batch->id)->where('user_id', $a->id)->value('push_sent'));
        $this->assertFalse((bool) Notification::where('batch_id', $batch->id)->where('user_id', $b->id)->value('push_sent'));

        $batch->refresh();
        $this->assertSame('completed', $batch->status);
        $this->assertSame(4, $batch->total_targeted);
        $this->assertSame(1, $batch->sent_count);   // only A reached
        $this->assertSame(3, $batch->failed_count); // B, C, D
        $this->assertNotNull($batch->completed_at);
    }

    public function test_email_channel_is_queued_not_sent_inline(): void
    {
        $e = User::factory()->create();
        $this->pref($e, push: false, email: true);

        $batch = $this->batch([
            'channels' => ['push', 'email'],
            'filters'  => ['user_ids' => [$e->id]],
        ]);

        MassNotificationJob::dispatchSync($batch->id, ['user_ids' => [$e->id]], $this->content(), ['push', 'email'], null);

        Mail::assertQueued(NotificationMail::class, 1);

        $batch->refresh();
        $this->assertSame(1, $batch->sent_count);   // reached via email
        $this->assertSame(0, $batch->failed_count);
        $this->assertSame('completed', $batch->status);
    }

    public function test_guest_broadcast_skips_tokens_owned_by_a_registered_user(): void
    {
        $marker = 'TEST-BROADCAST-9.9.9';

        $g1 = GuestNotificationToken::create([
            'device_id' => 'g1', 'fcm_token' => 'guest-1', 'device_type' => 'android',
            'app_version' => $marker, 'locale' => 'en', 'is_active' => true, 'last_active_at' => now(),
        ]);
        $shared = GuestNotificationToken::create([
            'device_id' => 'g2', 'fcm_token' => 'guest-shared', 'device_type' => 'android',
            'app_version' => $marker, 'locale' => 'en', 'is_active' => true, 'last_active_at' => now(),
        ]);
        $g3 = GuestNotificationToken::create([
            'device_id' => 'g3', 'fcm_token' => 'guest-3-INVALID', 'device_type' => 'android',
            'app_version' => $marker, 'locale' => 'ar', 'is_active' => true, 'last_active_at' => now(),
        ]);

        // A logged-in user already holds the "shared" token — the guest copy must be skipped.
        $registered = User::factory()->create();
        $this->token($registered, 'guest-shared');

        $filters = ['app_version' => $marker];
        $batch = $this->batch(['audience' => 'guests', 'guest_filters' => $filters]);

        GuestMassNotificationJob::dispatchSync($batch->id, $filters, $this->content());

        $this->assertCount(1, $this->firebase->calls);
        $tokensSent = array_column($this->firebase->calls[0], 'token');
        sort($tokensSent);
        $this->assertSame(['guest-1', 'guest-3-INVALID'], $tokensSent);

        $this->assertNotNull($g1->fresh()->last_notified_at);
        $this->assertFalse($g3->fresh()->is_active);        // invalid -> deactivated
        $this->assertTrue($shared->fresh()->is_active);     // skipped, untouched

        $batch->refresh();
        $this->assertSame('completed', $batch->status);
        $this->assertSame(1, $batch->sent_count);   // guest-1
        $this->assertSame(1, $batch->failed_count); // guest-3-INVALID
    }

    public function test_failed_job_marks_batch_failed_not_stuck_processing(): void
    {
        $batch = $this->batch();

        (new MassNotificationJob($batch->id, [], $this->content(), ['push'], null))
            ->failed(new \RuntimeException('boom'));

        $this->assertSame('failed', $batch->fresh()->status);
    }
}

/**
 * Records every sendBatch() call and "delivers" every token except those whose
 * string contains INVALID (reported back as an invalid token).
 */
class FakeFirebaseService extends FirebaseService
{
    /** @var array<int,array> one entry per sendBatch() call */
    public array $calls = [];

    public function sendBatch(array $items): array
    {
        $this->calls[] = $items;

        $out = ['sent' => 0, 'failed' => 0, 'invalid_tokens' => [], 'results' => []];

        foreach ($items as $item) {
            if (str_contains($item['token'], 'INVALID')) {
                $out['results'][$item['token']] = false;
                $out['failed']++;
                $out['invalid_tokens'][] = $item['token'];
            } else {
                $out['results'][$item['token']] = true;
                $out['sent']++;
            }
        }

        return $out;
    }
}
