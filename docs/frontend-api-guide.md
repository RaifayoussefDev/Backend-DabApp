# DabApp — Frontend API Guide
> Last updated: 2026-05-30 · Base URL: `http://localhost:8000` (dev) · All endpoints require `Authorization: Bearer {token}` unless marked **public**

---

## Table of Contents
1. [Authentication](#1-authentication)
2. [Assist — Full Flow](#2-assist--full-flow)
   - [Seeker: Create Request](#21-seeker--create-request)
   - [Seeker: View Proposals (with Helper GPS)](#22-seeker--view-proposals-with-helper-gps)
   - [Seeker: Accept Proposal](#23-seeker--accept-proposal)
   - [Helper: Submit Proposal](#24-helper--submit-proposal)
   - [Mission Status Flow](#25-mission-status-flow)
   - [QR Completion](#26-qr-completion)
3. [Tow Service](#3-tow-service)
   - [Get Tow Types](#31-get-tow-types-public)
   - [Calculate Price](#32-calculate-price)
   - [Available Providers](#33-available-providers)
   - [Create Tow Request](#34-create-tow-request)
4. [Push Notification Keys](#4-push-notification-keys)
5. [Known Limitations / TODOs](#5-known-limitations--todos)

---

## 1. Authentication

### Register
```
POST /api/register
```
```json
{
  "first_name": "Raifa",
  "last_name": "Seeker",
  "email": "user@example.com",
  "phone": "+212600000001",
  "password": "password123",
  "password_confirmation": "password123"
}
```
**Response** → `{ "user_id": 5 }` — save `user_id` for OTP step.

### Verify OTP
```
POST /api/verify-otp
```
```json
{ "user_id": 5, "otp": "123456" }
```

### Login
```
POST /api/login
```
```json
{ "email": "user@example.com", "password": "password123" }
```
**Response** → `{ "token": "eyJ..." }` — save and send as `Authorization: Bearer {token}` on every request.

---

## 2. Assist — Full Flow

### 2.1 Seeker — Create Request

#### Get Expertise Types (for picker UI)
```
GET /api/assist/expertise-types
```
Returns list of expertise types. Use `id` values for the request body.

#### Get Price Config (show price range to user)
```
GET /api/assist/price-config
```
```json
{
  "success": true,
  "data": {
    "price_min": 0,
    "price_max": 150,
    "price_step": 50,
    "valid_prices": [0, 50, 100, 150]
  }
}
```

#### Get My Garage (optional motorcycle picker)
```
GET /api/assist/seeker/garage
```

#### Create Assistance Request
```
POST /api/assist/seeker/request
```
```json
{
  "expertise_type_ids": [1],
  "latitude": 33.5800,
  "longitude": -7.6000,
  "location_label": "Bd Mohammed V, Casablanca",
  "description": "My rear tire is completely flat.",
  "motorcycle_id": null
}
```
**Response 201**
```json
{
  "success": true,
  "data": {
    "request": {
      "id": 12,
      "status": "pending",
      "created_at": "2026-05-30T10:00:00Z"
    }
  }
}
```
> Save `data.request.id` as `request_id`.

---

### 2.2 Seeker — View Proposals (with Helper GPS)

```
GET /api/assist/seeker/request/{request_id}/proposals
```

**Response 200**
```json
{
  "success": true,
  "data": [
    {
      "id": 7,
      "proposed_price": 100,
      "status": "pending",
      "created_at": "2026-05-30T10:05:00Z",
      "helper": {
        "id": 2,
        "first_name": "Ahmed",
        "last_name": "Al-Rashid",
        "profile_picture": "https://cdn.example.com/avatar.jpg",
        "rating": 4.8,
        "total_assists": 23,
        "latitude": 33.5731,
        "longitude": -7.5898
      }
    }
  ]
}
```

**How to use `latitude` / `longitude` for ETA:**
- These are the helper's **current GPS coordinates** (updated live via `/api/assist/helper/profile/location`)
- Use them with the seeker's coordinates to call Google Maps / Mapbox Distance Matrix API
- If `latitude` or `longitude` is `null` → helper hasn't shared location yet, hide ETA

> Proposals are sorted by `proposed_price ASC`, then `created_at ASC`.

---

### 2.3 Seeker — Accept Proposal

```
POST /api/assist/seeker/request/{request_id}/proposals/{proposal_id}/accept
```
No body needed.

**Response 200**
```json
{
  "success": true,
  "message": "Proposal accepted. Your helper is on the way.",
  "data": {
    "request_id": 12,
    "status": "accepted",
    "accepted_price": 100,
    "helper": {
      "id": 2,
      "first_name": "Ahmed",
      "last_name": "Al-Rashid",
      "profile_picture": "https://cdn.example.com/avatar.jpg",
      "phone": "+212600000002"
    },
    "rejected_count": 2
  }
}
```
> All other pending proposals are **auto-rejected**. Other helpers receive a push notification.

#### Manually Reject a Single Proposal
```
DELETE /api/assist/seeker/request/{request_id}/proposals/{proposal_id}
```

---

### 2.4 Helper — Submit Proposal

```
POST /api/assist/helper/feed/{request_id}/propose
```
```json
{ "proposed_price": 100 }
```
> Price must be a multiple of `price_step` within `[price_min, price_max]`.  
> Invalid price → **400**. Already proposed → **409**.

**Response 201**
```json
{
  "success": true,
  "message": "Proposal submitted. Waiting for the seeker to accept.",
  "data": {
    "id": 7,
    "request_id": 12,
    "proposed_price": 100,
    "status": "pending",
    "created_at": "2026-05-30T10:05:00Z"
  }
}
```

---

### 2.5 Mission Status Flow

Helper updates mission status in order:

| Step | Call | Status set to |
|------|------|---------------|
| 1 | `PATCH /api/assist/helper/mission/{request_id}/status` `{ "status": "en_route" }` | `en_route` |
| 2 | `PATCH /api/assist/helper/mission/{request_id}/status` `{ "status": "arrived" }` | `arrived` — QR token generated |

#### Update Helper GPS (call frequently while on mission)
```
PATCH /api/assist/helper/profile/location
```
```json
{ "latitude": 33.5731, "longitude": -7.5898 }
```

#### Seeker Track Helper
```
GET /api/assist/seeker/request/{request_id}/track
```
Returns helper's current `latitude` / `longitude`. Poll every 10-15 seconds while status is `en_route`.

---

### 2.6 QR Completion

When helper sets status to `arrived`, a `completion_token` is generated (returned in the status update response).

**The helper shows a QR code** encoding this token. **The seeker scans it:**

```
POST /api/assist/seeker/request/{request_id}/verify-qr
```
```json
{ "token": "abc123xyz" }
```
> Wrong token → **400**. Correct token → request moves to `completed`.

#### Rate the Helper
```
POST /api/assist/seeker/request/{request_id}/rate
```
```json
{ "stars": 5, "comment": "Super fast, saved my day!" }
```

---

## 3. Tow Service

> **Use these route prefixes** (ignore `/api/tow/types` — it's a legacy duplicate):
> - Read endpoints: `/api/tow-types`
> - Action endpoints: `/api/tow-service/...`

---

### 3.1 Get Tow Types *(public)*

```
GET /api/tow-types
GET /api/tow-types?is_active=1       ← filter active only
```

**Response 200**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Flatbed Tow",
      "name_ar": "سحب بسطح مستوي",
      "description": "Most secure method for transporting motorcycles",
      "description_ar": "الطريقة الأكثر أماناً لنقل الدراجات النارية",
      "icon": "flatbed-truck",
      "image": "https://example.com/flatbed.jpg",
      "base_price": 50.00,
      "price_per_km": 5.00,
      "is_active": true,
      "order_position": 1
    }
  ],
  "message": "Tow types retrieved successfully"
}
```

#### Get Single Tow Type
```
GET /api/tow-types/{id}
```

---

### 3.2 Calculate Price

Show estimated price **before** the user confirms the booking.

```
POST /api/tow-service/calculate-price
Authorization: Bearer {token}
```

**Option A — by tow type (generic)**
```json
{
  "tow_type_id": 1,
  "pickup_latitude": 24.7136,
  "pickup_longitude": 46.6753,
  "dropoff_latitude": 21.4858,
  "dropoff_longitude": 39.1925,
  "promo_code": "SUMMER2025"
}
```

**Option B — by specific provider service**
```json
{
  "service_id": 10,
  "pickup_latitude": 24.7136,
  "pickup_longitude": 46.6753,
  "dropoff_latitude": 21.4858,
  "dropoff_longitude": 39.1925,
  "pickup_city_id": 1,
  "dropoff_city_id": 2
}
```

**Response 200**
```json
{
  "success": true,
  "data": {
    "tow_type": { "id": 1, "name": "Flatbed Tow", "name_ar": "سحب بسطح مستوي" },
    "pricing_model": "standard",
    "distance_km": 857.23,
    "base_price": 50.00,
    "price_per_km": 5.00,
    "distance_price": 4286.15,
    "subtotal": 4336.15,
    "discount_amount": 0.00,
    "total_price": 4336.15,
    "currency": "SAR",
    "estimated_duration_minutes": 1029
  }
}
```

> `pricing_model` values:
> - `"standard"` — base + distance formula
> - `"fixed_route"` — flat price for city-to-city route
> - `"provider_specific"` — provider's own pricing rules

---

### 3.3 Available Providers

Find tow providers near the user's location. Use this to show the provider cards on the Tow Service screen.

```
GET /api/tow-service/available-providers?latitude=24.7136&longitude=46.6753&radius=20
Authorization: Bearer {token}
```

| Param | Required | Default | Description |
|-------|----------|---------|-------------|
| `latitude` | ✅ | — | User's lat |
| `longitude` | ✅ | — | User's lon |
| `radius` | ❌ | 20 | Search radius in km (max 100) |

**Response 200**
```json
{
  "success": true,
  "count": 3,
  "search_radius_km": 20,
  "data": [
    {
      "id": 5,
      "name": "Fast Riders Moto Club",
      "logo": "https://cdn.example.com/logo.jpg",
      "distance": 4.2,
      "city": { "id": 1, "name": "Riyadh" },
      "services": [...],
      "activeSubscription": { "plan": { "name": "Premium", "priority": 10 } }
    }
  ]
}
```

> Results are sorted by **subscription plan priority first**, then **distance**. Premium providers appear first.

---

### 3.4 Create Tow Request

```
POST /api/tow-service/request
Authorization: Bearer {token}
```

**Option A — by tow type**
```json
{
  "tow_type_id": 1,
  "pickup_location": "King Fahd Road, Riyadh",
  "pickup_latitude": 24.7136,
  "pickup_longitude": 46.6753,
  "dropoff_location": "Jeddah Corniche",
  "dropoff_latitude": 21.4858,
  "dropoff_longitude": 39.1925,
  "notes": "Motorcycle won't start, battery dead",
  "phone": "+966501234567",
  "promo_code": "SUMMER2025"
}
```

**Option B — by specific provider service**
```json
{
  "service_id": 10,
  "pickup_location": "...",
  "pickup_latitude": 24.7136,
  "pickup_longitude": 46.6753,
  "dropoff_location": "...",
  "dropoff_latitude": 21.4858,
  "dropoff_longitude": 39.1925,
  "pickup_city_id": 1,
  "dropoff_city_id": 2
}
```

**Response 201**
```json
{
  "success": true,
  "data": {
    "booking": {
      "id": 42,
      "status": "pending",
      "price": 4336.15,
      "payment_status": "pending",
      "pickup_location": "King Fahd Road, Riyadh",
      "dropoff_location": "Jeddah Corniche",
      "distance_km": 857.23,
      "service": { "id": 3, "name": "Standard Tow" },
      "provider": { "id": 5, "name": "Fast Riders Moto Club" }
    },
    "tow_type": { "id": 1, "name": "Flatbed Tow" },
    "distance_km": 857.23,
    "total_price": 4336.15,
    "payment_required": true
  },
  "message": "Tow service request created successfully. A provider will be assigned shortly."
}
```

> ⚠️ **Payment is not yet handled by the API** — `payment_required: true` means you need to collect payment separately (payment gateway integration pending).

---

## 4. Push Notification Keys

All push notifications include a `data` payload. Use the `type` field to route in-app navigation.

| `type` key | Sent to | Trigger | `action_url` |
|---|---|---|---|
| `assist_proposal_received` | Seeker | Helper submitted a proposal | `assist/seeker/request/{id}/proposals` |
| `assist_proposal_accepted` | Helper | Seeker accepted proposal | `assist/helper/mission/{id}` |
| `assist_proposal_rejected` | Helper | Seeker chose another helper | `assist/helper/feed` |
| `assist_new_request` | Helper | New request nearby | `assist/helper/feed` |
| `assist_en_route` | Seeker | Helper is on the way | `assist/seeker/request/{id}` |
| `assist_arrived` | Seeker | Helper arrived — show QR scanner | `assist/seeker/request/{id}` |
| `assist_completed` | Seeker | Mission complete | `assist/seeker/request/{id}` |
| `assist_cancelled` | Helper | Seeker cancelled | `assist/helper/mission/{id}` |
| `assist_rated` | Helper | Seeker left a rating | `assist/helper/profile` |
| `assist_helper_approved` | Helper | Admin approved profile | `assist/helper/profile` |
| `assist_helper_rejected` | Helper | Admin rejected profile | `assist/helper/profile` |

**Full FCM data payload structure:**
```json
{
  "notification_id": "42",
  "type": "assist_proposal_received",
  "entity_type": "assistance_request",
  "entity_id": "12",
  "role": "seeker",
  "action_url": "assist/seeker/request/12/proposals",
  "timestamp": "2026-05-30T10:05:00+00:00"
}
```

---

## 5. Known Limitations / TODOs

| Feature | Status | Notes |
|---|---|---|
| Tow Service — provider notification | ⚠️ TODO | Provider is assigned but not notified via push |
| Tow Service — payment transaction | ⚠️ TODO | `payment_required: true` in response but no gateway call yet |
| Proposals lat/long | ✅ Done | Helper coords in `GET /proposals` response |
| Tow route duplicates | ℹ️ Info | `/api/tow/types` == `/api/tow-types` — use `/api/tow-types` |
