# Listing Follow-up — Mobile Integration Guide

This walks through **[`DabApp-Listings-FollowUp.postman_collection.json`](./DabApp-Listings-FollowUp.postman_collection.json)**
phase by phase — import that collection into Postman and follow along side-by-side with this doc.

Backend API for the "Did it sell?" bottom sheet: a **single, one-time** check-in shown to a seller
7 days after they publish a listing. There is **no recurring/escalating schedule** — each listing
gets this exactly once (unless the seller undoes a mistaken "sold" answer, which resumes it).

All endpoints require the standard `Authorization: Bearer {token}` header (JWT), same as every other
authenticated DabApp endpoint. Collection variable `base_url` = `https://be.dabapp.co`.

---

## Before you start: how the sheet gets triggered in the real app

The Postman collection skips this part (it's not an HTTP call), but the app needs both of these:

1. **Push notification** — a daily server job checks every listing published 7+ days ago and, if it
   hasn't had a follow-up sent yet, pushes `type: "listing_follow_up"`:
   ```json
   {
     "notification_id": "1234",
     "type": "listing_follow_up",
     "entity_type": "App\\Models\\Listing",
     "entity_id": "995",
     "listing_id": "995",
     "listing_title": "Yamaha Tenere 2023",
     "timestamp": "2026-08-26T18:00:00+00:00"
   }
   ```
   All values arrive as strings (FCM requirement) — parse `entity_id`/`listing_id` to int. Tapping the
   push should deep-link straight into the bottom sheet for that listing.
2. **In-app check on launch** — if the seller opens the app before the push fires, call the same
   endpoint as Postman step **2-2** below on app launch/resume, and show the sheet immediately if it
   returns a listing.

---

## Phase 1 — Login (`1-1 · POST Seller Login`)

Standard login, nothing follow-up-specific — included only so the collection is runnable standalone.
`POST {{base_url}}/api/login` with `{ "login": "{{seller_email}}", "password": "{{seller_password}}" }`
saves the returned `token` into `{{seller_token}}` (a test script does this for you). Every request
after this one sends it as `Authorization: Bearer {{seller_token}}`.

---

## Phase 2 — Load listings

### `2-1 · GET My Listings (status=published)`
`GET {{base_url}}/api/my-ads?status=published` — the general "all my published listings" endpoint.
Not follow-up-specific; use it to pick real listing IDs to put in the `listing_id` / `listing_id_2`
collection variables before running Phase 3/4.

### `2-2 · GET Pending Follow-up (single listing, not a list)` — **this is Screen 1's data source**
```
GET {{base_url}}/api/listings/pending-follow-up
Authorization: Bearer {{seller_token}}
```
Returns **at most one** listing — the oldest one currently due, or `data: null` — never a list, even
if 2-1 shows several published listings for the same seller. This is what the app calls on launch.

**200, follow-up due:**
```json
{ "data": { "id": 995, "title": "Yamaha Tenere 2023", "status": "published",
            "published_at": "...", "follow_up_responded_at": null, "...": "..." } }
```
**200, nothing due:** `{ "data": null }`
**401:** `{ "message": "Unauthorized. User must be logged in." }`

Mobile logic: `data === null` → show nothing. Otherwise → open the sheet with `data.title` in the
subtitle → **Screen 1: "Did it sold?"** with two buttons, no confirm needed (one tap = answer). A
third, low-emphasis dismiss action ("remind me later" / swipe down) closes the sheet **without**
calling any endpoint — the listing just stays pending and gets offered again next time.

---

## Phase 3 — Seller answers NOT SOLD

### `3-1 · PATCH Follow-up response = not_sold` — **Screen 2B → toast**
```
PATCH {{base_url}}/api/listings/{{listing_id}}/follow-up
Authorization: Bearer {{seller_token}}
Content-Type: application/json

{ "response": "not_sold", "reason_not_sold": "price_issue" }
```
`reason_not_sold` is optional free text — the backend doesn't enforce a fixed enum, so send whichever
key matches the tapped reason button, or the seller's typed text for "other":

| Reason button (Arabic) | value to send |
|---|---|
| استقبلت تواصل لكن ما تم البيع | `received_contact_not_sold` |
| ما جاني أي تواصل | `no_contact_received` |
| السعر يحتاج تعديل | `price_issue` |
| الإعلان يحتاج تحسين | `listing_quality_issue` |
| أنتظر المشتري المناسب | `buyer_waiting` |
| سبب آخر... | free text, optional |

**200:** `{ "message": "Thanks for letting us know. Your listing stays active.", "data": { "status": "published", "reason_not_sold": "price_issue", "...": "..." } }`
`status` stays `published` — nothing changes in the marketplace. Show a short toast and close the
sheet; there's no next screen for this path.

**Error cases** (also covered as their own Postman requests — see Phase 5): 401 not logged in, 404
listing not found, 403 not the owner, 422 `{"message": "This listing is no longer active.", "current_status": "..."}` if it already left `published`.

---

## Phase 4 — Seller answers SOLD, and undo

### `4-1 · PATCH Follow-up response = sold (via DabApp)` — **Screen 2A → Screen 3**
```
PATCH {{base_url}}/api/listings/{{listing_id_2}}/follow-up
Authorization: Bearer {{seller_token}}
Content-Type: application/json

{ "response": "sold", "sale_channel": "dabapp" }
```
`sale_channel` is **required** when `response = sold`, one of:

| Sale-channel button (Arabic) | value to send |
|---|---|
| عن طريق DabApp | `dabapp` |
| عن طريق منصة أخرى | `other_platform` |
| شخص تواصل معي خارج المنصة | `off_platform` |

**200:** `{ "message": "Congratulations on the sale!", "data": { "status": "sold", "sale_channel": "dabapp", "...": "..." }, "rejected_sooms_count": 2 }`
`status` flips to `sold`, the listing drops off the marketplace, any pending SOOM/offer requests on it
are auto-rejected (`rejected_sooms_count` is informational, not required in the UI). On success, open
**Screen 3**: a confirmation screen with a primary "Got it, thanks" button (just closes the sheet, no
API call) and a secondary/ghost "Undo — still available" button, which calls the next request.

### `4-2 · PATCH Undo the sold answer ("تراجع — ما زال متاحًا")` — **Screen 3's undo button**
```
PATCH {{base_url}}/api/listings/{{listing_id_2}}/follow-up/undo
Authorization: Bearer {{seller_token}}
```
(no body). Run right after 4-1 in the collection. Only reverses a sale made **through this specific
follow-up flow** (i.e. `follow_up_response = sold`) — it will refuse to touch a listing sold via the
app's regular "mark as sold" action elsewhere. This is a mistake-correction affordance on Screen 3,
not a general "unsell" button.

**200:** `{ "message": "Undone — your listing is active again.", "data": { "status": "published", "sale_channel": null, "follow_up_response": null, "...": "..." } }`
The listing goes back to `published` and becomes immediately eligible for `2-2` again (it never
actually got a real answer). It does **not** restore any SOOM/offers that 4-1 auto-rejected — those
stay rejected. The collection notes: run 4-1 again afterwards if you want `listing_id_2` back in the
`sold` state for further testing.

**422 if not eligible:** `{ "message": "This listing was not sold via the follow-up and cannot be undone here.", "current_status": "..." }` — see Phase 5-7.

---

## Phase 5 — Edge cases

Each of these is its own request in the collection, run any time after Phase 1 login:

| # | Request | Expected | What it proves |
|---|---|---|---|
| 5-1 | `PATCH .../follow-up` `{"response":"sold"}` (no `sale_channel`) | 422 | `sale_channel` is required when `response=sold` |
| 5-2 | `PATCH .../follow-up` `{"response":"maybe"}` | 422 | `response` only accepts `sold` / `not_sold` |
| 5-3 | `PATCH .../follow-up` on `listing_id_2` after 4-1 | 422, `current_status: "sold"` | can't answer a listing that already left `published` |
| 5-4 | `PATCH .../follow-up` with no `Authorization` header | 401 | auth is required |
| 5-5 | `PATCH .../follow-up` on listing id `999999999` | 404 | unknown listing |
| 5-6 | `PATCH .../follow-up` on `{{other_users_listing_id}}` (fill in manually) | 403 | ownership is enforced |
| 5-7 | `PATCH .../follow-up/undo` on `listing_id` after Phase 3 (answered `not_sold`, never `sold`) | 422 | undo only works on a listing this flow actually marked `sold` |

---

## Quick reference

| Screen | Postman request | Key field |
|---|---|---|
| 1 — main question | `2-2 · GET Pending Follow-up` | — |
| 2A — sale channel | `4-1 · PATCH ... = sold` | `response: "sold"`, `sale_channel` |
| 2B — reason | `3-1 · PATCH ... = not_sold` | `response: "not_sold"`, `reason_not_sold` |
| 3 — undo | `4-2 · PATCH .../undo` | — |
