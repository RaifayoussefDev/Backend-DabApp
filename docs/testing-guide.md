# DabApp — Trainer Module: Step-by-Step Testing Guide

> **Purpose:** Manual end-to-end testing of every Trainer API scenario using Postman or cURL.
> **Base URL:** `{{BASE_URL}}` — replace with your environment (e.g. `http://localhost:8000`)
> **Auth header:** `Authorization: Bearer {{TOKEN}}`

---

## Environment Setup

### Postman Collection Variables

| Variable | Example Value | Description |
|---|---|---|
| `BASE_URL` | `http://localhost:8000` | API base URL |
| `TOKEN` | *(auto-set after login)* | JWT access token |
| `TRAINER_ID` | *(set after finding a trainer)* | Trainer ID for session tests |
| `BOOKING_ID` | *(set after booking)* | Booking ID |
| `LOCATION_ID` | *(set from trainer detail)* | Training location ID |

### Pre-request Script (auto-inject token)
```javascript
pm.request.headers.add({
  key: "Authorization",
  value: "Bearer " + pm.collectionVariables.get("TOKEN")
});
pm.request.headers.add({
  key: "Accept",
  value: "application/json"
});
```

---

## BLOCK 1 — Authentication

### TEST 1.1 — Register a new user (Client role)

**Method:** `POST`
**URL:** `{{BASE_URL}}/api/register`
**Headers:** `Content-Type: application/json`, `Accept: application/json`

**Body (JSON):**
```json
{
  "first_name": "Ahmed",
  "last_name": "Al-Rashidi",
  "email": "ahmed.test@example.com",
  "phone": "+966501234567",
  "password": "Test@1234",
  "password_confirmation": "Test@1234"
}
```

**Expected response:** `201 Created`
```json
{
  "success": true,
  "message": "Registration successful. Please verify your account."
}
```

**What to check:**
- [ ] Status is `201`
- [ ] No `errors` field in response
- [ ] OTP sent to phone/email (check logs if testing locally: `storage/logs/laravel.log`)

---

### TEST 1.2 — Verify OTP

**Method:** `POST`
**URL:** `{{BASE_URL}}/api/verify-otp`

**Body (JSON):**
```json
{
  "phone": "+966501234567",
  "otp": "123456"
}
```

> **Local dev tip:** Check `storage/logs/laravel.log` for the OTP code.

**Expected:** `200 OK` with token returned. Save the token.

---

### TEST 1.3 — Login

**Method:** `POST`
**URL:** `{{BASE_URL}}/api/login`

**Body (JSON):**
```json
{
  "email": "ahmed.test@example.com",
  "password": "Test@1234"
}
```

**Expected:** `200 OK`
```json
{
  "success": true,
  "data": {
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "token_type": "bearer",
    "expires_in": 3600
  }
}
```

**What to check:**
- [ ] `access_token` is present — save it as `{{TOKEN}}`
- [ ] `token_type` is `bearer`

---

### TEST 1.4 — Get authenticated user

**Method:** `GET`
**URL:** `{{BASE_URL}}/api/me` *(or your user profile endpoint)*
**Headers:** `Authorization: Bearer {{TOKEN}}`

**Expected:** `200 OK` with user profile.

**What to check:**
- [ ] Returns your user's `first_name`, `last_name`, `email`
- [ ] No `401` error

---

## BLOCK 2 — Browse Trainers (Public)

### TEST 2.1 — List all approved trainers

**Method:** `GET`
**URL:** `{{BASE_URL}}/api/trainers`
**Headers:** `Accept: application/json`
*(No auth required)*

**Expected:** `200 OK`
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "total": 5,
    "per_page": 15,
    "data": [
      {
        "id": 1,
        "name": "Khalid Al-Mansouri",
        "specialty": "coaching",
        "price_per_hour": "150.00",
        "rating_average": "4.80",
        "is_available": true
      }
    ]
  }
}
```

**What to check:**
- [ ] Only trainers with `status = approved` appear
- [ ] `is_available = true` trainers are included
- [ ] Save one `id` as `{{TRAINER_ID}}`

---

### TEST 2.2 — Filter by specialty

**Method:** `GET`
**URL:** `{{BASE_URL}}/api/trainers?specialty=coaching`

**Expected:** Only trainers with `specialty = coaching` returned.

---

### TEST 2.3 — Filter by city

**Method:** `GET`
**URL:** `{{BASE_URL}}/api/trainers?city_id=1`

**Expected:** Only trainers who have at least one available location in city 1.

---

### TEST 2.4 — Search by name

**Method:** `GET`
**URL:** `{{BASE_URL}}/api/trainers?search=Khalid`

**Expected:** Trainers whose `name` or `name_ar` contains "Khalid".

---

### TEST 2.5 — Sort by price (ascending)

**Method:** `GET`
**URL:** `{{BASE_URL}}/api/trainers?sort_by=price`

**Expected:** Trainers sorted by `price_per_hour` ascending.

---

### TEST 2.6 — Trainer detail

**Method:** `GET`
**URL:** `{{BASE_URL}}/api/trainers/{{TRAINER_ID}}`

**Expected:** `200 OK` — full trainer profile.

**What to check:**
- [ ] `locations[]` array is present with lat/lng
- [ ] `schedules[]` array is present
- [ ] `reviews[]` array is present (may be empty)
- [ ] `photo_url` is a full URL (not a relative path)
- [ ] `is_liked_by_auth` = false (unauthenticated)

---

### TEST 2.7 — Trainer reviews

**Method:** `GET`
**URL:** `{{BASE_URL}}/api/trainers/{{TRAINER_ID}}/reviews`

**Expected:** `200 OK` with `data` array and `rating_summary` object.

---

### TEST 2.8 — Trainer comments

**Method:** `GET`
**URL:** `{{BASE_URL}}/api/trainers/{{TRAINER_ID}}/comments`

**Expected:** `200 OK` with paginated comments.

---

### TEST 2.9 — All training locations

**Method:** `GET`
**URL:** `{{BASE_URL}}/api/trainer-locations`

**Expected:** Array of locations with `latitude`, `longitude`, `city`, `trainer`.

**What to check:**
- [ ] Save one `id` as `{{LOCATION_ID}}`

---

## BLOCK 3 — Trainer Availability (Public)

### TEST 3.1 — Get availability for next 7 days

**Method:** `GET`
**URL:** `{{BASE_URL}}/api/trainers/{{TRAINER_ID}}/availability?from_date=2026-06-20&to_date=2026-06-26`

**Expected:** `200 OK`
```json
{
  "success": true,
  "data": {
    "trainer": { "id": 1, "name": "Khalid Al-Mansouri", "price_per_hour": 150 },
    "available_slots": [
      {
        "date": "2026-06-21",
        "day_name": "Sunday",
        "time_slots": ["08:00-10:00", "10:00-12:00", "13:00-15:00"]
      }
    ],
    "schedule_source": "configured"
  }
}
```

**What to check:**
- [ ] `schedule_source` is `configured` (if trainer has set a schedule) or `default`
- [ ] `time_slots` are in `HH:MM-HH:MM` format
- [ ] Save one slot (e.g. `"08:00"`) as `{{START_TIME}}` and date as `{{BOOKING_DATE}}`

---

### TEST 3.2 — Availability filtered by location

**Method:** `GET`
**URL:** `{{BASE_URL}}/api/trainers/{{TRAINER_ID}}/availability?from_date=2026-06-20&to_date=2026-06-26&location_id={{LOCATION_ID}}`

**Expected:** Same structure + `location` object in response.

**What to check:**
- [ ] `location` object has `id`, `location_name`, `latitude`, `longitude`

---

### TEST 3.3 — Validation: missing `from_date`

**Method:** `GET`
**URL:** `{{BASE_URL}}/api/trainers/{{TRAINER_ID}}/availability?to_date=2026-06-26`

**Expected:** `422 Unprocessable Entity`
```json
{
  "message": "The from date field is required.",
  "errors": { "from_date": ["The from date field is required."] }
}
```

---

### TEST 3.4 — Validation: `to_date` before `from_date`

**Method:** `GET`
**URL:** `{{BASE_URL}}/api/trainers/{{TRAINER_ID}}/availability?from_date=2026-06-26&to_date=2026-06-20`

**Expected:** `422` with `to_date` error.

---

## BLOCK 4 — Become a Trainer (Provider Registration)

> All endpoints in this block require `Authorization: Bearer {{TOKEN}}`

### TEST 4.1 — Upload profile photo (separate upload)

**Method:** `POST`
**URL:** `{{BASE_URL}}/api/trainer/upload-photo`
**Headers:** `Authorization: Bearer {{TOKEN}}`
**Body:** `multipart/form-data`

| Key | Type | Value |
|---|---|---|
| `photo` | File | Select a JPG/PNG ≤ 2MB |

**Expected:** `200 OK`
```json
{
  "success": true,
  "data": {
    "path": "trainers/photos/abc123.jpg",
    "url": "http://localhost:8000/storage/trainers/photos/abc123.jpg"
  }
}
```

**What to check:**
- [ ] `path` is returned — save as `{{PHOTO_PATH}}`
- [ ] URL is accessible in browser

---

### TEST 4.2 — Upload cover photo

**Method:** `POST`
**URL:** `{{BASE_URL}}/api/trainer/upload-cover`
**Body:** `multipart/form-data`, key `cover`, file ≤ 5MB.

**Expected:** Same structure as 4.1 — save `path` as `{{COVER_PATH}}`

---

### TEST 4.3 — Upload certification files

**Method:** `POST`
**URL:** `{{BASE_URL}}/api/trainer/upload-certificates`
**Body:** `multipart/form-data`

| Key | Type | Value |
|---|---|---|
| `certificates[]` | File | PDF or image ≤ 5MB |
| `certificates[]` | File | Second certificate |

**Expected:** `200 OK`
```json
{
  "success": true,
  "data": {
    "paths": [
      "trainers/certificates/cert1.pdf",
      "trainers/certificates/cert2.jpg"
    ],
    "urls": [
      "http://localhost:8000/storage/trainers/certificates/cert1.pdf"
    ]
  }
}
```

**What to check:**
- [ ] `paths[]` returned — save as `{{CERT_PATHS}}`

---

### TEST 4.4 — Register as trainer

**Method:** `POST`
**URL:** `{{BASE_URL}}/api/trainer/register`
**Headers:** `Authorization: Bearer {{TOKEN}}`, `Content-Type: application/json`

**Body (JSON):**
```json
{
  "name": "Ahmed Al-Rashidi",
  "name_ar": "أحمد الراشدي",
  "bio": "Professional motorcycle coach with 5 years on the track.",
  "bio_ar": "مدرب دراجات نارية محترف مع 5 سنوات على الحلبة.",
  "specialty": "coaching",
  "experience_years": 5,
  "price_per_hour": 200.00,
  "certifications": "FIM Level 1, MSF Certified",
  "photo_path": "{{PHOTO_PATH}}",
  "cover_path": "{{COVER_PATH}}",
  "certification_files": ["trainers/certificates/cert1.pdf"]
}
```

**Expected:** `201 Created`
```json
{
  "success": true,
  "message": "Your trainer profile has been submitted and is pending approval.",
  "data": {
    "id": 2,
    "name": "Ahmed Al-Rashidi",
    "status": "pending",
    "specialty": "coaching"
  }
}
```

**What to check:**
- [ ] `status` is `pending`
- [ ] Profile does NOT appear in `GET /api/trainers` yet
- [ ] Save trainer `id` as `{{MY_TRAINER_ID}}`

---

### TEST 4.5 — Duplicate registration rejected

**Method:** `POST`
**URL:** `{{BASE_URL}}/api/trainer/register`
*(same token, same body)*

**Expected:** `409 Conflict`
```json
{
  "success": false,
  "message": "You already have a trainer profile"
}
```

---

### TEST 4.6 — Validation: missing required field

**Method:** `POST`
**URL:** `{{BASE_URL}}/api/trainer/register`

**Body:** Same as 4.4 but remove `specialty`.

**Expected:** `422 Unprocessable Entity`
```json
{
  "message": "The specialty field is required.",
  "errors": {
    "specialty": ["The specialty field is required."]
  }
}
```

---

## BLOCK 5 — Trainer Profile Management (Provider)

### TEST 5.1 — Get my trainer profile

**Method:** `GET`
**URL:** `{{BASE_URL}}/api/trainer/me`
**Headers:** `Authorization: Bearer {{TOKEN}}`

**Expected:** `200 OK` with full trainer profile including `certification_files_urls[]`.

---

### TEST 5.2 — Update profile fields

**Method:** `POST`
**URL:** `{{BASE_URL}}/api/trainer/profile`
**Headers:** `Authorization: Bearer {{TOKEN}}`, `Content-Type: application/json`

**Body (JSON):**
```json
{
  "bio": "Updated bio — now specialising in off-road training.",
  "price_per_hour": 220.00,
  "is_available": 1
}
```

**Expected:** `200 OK` with fresh trainer data reflecting the changes.

**What to check:**
- [ ] `price_per_hour` updated to `220.00`
- [ ] `is_available` is `true`

---

### TEST 5.3 — Set weekly schedule

**Method:** `POST`
**URL:** `{{BASE_URL}}/api/trainer/schedule`
**Headers:** `Authorization: Bearer {{TOKEN}}`, `Content-Type: application/json`

**Body (JSON):**
```json
{
  "schedule": [
    { "day_of_week": 0, "start_time": "08:00", "end_time": "16:00", "is_available": true },
    { "day_of_week": 1, "start_time": "08:00", "end_time": "16:00", "is_available": true },
    { "day_of_week": 5, "start_time": "00:00", "end_time": "00:00", "is_available": false },
    { "day_of_week": 6, "start_time": "10:00", "end_time": "14:00", "is_available": true }
  ]
}
```

> Day mapping: `0`=Sunday, `1`=Monday, `2`=Tuesday, `3`=Wednesday, `4`=Thursday, `5`=Friday, `6`=Saturday

**Expected:** `200 OK` — schedule upserted.

---

### TEST 5.4 — Add a training location

**Method:** `POST`
**URL:** `{{BASE_URL}}/api/trainer/locations`
**Headers:** `Authorization: Bearer {{TOKEN}}`, `Content-Type: application/json`

**Body (JSON):**
```json
{
  "location_name": "Al-Naseem Training Circuit",
  "location_name_ar": "حلبة النسيم للتدريب",
  "city_id": 1,
  "latitude": 24.7250,
  "longitude": 46.6900
}
```

**Expected:** `201 Created` with location object. Save `id` as `{{LOCATION_ID}}`.

---

### TEST 5.5 — Delete a training location

**Method:** `DELETE`
**URL:** `{{BASE_URL}}/api/trainer/locations/{{LOCATION_ID}}`
**Headers:** `Authorization: Bearer {{TOKEN}}`

**Expected:** `200 OK`
```json
{ "success": true, "message": "Location removed successfully" }
```

---

## BLOCK 6 — Admin Actions (Approve Trainer)

> Use admin credentials for this block. Login to `POST /api/admin/login`.

### TEST 6.1 — Admin login

**Method:** `POST`
**URL:** `{{BASE_URL}}/api/admin/login`

**Body (JSON):**
```json
{
  "email": "admin@dabapp.com",
  "password": "AdminPass@1234"
}
```

**Expected:** `200 OK` — save token as `{{ADMIN_TOKEN}}`

---

### TEST 6.2 — List pending trainers

**Method:** `GET`
**URL:** `{{BASE_URL}}/api/admin/trainers?status=pending`
**Headers:** `Authorization: Bearer {{ADMIN_TOKEN}}`

**Expected:** List including the trainer registered in TEST 4.4.

---

### TEST 6.3 — Approve trainer

**Method:** `POST`
**URL:** `{{BASE_URL}}/api/admin/trainers/{{MY_TRAINER_ID}}/approve`
**Headers:** `Authorization: Bearer {{ADMIN_TOKEN}}`

**Expected:** `200 OK`
```json
{ "success": true, "message": "Trainer approved successfully." }
```

**What to check:**
- [ ] Now `GET /api/trainers` returns this trainer
- [ ] Trainer's `status` is `approved`

---

### TEST 6.4 — Verify trainer appears publicly

**Method:** `GET`
**URL:** `{{BASE_URL}}/api/trainers/{{MY_TRAINER_ID}}`
*(No auth required)*

**Expected:** `200 OK` — full profile visible.

---

## BLOCK 7 — Book a Session (Client)

> Use the CLIENT token (`{{TOKEN}}`), not admin.
> Trainer must be approved (TEST 6.3 done).
> Set `{{TRAINER_ID}}` to an approved trainer with a location.

### TEST 7.1 — Get availability

*(Already covered in Block 3 — run TEST 3.1 again with the approved trainer)*

Note the `date` and a free `time_slot` from the response for the next steps.

---

### TEST 7.2 — Book a session

**Method:** `POST`
**URL:** `{{BASE_URL}}/api/trainers/{{TRAINER_ID}}/book`
**Headers:** `Authorization: Bearer {{TOKEN}}`, `Content-Type: application/json`

**Body (JSON):**
```json
{
  "booking_date": "2026-06-21",
  "start_time": "10:00",
  "duration_hours": 2,
  "location_id": 1,
  "session_type": "beginner",
  "notes": "First time on the track. Please cover basics."
}
```

**Expected:** `201 Created`
```json
{
  "success": true,
  "message": "Booking request sent. Waiting for trainer acceptance.",
  "data": {
    "booking_id": 42,
    "status": "pending",
    "total_price": 400.00,
    "duration_hours": 2,
    "price_per_hour": 200.00,
    "session_type": "beginner",
    "booking_date": "2026-06-21",
    "start_time": "10:00",
    "end_time": "12:00",
    "location": { "id": 1, "location_name": "Al-Naseem Training Circuit" }
  }
}
```

**What to check:**
- [ ] `status` = `pending`
- [ ] `end_time` = `start_time + duration_hours` (10:00 + 2h = 12:00)
- [ ] `total_price` = `price_per_hour × duration_hours` (200 × 2 = 400)
- [ ] Save `booking_id` as `{{BOOKING_ID}}`

---

### TEST 7.3 — Double-booking conflict (same slot)

**Method:** `POST`
**URL:** `{{BASE_URL}}/api/trainers/{{TRAINER_ID}}/book`
*(exact same body as TEST 7.2)*

**Expected:** `400 Bad Request`
```json
{
  "success": false,
  "message": "This time slot is already booked"
}
```

---

### TEST 7.4 — Validation: past date rejected

**Body:** Same as 7.2 but `"booking_date": "2020-01-01"`

**Expected:** `422 Unprocessable Entity`
```json
{
  "errors": { "booking_date": ["The booking date must be a date after today."] }
}
```

---

### TEST 7.5 — Validation: invalid session type

**Body:** Same as 7.2 but `"session_type": "pro"`

**Expected:** `422 Unprocessable Entity`
```json
{
  "errors": { "session_type": ["The selected session type is invalid."] }
}
```

---

### TEST 7.6 — View my bookings

**Method:** `GET`
**URL:** `{{BASE_URL}}/api/trainer/bookings`
**Headers:** `Authorization: Bearer {{TOKEN}}`

**Expected:** Paginated list including booking from TEST 7.2.

---

### TEST 7.7 — View upcoming bookings only

**Method:** `GET`
**URL:** `{{BASE_URL}}/api/trainer/bookings?upcoming=1`

**Expected:** Only future bookings with status `pending`, `accepted`, or `confirmed`.

---

## BLOCK 8 — Trainer Accepts & Payment Flow

> Use the TRAINER's token (the user who registered as trainer in TEST 4.4).
> Login with trainer credentials to get a separate `{{TRAINER_TOKEN}}`.

### TEST 8.1 — Trainer views their sessions

**Method:** `GET`
**URL:** `{{BASE_URL}}/api/trainer/sessions`
**Headers:** `Authorization: Bearer {{TRAINER_TOKEN}}`

**Expected:** Paginated list of sessions for this trainer.

---

### TEST 8.2 — Trainer accepts the booking

**Method:** `POST`
**URL:** `{{BASE_URL}}/api/trainer/sessions/{{BOOKING_ID}}/accept`
**Headers:** `Authorization: Bearer {{TRAINER_TOKEN}}`

**Expected:** `200 OK`
```json
{
  "success": true,
  "message": "Booking accepted. Client has been notified to complete payment.",
  "data": {
    "booking_id": 42,
    "status": "accepted",
    "payment_url": "https://secure.paytabs.com/payment/page/..."
  }
}
```

**What to check:**
- [ ] `status` changed to `accepted`
- [ ] `payment_url` is present (or null if `SKIP_PAYTABS=true` in `.env`)

---

### TEST 8.3 — Client initiates payment

**Method:** `POST`
**URL:** `{{BASE_URL}}/api/trainer/bookings/{{BOOKING_ID}}/pay`
**Headers:** `Authorization: Bearer {{TOKEN}}`

**Expected:** `200 OK`
```json
{
  "success": true,
  "message": "Payment initiated.",
  "data": {
    "payment_url": "https://secure.paytabs.com/payment/page/..."
  }
}
```

**What to check:**
- [ ] If `SKIP_PAYTABS=true`, `payment_url` is null — this is expected in dev
- [ ] Client is redirected to this URL to complete payment

---

### TEST 8.4 — Simulate PayTabs callback (payment approved)

> **Dev only** — simulate the PayTabs webhook locally.

**Method:** `POST`
**URL:** `{{BASE_URL}}/api/trainer/payments/callback`
**Headers:** `Content-Type: application/json`

**Body (JSON):**
```json
{
  "tran_ref": "TST2026123456789",
  "cart_id": "TRAINER_42",
  "cart_amount": 400.00,
  "cart_currency": "SAR",
  "payment_result": {
    "response_status": "A",
    "response_code": "000",
    "response_message": "Authorised"
  }
}
```

> Replace `42` with your actual `{{BOOKING_ID}}`.

**Expected:** `200 OK`
```json
{
  "success": true,
  "message": "Payment confirmed. Booking status: confirmed"
}
```

**What to check:**
- [ ] Booking `status` = `confirmed` (verify via GET /api/trainer/bookings)
- [ ] Booking `payment_status` = `paid`
- [ ] A `payment_split` record created (check DB)
- [ ] A `trainer_payout` record created with `status = pending` (check DB)

---

### TEST 8.5 — Simulate PayTabs callback (payment declined)

**Body:** Same as 8.4 but:
```json
{
  "payment_result": {
    "response_status": "D",
    "response_code": "051",
    "response_message": "Declined"
  }
}
```

**Expected:** `200 OK`
```json
{
  "success": true,
  "message": "Payment declined. Booking cancelled."
}
```

**What to check:**
- [ ] Booking `status` = `cancelled`
- [ ] Booking `payment_status` = `failed`

---

## BLOCK 9 — Session Lifecycle (Trainer)

### TEST 9.1 — Trainer marks session as started

**Method:** `POST`
**URL:** `{{BASE_URL}}/api/trainer/sessions/{{BOOKING_ID}}/start`
**Headers:** `Authorization: Bearer {{TRAINER_TOKEN}}`

> Booking must be in `confirmed` status.

**Expected:** `200 OK`
```json
{ "success": true, "message": "Session marked as in progress" }
```

---

### TEST 9.2 — Trainer marks session as completed

**Method:** `POST`
**URL:** `{{BASE_URL}}/api/trainer/sessions/{{BOOKING_ID}}/complete`
**Headers:** `Authorization: Bearer {{TRAINER_TOKEN}}`

> Booking must be `in_progress`.

**Expected:** `200 OK`
```json
{ "success": true, "message": "Session marked as completed" }
```

**What to check:**
- [ ] `status` = `completed`
- [ ] Client receives a "rate your session" notification (check Firebase logs)

---

## BLOCK 10 — Review Submission (Client)

### TEST 10.1 — Submit a review (happy path)

**Method:** `POST`
**URL:** `{{BASE_URL}}/api/trainer/bookings/{{BOOKING_ID}}/review`
**Headers:** `Authorization: Bearer {{TOKEN}}`, `Content-Type: application/json`

> Booking must be `completed`.

**Body (JSON):**
```json
{
  "rating": 5,
  "comment": "Khalid is an exceptional coach. Very patient and thorough."
}
```

**Expected:** `201 Created`
```json
{
  "success": true,
  "message": "Review submitted successfully."
}
```

---

### TEST 10.2 — Cannot review twice

**Method:** `POST`
**URL:** Same as 10.1, same body.

**Expected:** `400 Bad Request` — "You have already reviewed this session."

---

### TEST 10.3 — Cannot review incomplete session

Create a new booking, do NOT complete it, then try to submit a review.

**Expected:** `400 Bad Request` — "This session cannot be reviewed yet."

---

## BLOCK 11 — Social Features (Auth)

### TEST 11.1 — Like a trainer

**Method:** `POST`
**URL:** `{{BASE_URL}}/api/trainers/{{TRAINER_ID}}/like`
**Headers:** `Authorization: Bearer {{TOKEN}}`

**Expected:** `200 OK`
```json
{ "success": true, "message": "Trainer liked.", "liked": true }
```

**Call again (toggle off):**
```json
{ "success": true, "message": "Like removed.", "liked": false }
```

---

### TEST 11.2 — Favorite a trainer

**Method:** `POST`
**URL:** `{{BASE_URL}}/api/trainers/{{TRAINER_ID}}/favorite`
**Headers:** `Authorization: Bearer {{TOKEN}}`

**Expected:** Toggle behavior, same as like.

---

### TEST 11.3 — View my favorites

**Method:** `GET`
**URL:** `{{BASE_URL}}/api/user/trainer-favorites`
**Headers:** `Authorization: Bearer {{TOKEN}}`

**Expected:** List of favorited trainers.

---

### TEST 11.4 — Post a comment

**Method:** `POST`
**URL:** `{{BASE_URL}}/api/trainers/{{TRAINER_ID}}/comments`
**Headers:** `Authorization: Bearer {{TOKEN}}`, `Content-Type: application/json`

**Body (JSON):**
```json
{
  "content": "Looking forward to booking a session next week!"
}
```

**Expected:** `201 Created`

**What to check:**
- [ ] Comment does NOT appear in `GET /api/trainers/{{TRAINER_ID}}/comments` until approved by admin
- [ ] Admin can approve at: `POST /api/admin/trainer-comments/{id}/approve`

---

## BLOCK 12 — Cancel Booking (Client)

### TEST 12.1 — Cancel a pending/confirmed booking

**Method:** `POST`
**URL:** `{{BASE_URL}}/api/trainer/bookings/{{BOOKING_ID}}/cancel`
**Headers:** `Authorization: Bearer {{TOKEN}}`

> Booking must be `pending` or `confirmed`.

**Expected:** `200 OK`
```json
{ "success": true, "message": "Booking cancelled successfully" }
```

**What to check:**
- [ ] Booking `status` = `cancelled`
- [ ] If booking was `paid`, a PayTabs refund is triggered (check logs)

---

### TEST 12.2 — Cannot cancel a completed session

**Method:** `POST`
**URL:** `{{BASE_URL}}/api/trainer/bookings/{{COMPLETED_BOOKING_ID}}/cancel`

**Expected:** `400 Bad Request`
```json
{ "success": false, "message": "This booking cannot be cancelled in its current status" }
```

---

## BLOCK 13 — Trainer Rejection Flow

### TEST 13.1 — Trainer rejects a booking

**Method:** `POST`
**URL:** `{{BASE_URL}}/api/trainer/sessions/{{BOOKING_ID}}/reject`
**Headers:** `Authorization: Bearer {{TRAINER_TOKEN}}`, `Content-Type: application/json`

> Booking must be `pending`.

**Body (JSON):**
```json
{
  "reason": "I have a prior commitment on this date. Please reschedule."
}
```

**Expected:** `200 OK`
```json
{
  "success": true,
  "message": "Booking rejected. Client has been notified.",
  "data": { "booking_id": 42, "status": "cancelled" }
}
```

---

## BLOCK 14 — Error & Edge Cases

| Scenario | Method | URL | Expected |
|---|---|---|---|
| Expired token | GET | `/api/trainer/me` | `401` — "Token has expired" |
| Invalid token | GET | `/api/trainer/me` with random token | `401` — "Token is invalid" |
| Trainer not found | GET | `/api/trainers/99999` | `404` — "Trainer not found" |
| Location not belonging to trainer | POST | `/api/trainers/1/book` with foreign location_id | `400` — "Invalid location for this trainer" |
| Trainer unavailable (`is_available=false`) | POST | `/api/trainers/{id}/book` | `400` — "Trainer is not currently available" |
| Duration > 4h | POST | `/api/trainers/{id}/book` with `duration_hours: 5` | `422` — "duration_hours max 4" |
| Client books own trainer profile | POST | `/api/trainers/{{MY_TRAINER_ID}}/book` | Should work (no self-booking restriction) — verify business rule |

---

## Quick Reference: Booking Status Flow

```
[Client books]
      ↓
   pending
      ↓ (Trainer accepts)
   accepted
      ↓ (Client pays via PayTabs)
  confirmed  ←── PayTabs webhook (status=A)
      ↓ (Trainer starts)
  in_progress
      ↓ (Trainer completes)
  completed
      ↓ (Client can now review)
   [review submitted]

Alt paths:
  pending → cancelled  (client cancels OR trainer rejects)
  confirmed → cancelled  (client cancels — triggers refund if paid)
  accepted → cancelled  (payment declined by PayTabs)
```

---

## Checklist Summary

- [ ] Auth: register → OTP → login → token saved
- [ ] Public: browse trainers, filter, search, availability
- [ ] Provider: photo upload → certificate upload → register → schedule → location
- [ ] Admin: approve trainer
- [ ] Client: book → conflict check → payment simulation → confirm
- [ ] Trainer: view sessions → accept → start → complete
- [ ] Client: review → like → favorite → comment
- [ ] Cancel flow tested (pending and confirmed states)
- [ ] Rejection flow tested
- [ ] All 422 validation errors verified
- [ ] All 404/400/403 errors verified
