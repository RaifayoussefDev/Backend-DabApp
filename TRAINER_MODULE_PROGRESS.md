# Trainer Module — Progress Report
**Project:** DabApp Backend (Laravel)
**Date:** 2026-06-12
**Status:** Core development complete — notifications & refund pending

---

## What Was Built

### 1. Database — 15 migrations

| File | Table | Purpose |
|------|-------|---------|
| `2026_06_12_182209_drop_instructor_tables` | DROP | Removes `instructor_locations` + `riding_instructors` (clean break) |
| `2026_06_12_182527_create_trainers_table` | `trainers` | Main trainer profile (user_id FK, specialty, price, status, rating) |
| `2026_06_12_182529_create_trainer_locations_table` | `trainer_locations` | Training locations per trainer |
| `2026_06_12_182530_create_trainer_schedules_table` | `trainer_schedules` | Weekly availability per day_of_week |
| `2026_06_12_182532_create_trainer_bookings_table` | `trainer_bookings` | Client bookings with status workflow |
| `2026_06_12_182534_create_trainer_payments_table` | `trainer_payments` | PayTabs transactions (separate from existing `payments`) |
| `2026_06_12_182540_create_payment_splits_table` | `payment_splits` | Commission snapshot per booking (DabApp % + trainer %) |
| `2026_06_12_182542_create_trainer_payouts_table` | `trainer_payouts` | Manual payout requests with bank info + proof upload |
| `2026_06_12_182544_create_trainer_reviews_table` | `trainer_reviews` | One review per completed booking (admin moderated) |
| `2026_06_12_182546_create_trainer_comments_table` | `trainer_comments` | Nested comments on trainer profiles (parent_id self-ref) |
| `2026_06_12_182548_create_trainer_favorites_table` | `trainer_favorites` | User favorites (unique: trainer_id + user_id) |
| `2026_06_12_182551_create_trainer_likes_table` | `trainer_likes` | User likes (unique: trainer_id + user_id) |
| `2026_06_12_182553_create_commission_settings_table` | `commission_settings` | Global or per-trainer commission rates with effective dates |
| `2026_06_12_182555_create_commission_history_table` | `commission_history` | Full audit trail of every commission rate change |

> **Note:** `notification_templates` already existed — duplicate migration was NOT created.

**Run migrations:**
```bash
php artisan migrate
```

---

### 2. Models — 13 files (`app/Models/`)

| Model | Key Feature |
|-------|------------|
| `Trainer` | `getEffectiveCommissionPercentage()` — checks trainer override first, falls back to global |
| `TrainerBooking` | `hasConflict()`, `canBeReviewed()`, `canBeCancelled()` helpers |
| `TrainerPayment` | PayTabs transaction linked to booking |
| `PaymentSplit` | Static `calculate()` method — returns commission_amount + trainer_amount |
| `TrainerPayout` | `transfer_proof_url` accessor |
| `TrainerReview` | One per booking (unique constraint) |
| `TrainerComment` | `replies()` self-reference for nested threads |
| `TrainerSchedule` | `day_name` / `day_name_ar` appended accessors (0=Sunday…6=Saturday) |
| `TrainerLocation` | Bilingual `localized_name` accessor |
| `TrainerFavorite` | Simple pivot |
| `TrainerLike` | Simple pivot |
| `CommissionSetting` | `active()`, `global()`, `forTrainer(int $id)` scopes |
| `CommissionHistory` | Audit log of rate changes |

---

### 3. Controllers — 13 files (`app/Http/Controllers/Trainer/`)

#### Public & Client

| Controller | Endpoints |
|-----------|-----------|
| `TrainerController` | `index` (list + filters), `show` (full profile), `locations`, `register`, `updateProfile`, `addLocation`, `deleteLocation`, `myProfile` |
| `TrainerBookingController` | `availability` (slot generator), `book` (PayTabs init), `paymentCallback` (webhook), `myBookings`, `cancel`, `mySessions`, `startSession`, `completeSession` |
| `TrainerScheduleController` | `index` (get week schedule), `upsert` (set/update schedule) |
| `TrainerReviewController` | `index` (with rating distribution), `store` (after completed booking only) |
| `TrainerCommentController` | `index` (nested with replies), `store` (validates parent), `destroy` (own comment) |
| `TrainerLikeController` | `toggle` (increments/decrements `likes_count`) |
| `TrainerFavoriteController` | `toggle`, `myFavorites` (paginated) |

#### Admin Panel

| Controller | Endpoints |
|-----------|-----------|
| `AdminTrainerStatsController` | `dashboard` (full overview stats), `revenue` (breakdown by period) |
| `AdminTrainerController` | `index`, `show` (full profile + stats), `approve`, `reject`, `suspend`, `reactivate`, `destroy`, `reviews`, `approveReview`, `deleteReview`, `comments`, `approveComment`, `deleteComment` |
| `AdminTrainerBookingController` | `index` (all bookings + filters), `show` (full detail), `cancel` (force), `confirm` (manual) |
| `AdminTrainerPaymentController` | `index` (all PayTabs txns), `show` (with raw PayTabs response) |
| `AdminCommissionController` | `index`, `setGlobal`, `setTrainerRate`, `removeTrainerRate`, `history` |
| `AdminPayoutController` | `index` (with summary totals), `show`, `approve`, `reject`, `markPaid` (+ proof upload) |

---

### 4. Routes — `routes/api.php`

| Group | Auth | Prefix |
|-------|------|--------|
| Public browse | None | `/api/trainers/*` |
| PayTabs webhook | None | `POST /api/trainer/payments/callback` |
| Client actions (booking, social) | `auth:api` | `/api/trainer/*` |
| Provider self-management | `auth:api` | `/api/trainer/*` |
| Admin full panel | `auth:api` | `/api/admin/*` |

**Full admin route list:**

```
GET    /api/admin/trainer-stats/dashboard
GET    /api/admin/trainer-stats/revenue?year=&month=&date_from=&date_to=

GET    /api/admin/trainers
GET    /api/admin/trainers/{id}
POST   /api/admin/trainers/{id}/approve
POST   /api/admin/trainers/{id}/reject
POST   /api/admin/trainers/{id}/suspend
POST   /api/admin/trainers/{id}/reactivate
DELETE /api/admin/trainers/{id}

GET    /api/admin/trainer-reviews?approved=0
POST   /api/admin/trainer-reviews/{id}/approve
DELETE /api/admin/trainer-reviews/{id}

GET    /api/admin/trainer-comments?approved=0
POST   /api/admin/trainer-comments/{id}/approve
DELETE /api/admin/trainer-comments/{id}

GET    /api/admin/trainer-bookings?status=&trainer_id=&user_id=&date_from=&date_to=
GET    /api/admin/trainer-bookings/{id}
POST   /api/admin/trainer-bookings/{id}/cancel
POST   /api/admin/trainer-bookings/{id}/confirm

GET    /api/admin/trainer-payments?payment_status=&date_from=&date_to=
GET    /api/admin/trainer-payments/{id}

GET    /api/admin/commission
POST   /api/admin/commission/global
GET    /api/admin/commission/history
POST   /api/admin/commission/trainer/{trainerId}
DELETE /api/admin/commission/trainer/{trainerId}

GET    /api/admin/payouts?status=&trainer_id=
GET    /api/admin/payouts/{id}
POST   /api/admin/payouts/{id}/approve
POST   /api/admin/payouts/{id}/reject
POST   /api/admin/payouts/{id}/mark-paid   (multipart — transfer_ref + optional file)
```

---

### 5. Swagger Documentation

Every endpoint has full `@OA\` annotations including:
- Operation ID, tags, summary, description
- All query params and path params with types + examples
- Request body with required/optional fields and example values
- All possible response codes (200, 400, 404, 422) with typed JSON schemas

**Regenerate Swagger UI:**
```bash
php artisan l5-swagger:generate
```

---

## Architecture Decisions

| Decision | Why |
|----------|-----|
| `trainer_payments` separate from `payments` | Existing `payments` table has listing-specific columns (`listing_id`, `promo_code_id`). Clean isolation. |
| Commission snapshotted in `payment_splits` | Changing global % later never retroactively affects past bookings |
| `payment_id` in `trainer_bookings` is nullable (no FK) | Circular dependency: bookings table created before payments table in migration order |
| Trainer status: `pending → approved / rejected / suspended` | No subscription required — DabApp validates manually before trainer is visible |
| PayTabs `cart_id = "TRAINER_{booking_id}"` | Pattern used in webhook to identify trainer bookings vs other PayTabs flows |
| `rides_instructors` fully dropped | Not a soft delete — clean break, rollback available in migration `down()` |

---

## What's Still Missing (Next Phase)

### High Priority

| # | What | Where |
|---|------|-------|
| 1 | Push notification + email on trainer **approve / reject / suspend / reactivate** | `AdminTrainerController` — marked `// TODO:` |
| 2 | Push notification + email when **session completed** | `TrainerBookingController::completeSession()` |
| 3 | Push notification + email when **review approved** | `AdminTrainerController::approveReview()` |
| 4 | Push notification + email when **payout approved / rejected / marked paid** | `AdminPayoutController` — 3 TODOs |
| 5 | Push notification + email when **booking force-cancelled or confirmed** by admin | `AdminTrainerBookingController` — 2 TODOs |
| 6 | **PayTabs refund** on booking cancellation when `payment_status = paid` | `TrainerBookingController::cancel()` — marked `// TODO:` |

### Medium Priority

| # | What | Notes |
|---|------|-------|
| 7 | Trainer earnings wallet / withdrawal request flow | Currently manual via admin payout |
| 8 | Admin bulk actions (bulk approve trainers, bulk approve reviews) | Nice-to-have for large datasets |
| 9 | Trainer response to reviews (reply) | UX improvement |
| 10 | Export bookings / payouts to CSV/Excel | Already done for other modules — same pattern |

---

## How to Run

```bash
# 1. Apply DB changes (requires MySQL running)
php artisan migrate

# 2. Rebuild Swagger docs
php artisan l5-swagger:generate

# 3. Test in Swagger UI
# Open: http://your-domain/api/documentation
# Look for tags: "Trainer", "Admin - Trainers", "Admin - Trainer Stats",
#                "Admin - Trainer Bookings", "Admin - Trainer Payments",
#                "Admin - Commission", "Admin - Payouts"
```

---

## File Tree (New Files Only)

```
app/
├── Http/Controllers/Trainer/
│   ├── TrainerController.php
│   ├── TrainerBookingController.php
│   ├── TrainerScheduleController.php
│   ├── TrainerReviewController.php
│   ├── TrainerCommentController.php
│   ├── TrainerLikeController.php
│   ├── TrainerFavoriteController.php
│   ├── AdminTrainerController.php          ← extended with show, reactivate, destroy, comments, deleteComment
│   ├── AdminTrainerStatsController.php     ← NEW: dashboard + revenue breakdown
│   ├── AdminTrainerBookingController.php   ← NEW: list, show, cancel, confirm
│   ├── AdminTrainerPaymentController.php   ← NEW: list, show
│   ├── AdminCommissionController.php       ← extended with removeTrainerRate
│   └── AdminPayoutController.php           ← extended with show, reject
├── Models/
│   ├── Trainer.php
│   ├── TrainerLocation.php
│   ├── TrainerSchedule.php
│   ├── TrainerBooking.php
│   ├── TrainerPayment.php
│   ├── PaymentSplit.php
│   ├── TrainerPayout.php
│   ├── TrainerReview.php
│   ├── TrainerComment.php
│   ├── TrainerFavorite.php
│   ├── TrainerLike.php
│   ├── CommissionSetting.php
│   └── CommissionHistory.php
database/migrations/
│   ├── 2026_06_12_182209_drop_instructor_tables.php
│   ├── 2026_06_12_182527_create_trainers_table.php
│   ├── 2026_06_12_182529_create_trainer_locations_table.php
│   ├── 2026_06_12_182530_create_trainer_schedules_table.php
│   ├── 2026_06_12_182532_create_trainer_bookings_table.php
│   ├── 2026_06_12_182534_create_trainer_payments_table.php
│   ├── 2026_06_12_182540_create_payment_splits_table.php
│   ├── 2026_06_12_182542_create_trainer_payouts_table.php
│   ├── 2026_06_12_182544_create_trainer_reviews_table.php
│   ├── 2026_06_12_182546_create_trainer_comments_table.php
│   ├── 2026_06_12_182548_create_trainer_favorites_table.php
│   ├── 2026_06_12_182551_create_trainer_likes_table.php
│   ├── 2026_06_12_182553_create_commission_settings_table.php
│   └── 2026_06_12_182555_create_commission_history_table.php
```
