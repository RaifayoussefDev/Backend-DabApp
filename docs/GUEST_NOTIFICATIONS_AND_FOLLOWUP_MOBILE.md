# Guest notifications + recurring "did it sell?" — mobile integration

_Backend ready. This is everything the mobile app needs._

---

## 1. Deploy / DB (backend ops)

### Migrations to run
`php artisan migrate --force` — 6 migrations (`2026_12_28_000000` → `000005`):

| Migration | Adds |
|---|---|
| `000000` | table `guest_notification_tokens` |
| `000001` | `views.device_id` |
| `000002` | `notification_batches.audience`, `.guest_filters` |
| `000003` | `listings.follow_up_source`, `.follow_up_set_by` |
| `000004` | `notification_batches.action_url` |
| `000005` | `listings.follow_up_count`, `.next_follow_up_at` (+ staggered backfill) |

The `listing_follow_up` row in `notification_templates` must exist (seeded long ago by
`2026_08_26_120100`). No new template was added — the recurring reminder reuses it.

### Deploy steps (SSH)
```bash
php artisan down
git pull origin <branch>
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache
php artisan queue:restart          # REQUIRED — new job classes
php artisan up
```
Docker: `docker compose pull && docker compose up -d` (entrypoint runs migrate), then
`docker compose exec app php artisan queue:restart`.

### Must be running
- **Queue worker** — `queue:work` (supervisord `queue:work` program is already configured).
  Without it, **nothing sends**.
- **Scheduler** — `schedule:work` (Docker) or the `* * * * * php artisan schedule:run` crontab.
  Drives the daily follow-up sweep at 18:00.

### Manual test after deploy
```bash
php artisan listings:send-follow-up      # queues the sweep
# worker picks it up; or force it:
php artisan queue:work --once
```
Force one listing to be due:
```php
$l = App\Models\Listing::where('status','published')->first();
$l->update(['next_follow_up_at' => now()->subMinute()]);
App\Jobs\SendListingFollowUps::dispatch();
```

### Scale behaviour (≈10k recipients)
- Sweep is **queued** (never blocks the scheduler), **chunked 200**, **capped at 3000
  sends/run** (`SendListingFollowUps::DAILY_CAP`). Overflow carries to the next daily run.
- Backfill is **staggered over 21 days** (`GREATEST(natural date, now + id%21 days)`), so the
  existing backlog does not all fire on day 1.
- Old listings get **1 reminder then +30 days** — no catch-up burst.
- Admin broadcasts (`MassNotificationJob` / `GuestMassNotificationJob`) already chunk 200 and
  run on the queue — the HTTP call returns instantly, sending happens in the background.

---

## 2. What the app must do

### 2.1 Register the guest push token — on every launch **while logged out**
```
POST /api/guest/notification-tokens            (no auth header)

{
  "device_id":   "<stable per-install id>",              // REQUIRED, the upsert key
  "fcm_token":   "<current FCM token>",                   // REQUIRED
  "device_type": "ios" | "android" | "huawei" | "web",   // REQUIRED
  "app_version": "1.4.0",       // optional — version targeting
  "locale":      "ar" | "en",   // optional — decides notification language
  "country_id":  12,            // optional
  "city_id":     50             // optional
}

→ 200 / 201  { "success": true, "message": "...", "data": { "id", "device_type", "is_active" } }
```
- `device_id` **must be stable** across sessions (OS install id / secure-storage value). Not a
  fresh UUID each launch.
- Idempotent — call on every launch, and again whenever the FCM token refreshes.

### 2.2 Send the device id on listing views
Add header on `GET /api/listings/{id}` (and the guest-mode listing routes):
```
X-Device-Id: <same device_id>
```

### 2.3 On register / login / OTP verify
Add to the request body:
```json
{ "...": "...", "device_id": "<same device_id>" }
```
Backend deactivates the guest token so the user won't get duplicate pushes. The app keeps
registering the authenticated token via `POST /api/notification-tokens` (JWT) as today.

### 2.4 Opt-out / logout / uninstall (recommended)
```
DELETE /api/guest/notification-tokens/{device_id}
```

---

## 3. Notification payloads (FCM: `notification` + `data`)

### A. Guest broadcast — `data.audience = "guest"`
`notification.title` / `.body` = admin-typed text (EN or AR by device `locale`).
```json
"data": {
  "type": "promo | news | info",
  "audience": "guest",
  "action_url": "dabapp://listing/123",   // optional, may be absent/empty
  "batch_id": "45",
  "timestamp": "2026-09-03T12:00:00Z"
}
```

### B. Registered-user broadcast
```json
"data": {
  "notification_id": "9812",
  "type": "promo | news | info",
  "action_url": "...",                    // optional
  "batch_id": "45",
  "timestamp": "..."
}
```

### C. Recurring "did it sell?" reminder — `data.type = "listing_follow_up"`
Cadence: **J+7, J+17, J+32, then every 30 days**, while the listing stays `published` and
isn't marked sold.

| | EN | AR |
|---|---|---|
| title | `Did you sell it?` | `هل تم بيع إعلانك؟` |
| body | `How is your listing "<title>" going? Let us know if it sold.` | `كيف حال إعلانك "<title>"؟ أخبرنا إذا تم بيع المنتج.` |

```json
"data": {
  "notification_id": "...",
  "type": "listing_follow_up",
  "entity_type": "App\\Models\\Listing",
  "entity_id": "123",
  "listing_id": "123",
  "listing_title": "Suzuki Hayabusa 2006",
  "reminder_number": "2",          // 1, 2, 3… (how many reminders sent so far)
  "action_url": "...",             // optional
  "timestamp": "..."
}
```

**On tap (type A/B):** if `action_url` present → route to it (deep link / in-app path);
else open home.
**On tap (type C):** open the "did it sell?" bottom sheet for `listing_id`.

---

## 4. "Did it sell?" bottom-sheet endpoints (logged-in seller)

```
GET   /api/listings/pending-follow-up
      → { "data": <listing | null> }        // show the sheet proactively if not null

PATCH /api/listings/{id}/follow-up
      body: {
        "response": "sold" | "not_sold",
        "sale_channel": "dabapp" | "other_platform" | "off_platform",   // REQUIRED if response = "sold"
        "reason_not_sold": "price too high"                              // optional, only for "not_sold"
      }
      → 200 { "message": "...", "data": <listing>, "rejected_sooms_count": <n> }   // for "sold"
      → 200 { "message": "...", "data": <listing> }                                // for "not_sold"

PATCH /api/listings/{id}/follow-up/undo    // undo a mistaken "sold" tap
      → 200 { "message": "...", "data": <listing> }
```

- `"sold"` → listing status flips to `sold`, pending sooms rejected, reminders stop.
- `"not_sold"` → listing stays live, next reminder in 30 days.

---

## 5. QA test endpoint — trigger the push on demand, as many times as needed

**Whitelisted to one account** for now: `sayedalaa447@gmail.com` (user id **83**). Temporary,
remove once mobile QA is done (`ListingFollowUpController::TEST_USER_IDS` + this route).

```
GET  /api/my-ads
     → list your own listings, pick a listing_id to test with

POST /api/listings/{listing_id}/test-follow-up-notification     (auth required, JWT of user 83)
     → 200 {
         "message": "Test follow-up notification sent (schedule untouched — call again anytime).",
         "listing_id": 123,
         "listing_title": "...",
         "push_results": { "total": 1, "sent": 1, "failed": 0 }
       }
     → 403 if not logged in as user 83
     → 404 if the listing isn't yours
```

- Sends the real `listing_follow_up` push (same title/body/data as §3.C) **immediately**.
- **Repeatable** — does NOT touch `follow_up_count` / `next_follow_up_at` / `follow_up_sent_at`,
  so it never desyncs the real J+7/17/32/+30d schedule. Call it 50 times in a row if needed.
- Throttled to 20 requests/minute.

**Suggested test flow:**
1. `GET /api/my-ads` → grab a `listing_id` you own.
2. `POST /api/listings/{listing_id}/test-follow-up-notification` → push should arrive.
3. Tap it → bottom sheet opens for that listing.
4. `PATCH /api/listings/{listing_id}/follow-up` with `sold`/`not_sold` → verify the response +
   that the listing updates.
5. Repeat step 2 anytime to re-test push delivery / deep link handling without waiting for the
   real 30-day cycle.
