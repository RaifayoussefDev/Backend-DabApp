# DabApp — Mobile Developer Guide
## Trainer & Reservation Module

**Version:** 1.0 — June 2026
**Audience:** Mobile developers (iOS / Android / Flutter / React Native)
**Backend:** Laravel 12 REST API + JWT Auth + PayTabs

---

## Table of Contents

1. [API Overview](#1-api-overview)
2. [Authentication](#2-authentication)
3. [Trainer Browsing & Discovery](#3-trainer-browsing--discovery)
4. [Trainer Profile & Availability](#4-trainer-profile--availability)
5. [Become a Trainer (Provider Flow)](#5-become-a-trainer-provider-flow)
6. [File Uploads](#6-file-uploads)
7. [Booking a Session (Client Flow)](#7-booking-a-session-client-flow)
8. [Payment Flow](#8-payment-flow)
9. [Session Lifecycle (Trainer Side)](#9-session-lifecycle-trainer-side)
10. [Reviews, Comments & Social](#10-reviews-comments--social)
11. [Schedule Management](#11-schedule-management)
12. [Training Locations](#12-training-locations)
13. [Error Codes & Validation Rules](#13-error-codes--validation-rules)
14. [Complete Flow Diagrams](#14-complete-flow-diagrams)
15. [Localization & Language](#15-localization--language)

---

## 1. API Overview

### Base URL

```
Production:  https://api.dabapp.com
Staging:     https://staging-api.dabapp.com
```

### Request Format

All requests must include:

```
Content-Type: application/json
Accept: application/json
```

For authenticated endpoints, also include:
```
Authorization: Bearer {access_token}
```

For file upload endpoints, use:
```
Content-Type: multipart/form-data
```

### Response Envelope

All responses follow this consistent structure:

```json
{
  "success": true | false,
  "message": "Human-readable message",
  "data": { ... } | [ ... ] | null
}
```

For paginated lists, `data` contains:
```json
{
  "data": [ ... ],
  "current_page": 1,
  "last_page": 5,
  "per_page": 15,
  "total": 72,
  "next_page_url": "https://api.dabapp.com/api/trainers?page=2",
  "prev_page_url": null
}
```

### HTTP Status Codes Used

| Code | Meaning |
|---|---|
| `200` | Success |
| `201` | Resource created |
| `400` | Business logic error (conflict, unavailable, wrong status) |
| `401` | Not authenticated (missing or expired token) |
| `403` | Authenticated but not authorized (wrong owner) |
| `404` | Resource not found |
| `409` | Conflict (duplicate resource) |
| `422` | Validation error (invalid input data) |
| `500` | Server error |

---

## 2. Authentication

### 2.1 Register

```
POST /api/register
```

**Request:**
```json
{
  "first_name": "Ahmed",
  "last_name": "Al-Rashidi",
  "email": "ahmed@example.com",
  "phone": "+966501234567",
  "password": "MyPass@2026",
  "password_confirmation": "MyPass@2026"
}
```

**Response `201`:**
```json
{
  "success": true,
  "message": "Registration successful. Please verify your account."
}
```

> An OTP is sent via WhatsApp/SMS to `phone`.

---

### 2.2 Verify OTP

```
POST /api/verify-otp
```

**Request:**
```json
{
  "phone": "+966501234567",
  "otp": "123456"
}
```

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "access_token": "eyJ0eXAiOiJKV1Qi...",
    "token_type": "bearer",
    "expires_in": 3600
  }
}
```

---

### 2.3 Login

```
POST /api/login
```

**Request:**
```json
{
  "email": "ahmed@example.com",
  "password": "MyPass@2026"
}
```

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "access_token": "eyJ0eXAiOiJKV1Qi...",
    "token_type": "bearer",
    "expires_in": 3600,
    "user": {
      "id": 101,
      "first_name": "Ahmed",
      "last_name": "Al-Rashidi",
      "email": "ahmed@example.com",
      "phone": "+966501234567"
    }
  }
}
```

---

### 2.4 Refresh Token

```
POST /api/refresh
Authorization: Bearer {expired_token}
```

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "access_token": "eyJ0eXAiOiJKV1Qi...(new token)",
    "expires_in": 3600
  }
}
```

> **Mobile implementation tip:** Call this endpoint automatically when you receive a `401` response. Store both the token and its expiry timestamp. Refresh proactively before it expires.

---

### 2.5 Token Storage Rules

- Store JWT in **secure storage** (Keychain on iOS, EncryptedSharedPreferences on Android)
- Never store in plain SharedPreferences or AsyncStorage without encryption
- Token expires in **3600 seconds** (1 hour)
- Use the refresh endpoint to extend sessions without re-login

---

## 3. Trainer Browsing & Discovery

All endpoints in this section are **public** (no authentication required).

### 3.1 List Trainers

```
GET /api/trainers
```

**Query parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `specialty` | string | No | Filter: `coaching`, `competition`, `off-road`, `street`, `custom` |
| `city_id` | integer | No | Filter by city ID |
| `min_rating` | float | No | Minimum rating (e.g. `4.0`) |
| `min_experience_years` | integer | No | Minimum years of experience |
| `is_available` | integer | No | `1` = available only |
| `search` | string | No | Free-text search on name (EN and AR) |
| `sort_by` | string | No | `rating` (default), `price`, `experience`, `sessions` |
| `per_page` | integer | No | Items per page (default `15`) |
| `page` | integer | No | Page number |

**Example request:**
```
GET /api/trainers?specialty=coaching&sort_by=rating&per_page=10
```

**Response `200`:**
```json
{
  "success": true,
  "message": "Trainers retrieved successfully",
  "data": {
    "current_page": 1,
    "total": 24,
    "per_page": 10,
    "data": [
      {
        "id": 1,
        "name": "Khalid Al-Mansouri",
        "name_ar": "خالد المنصوري",
        "specialty": "coaching",
        "experience_years": 8,
        "price_per_hour": "150.00",
        "rating_average": "4.80",
        "total_sessions": 200,
        "likes_count": 35,
        "is_available": true,
        "photo_url": "https://api.dabapp.com/storage/trainers/photos/photo1.jpg",
        "cover_url": "https://api.dabapp.com/storage/trainers/covers/cover1.jpg",
        "locations": [
          {
            "id": 5,
            "location_name": "Al-Naseem Training Circuit",
            "location_name_ar": "حلبة النسيم",
            "latitude": "24.7250000",
            "longitude": "46.6900000",
            "city": { "id": 1, "name": "Riyadh" }
          }
        ]
      }
    ]
  }
}
```

---

### 3.2 All Training Locations (Map View)

```
GET /api/trainer-locations
```

**Query parameters:**

| Parameter | Type | Description |
|---|---|---|
| `city_id` | integer | Filter by city |
| `trainer_id` | integer | Filter by trainer |

**Response `200`:**
```json
{
  "success": true,
  "count": 8,
  "data": [
    {
      "id": 5,
      "location_name": "Al-Naseem Training Circuit",
      "location_name_ar": "حلبة النسيم",
      "latitude": "24.7250000",
      "longitude": "46.6900000",
      "is_available": true,
      "city": { "id": 1, "name": "Riyadh" },
      "trainer": {
        "id": 1,
        "name": "Khalid Al-Mansouri",
        "photo_url": "https://api.dabapp.com/storage/trainers/photos/photo1.jpg",
        "rating_average": "4.80"
      }
    }
  ]
}
```

> **Use case:** Display all trainer pins on a map. Tapping a pin navigates to the trainer detail page.

---

## 4. Trainer Profile & Availability

### 4.1 Trainer Detail

```
GET /api/trainers/{id}
```

> Pass `Authorization: Bearer {token}` to get personalized `is_liked_by_auth` and `is_favorited_by_auth` flags.

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Khalid Al-Mansouri",
    "name_ar": "خالد المنصوري",
    "bio": "Professional motorcycle coach with 8 years on the track.",
    "bio_ar": "مدرب دراجات نارية محترف مع 8 سنوات على الحلبة.",
    "specialty": "coaching",
    "certifications": "FIM Level 2, MSF Certified",
    "certification_files_urls": [
      "https://api.dabapp.com/storage/trainers/certificates/cert1.pdf"
    ],
    "experience_years": 8,
    "price_per_hour": "150.00",
    "rating_average": "4.80",
    "total_sessions": 200,
    "likes_count": 35,
    "is_available": true,
    "photo_url": "https://api.dabapp.com/storage/trainers/photos/photo1.jpg",
    "cover_url": "https://api.dabapp.com/storage/trainers/covers/cover1.jpg",
    "is_liked_by_auth": false,
    "is_favorited_by_auth": false,
    "locations": [
      {
        "id": 5,
        "location_name": "Al-Naseem Training Circuit",
        "latitude": "24.7250000",
        "longitude": "46.6900000",
        "is_available": true,
        "city": { "id": 1, "name": "Riyadh" }
      }
    ],
    "schedules": [
      {
        "id": 3,
        "day_of_week": 0,
        "day_name": "Sunday",
        "day_name_ar": "الأحد",
        "start_time": "08:00",
        "end_time": "16:00",
        "is_available": true
      }
    ],
    "reviews": [
      {
        "id": 10,
        "rating": 5,
        "comment": "Excellent coach!",
        "created_at": "2026-06-01T10:00:00Z",
        "user": {
          "id": 55,
          "first_name": "Sara",
          "last_name": "K.",
          "avatar": null
        }
      }
    ]
  }
}
```

---

### 4.2 Get Trainer Availability

```
GET /api/trainers/{id}/availability
```

**Query parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `from_date` | date `YYYY-MM-DD` | Yes | Start of range |
| `to_date` | date `YYYY-MM-DD` | Yes | End of range (must be ≥ from_date) |
| `location_id` | integer | No | Filter slots by location |

**Example:**
```
GET /api/trainers/1/availability?from_date=2026-06-20&to_date=2026-06-26&location_id=5
```

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "trainer": {
      "id": 1,
      "name": "Khalid Al-Mansouri",
      "price_per_hour": 150
    },
    "location": {
      "id": 5,
      "location_name": "Al-Naseem Training Circuit",
      "latitude": "24.7250000",
      "longitude": "46.6900000",
      "is_available": true,
      "city": { "id": 1, "name": "Riyadh" }
    },
    "available_slots": [
      {
        "date": "2026-06-21",
        "day_name": "Sunday",
        "time_slots": ["08:00-10:00", "10:00-12:00", "13:00-15:00"]
      },
      {
        "date": "2026-06-22",
        "day_name": "Monday",
        "time_slots": ["08:00-10:00", "15:00-17:00"]
      }
    ],
    "schedule_source": "configured",
    "period": {
      "from": "2026-06-20",
      "to": "2026-06-26"
    }
  }
}
```

> **Note:** `schedule_source` = `"configured"` means the trainer has set custom working hours. `"default"` means fallback to 08:00–19:00 in 2h blocks. Already-booked slots are excluded from `time_slots`.

**UI guidance:** Render a week calendar. Each day with `time_slots` is bookable. Tapping a slot pre-fills the booking form.

---

### 4.3 Trainer Reviews

```
GET /api/trainers/{id}/reviews?per_page=10
```

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 10,
        "rating": 5,
        "comment": "Excellent coach — very patient and thorough.",
        "created_at": "2026-06-01T10:00:00Z",
        "user": { "id": 55, "first_name": "Sara", "last_name": "K.", "avatar": null }
      }
    ],
    "current_page": 1,
    "total": 24
  },
  "rating_summary": {
    "average": 4.8,
    "total": 24,
    "distribution": { "5": 18, "4": 4, "3": 1, "2": 1, "1": 0 }
  }
}
```

---

## 5. Become a Trainer (Provider Flow)

This is a multi-step flow. The recommended UI order is:

```
Step 1: Upload photo & cover
Step 2: Upload certificates
Step 3: Submit registration form (with paths from steps 1 & 2)
Step 4: Add locations & set schedule (after approval)
```

### 5.1 Register as Trainer

```
POST /api/trainer/register
Authorization: Bearer {token}
Content-Type: application/json
```

**Request:**
```json
{
  "name": "Ahmed Al-Rashidi",
  "name_ar": "أحمد الراشدي",
  "bio": "Professional motorcycle coach with 5 years on-track experience.",
  "bio_ar": "مدرب دراجات نارية محترف مع 5 سنوات على الحلبة.",
  "specialty": "coaching",
  "experience_years": 5,
  "price_per_hour": 200.00,
  "certifications": "FIM Level 1, MSF Certified",
  "photo_path": "trainers/photos/abc123.jpg",
  "cover_path": "trainers/covers/xyz456.jpg",
  "certification_files": [
    "trainers/certificates/cert1.pdf",
    "trainers/certificates/cert2.jpg"
  ]
}
```

> `photo_path`, `cover_path`, and `certification_files` are paths returned by the file upload endpoints (see Section 6). Upload files first, then send the paths here.

**Validation rules:**

| Field | Rule |
|---|---|
| `name` | Required, string, max 255 |
| `name_ar` | Optional, string, max 255 |
| `bio` | Optional, string, max 3000 |
| `bio_ar` | Optional, string, max 3000 |
| `specialty` | Required, one of: `coaching`, `competition`, `off-road`, `street`, `custom` |
| `experience_years` | Required, integer, 0–50 |
| `price_per_hour` | Required, numeric, ≥ 0 |
| `certifications` | Optional, string, max 3000 |
| `certification_files` | Optional, array, max 10 items |

**Response `201`:**
```json
{
  "success": true,
  "message": "Your trainer profile has been submitted and is pending approval.",
  "data": {
    "id": 3,
    "name": "Ahmed Al-Rashidi",
    "status": "pending",
    "specialty": "coaching",
    "is_available": false
  }
}
```

> After submission, the profile is **not visible publicly** until an admin approves it. Status transitions: `pending` → `approved` | `rejected` | `suspended`.

**Error `409` — already a trainer:**
```json
{
  "success": false,
  "message": "You already have a trainer profile"
}
```

---

### 5.2 Get My Trainer Profile

```
GET /api/trainer/me
Authorization: Bearer {token}
```

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "id": 3,
    "name": "Ahmed Al-Rashidi",
    "name_ar": "أحمد الراشدي",
    "bio": "Professional motorcycle coach...",
    "specialty": "coaching",
    "experience_years": 5,
    "price_per_hour": "200.00",
    "rating_average": "0.00",
    "total_sessions": 0,
    "is_available": false,
    "status": "pending",
    "photo_url": "https://api.dabapp.com/storage/trainers/photos/abc123.jpg",
    "cover_url": "https://api.dabapp.com/storage/trainers/covers/xyz456.jpg",
    "certification_files_urls": [
      "https://api.dabapp.com/storage/trainers/certificates/cert1.pdf"
    ],
    "locations": [],
    "schedules": []
  }
}
```

---

### 5.3 Update Trainer Profile

```
POST /api/trainer/profile
Authorization: Bearer {token}
Content-Type: application/json
```

**Request (send only fields to update):**
```json
{
  "bio": "Updated bio — now also offering off-road sessions.",
  "price_per_hour": 220.00,
  "is_available": 1,
  "photo_path": "trainers/photos/newphoto.jpg",
  "cover_path": "trainers/covers/newcover.jpg",
  "certification_files": [
    "trainers/certificates/new_cert.pdf"
  ]
}
```

> `is_available: 1` to mark yourself available, `is_available: 0` to go offline.
> To clear all certification files: add `"certification_files_empty": true` to the body.

**Response `200`:**
```json
{
  "success": true,
  "message": "Profile updated successfully",
  "data": { ... full trainer object ... }
}
```

---

## 6. File Uploads

All upload endpoints require authentication. Files are processed (resized, watermarked) server-side.

### 6.1 Upload Profile Photo

```
POST /api/trainer/upload-photo
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

| Field | Type | Rules |
|---|---|---|
| `photo` | file | Required, image (jpg/png/gif/webp), max 2MB |

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "path": "trainers/photos/abc123.jpg",
    "url": "https://api.dabapp.com/storage/trainers/photos/abc123.jpg",
    "thumbnail_url": "https://api.dabapp.com/storage/trainers/photos/thumbnails/abc123_thumb.jpg"
  }
}
```

> Save `path` — it will be sent in the registration/update body.

---

### 6.2 Delete Profile Photo

```
DELETE /api/trainer/upload-photo/{filename}
Authorization: Bearer {token}
```

**Response `200`:** `{ "success": true, "message": "Photo deleted" }`

---

### 6.3 Upload Cover Photo

```
POST /api/trainer/upload-cover
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

| Field | Type | Rules |
|---|---|---|
| `cover` | file | Required, image, max 5MB |

**Response:** Same structure as photo upload, with `path` and `url`.

---

### 6.4 Delete Cover Photo

```
DELETE /api/trainer/upload-cover/{filename}
Authorization: Bearer {token}
```

---

### 6.5 Upload Certification Files

```
POST /api/trainer/upload-certificates
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

| Field | Type | Rules |
|---|---|---|
| `certificates[]` | file | Required, image or PDF, max 5MB each, up to 10 files |

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "paths": [
      "trainers/certificates/cert1.pdf",
      "trainers/certificates/cert2.jpg"
    ],
    "urls": [
      "https://api.dabapp.com/storage/trainers/certificates/cert1.pdf",
      "https://api.dabapp.com/storage/trainers/certificates/cert2.jpg"
    ]
  }
}
```

---

### 6.6 Delete a Certification File

```
DELETE /api/trainer/upload-certificates/{filename}
Authorization: Bearer {token}
```

---

### Upload Flow Summary

```
Mobile app                              Backend
    │                                       │
    ├─── POST /trainer/upload-photo ───────►│ Process & store image
    │◄── { path: "trainers/photos/x.jpg" }─┤
    │                                       │
    ├─── POST /trainer/upload-cover ───────►│ Process & store cover
    │◄── { path: "trainers/covers/y.jpg" }─┤
    │                                       │
    ├─── POST /trainer/upload-certificates ►│ Store cert files
    │◄── { paths: ["trainers/certs/z.pdf"]}┤
    │                                       │
    └─── POST /trainer/register ───────────►│ Create trainer with paths
         { photo_path: "trainers/...",       │
           cover_path: "trainers/...",       │
           certification_files: [...] }      │
```

---

## 7. Booking a Session (Client Flow)

### Prerequisites

- User must be authenticated
- Trainer must be `approved` and `is_available = true`
- Target time slot must be free (check availability first)

### 7.1 Book a Session

```
POST /api/trainers/{trainer_id}/book
Authorization: Bearer {token}
Content-Type: application/json
```

**Request:**
```json
{
  "booking_date": "2026-06-21",
  "start_time": "10:00",
  "duration_hours": 2,
  "location_id": 5,
  "session_type": "beginner",
  "notes": "First time on a track. Please start with safety basics."
}
```

**Field rules:**

| Field | Type | Required | Rules |
|---|---|---|---|
| `booking_date` | date `YYYY-MM-DD` | Yes | Must be a future date (after today) |
| `start_time` | time `HH:MM` | Yes | 24-hour format, must be a free slot |
| `duration_hours` | integer | No | 1–4 (default: 1) |
| `location_id` | integer | Yes | Must belong to this trainer |
| `session_type` | string | No | `beginner`, `intermediate`, `advanced`, `custom` (default: `beginner`) |
| `notes` | string | No | Max 1000 characters |

**Response `201`:**
```json
{
  "success": true,
  "message": "Booking request sent. Waiting for trainer acceptance.",
  "data": {
    "booking_id": 42,
    "status": "pending",
    "total_price": 300.00,
    "duration_hours": 2,
    "price_per_hour": 150.00,
    "session_type": "beginner",
    "booking_date": "2026-06-21",
    "start_time": "10:00",
    "end_time": "12:00",
    "location": {
      "id": 5,
      "location_name": "Al-Naseem Training Circuit",
      "latitude": "24.7250000",
      "longitude": "46.6900000",
      "city": { "id": 1, "name": "Riyadh" }
    }
  }
}
```

**Error `400` — slot already booked:**
```json
{ "success": false, "message": "This time slot is already booked" }
```

**Error `400` — trainer unavailable:**
```json
{ "success": false, "message": "Trainer is not currently available" }
```

---

### 7.2 View My Bookings

```
GET /api/trainer/bookings
Authorization: Bearer {token}
```

**Query parameters:**

| Parameter | Type | Description |
|---|---|---|
| `status` | string | Filter: `pending`, `accepted`, `confirmed`, `in_progress`, `completed`, `cancelled` |
| `upcoming` | integer | `1` = show only upcoming sessions |
| `per_page` | integer | Items per page |

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 42,
        "booking_date": "2026-06-21",
        "start_time": "10:00:00",
        "end_time": "12:00:00",
        "duration_hours": 2,
        "session_type": "beginner",
        "status": "pending",
        "price": "300.00",
        "payment_status": "pending",
        "notes": "First time on a track.",
        "can_review": false,
        "trainer": {
          "id": 1,
          "name": "Khalid Al-Mansouri",
          "photo_url": "https://api.dabapp.com/storage/trainers/photos/photo1.jpg"
        },
        "location": {
          "id": 5,
          "location_name": "Al-Naseem Training Circuit",
          "city": { "name": "Riyadh" }
        }
      }
    ],
    "total": 3
  }
}
```

> `can_review: true` means the session is completed and the user hasn't reviewed it yet. Show "Leave a Review" button when this is `true`.

---

### 7.3 Cancel a Booking

```
POST /api/trainer/bookings/{id}/cancel
Authorization: Bearer {token}
```

> Can only cancel bookings with status `pending` or `confirmed`.

**Response `200`:**
```json
{ "success": true, "message": "Booking cancelled successfully" }
```

**Error `400`:**
```json
{ "success": false, "message": "This booking cannot be cancelled in its current status" }
```

> If the booking was already paid, a refund is automatically triggered via PayTabs.

---

## 8. Payment Flow

### 8.1 How it Works

The payment flow is:

```
1. Trainer accepts booking
       ↓
2. System generates a PayTabs payment page URL
       ↓
3. Mobile app opens the URL in an in-app browser (WebView)
       ↓
4. User completes payment on PayTabs page
       ↓
5. PayTabs sends webhook to backend → booking confirmed
       ↓
6. Backend redirects browser to confirmation URL
       ↓
7. App detects the return URL and closes WebView → shows confirmation
```

---

### 8.2 Initiate Payment

```
POST /api/trainer/bookings/{id}/pay
Authorization: Bearer {token}
```

> The booking must be in `accepted` status (trainer has accepted it).

**Response `200`:**
```json
{
  "success": true,
  "message": "Payment initiated.",
  "data": {
    "payment_url": "https://secure.paytabs.com/payment/page/xxxxxxxxxxx"
  }
}
```

**Mobile implementation:**
1. Open `payment_url` in an in-app WebView / browser
2. Monitor the URL changes
3. When the URL matches `{FRONTEND_URL}/trainers/booking-confirmation?booking_id=42&status=success` → payment successful, close browser, refresh booking
4. When URL matches `?status=failed` → show error

---

### 8.3 Payment Status

After payment, poll or observe the booking status:
- `payment_status: "paid"` + `status: "confirmed"` → success
- `payment_status: "failed"` + `status: "cancelled"` → declined

---

### 8.4 Booking Status Lifecycle

```
         Client books
              │
           pending
              │
     Trainer accepts (manually)
              │
           accepted  ──── Trainer rejects ──► cancelled
              │
     Client initiates payment
              │
        [PayTabs page]
              │
      ┌───────┴────────┐
   Payment OK      Payment fails
      │                 │
   confirmed         cancelled
      │
  Trainer starts
      │
  in_progress
      │
  Trainer completes
      │
  completed ──────────► Client can leave review
```

**Status values reference:**

| Status | Description | Can cancel? | Can review? |
|---|---|---|---|
| `pending` | Awaiting trainer acceptance | Yes (client) | No |
| `accepted` | Trainer accepted, awaiting payment | Yes (client) | No |
| `confirmed` | Payment received | Yes (client, triggers refund) | No |
| `in_progress` | Session in progress | No | No |
| `completed` | Session done | No | Yes |
| `cancelled` | Cancelled by any party | No | No |

---

## 9. Session Lifecycle (Trainer Side)

### 9.1 View My Sessions

```
GET /api/trainer/sessions
Authorization: Bearer {trainer_token}
```

**Query parameters:** `status`, `upcoming`, `per_page` (same as client bookings)

**Response:** Same envelope as client bookings, but `user` object is the client who booked.

---

### 9.2 Accept a Booking

```
POST /api/trainer/sessions/{id}/accept
Authorization: Bearer {trainer_token}
```

> Booking must be in `pending` status.

**Response `200`:**
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

---

### 9.3 Reject a Booking

```
POST /api/trainer/sessions/{id}/reject
Authorization: Bearer {trainer_token}
Content-Type: application/json
```

**Request:**
```json
{
  "reason": "I have a prior commitment on this date."
}
```

**Response `200`:**
```json
{
  "success": true,
  "message": "Booking rejected. Client has been notified.",
  "data": { "booking_id": 42, "status": "cancelled" }
}
```

---

### 9.4 Start a Session

```
POST /api/trainer/sessions/{id}/start
Authorization: Bearer {trainer_token}
```

> Booking must be `confirmed`.

**Response `200`:**
```json
{ "success": true, "message": "Session marked as in progress" }
```

---

### 9.5 Complete a Session

```
POST /api/trainer/sessions/{id}/complete
Authorization: Bearer {trainer_token}
```

> Booking must be `in_progress`.

**Response `200`:**
```json
{ "success": true, "message": "Session marked as completed" }
```

> Completing a session triggers a push notification to the client asking them to leave a review.

---

## 10. Reviews, Comments & Social

### 10.1 Submit a Review

```
POST /api/trainer/bookings/{booking_id}/review
Authorization: Bearer {token}
Content-Type: application/json
```

> Only allowed when `can_review = true` (session completed, no existing review).

**Request:**
```json
{
  "rating": 5,
  "comment": "Khalid is an exceptional coach. Very patient and thorough."
}
```

**Field rules:**

| Field | Rule |
|---|---|
| `rating` | Required, integer, 1–5 |
| `comment` | Optional, string, max 2000 |

**Response `201`:**
```json
{
  "success": true,
  "message": "Review submitted successfully."
}
```

**Error `400` — already reviewed:**
```json
{ "success": false, "message": "You have already reviewed this session." }
```

---

### 10.2 Post a Comment

```
POST /api/trainers/{trainer_id}/comments
Authorization: Bearer {token}
Content-Type: application/json
```

**Request:**
```json
{
  "content": "Looking forward to booking a session!",
  "parent_id": null
}
```

> `parent_id` is optional — set it to reply to another comment.
> Comments require admin approval before being publicly visible.

**Response `201`:** `{ "success": true, "message": "Comment submitted and pending approval." }`

---

### 10.3 Like / Unlike a Trainer

```
POST /api/trainers/{id}/like
Authorization: Bearer {token}
```

**Response `200` (liked):**
```json
{ "success": true, "message": "Trainer liked.", "liked": true, "likes_count": 36 }
```

**Response `200` (unliked — toggle):**
```json
{ "success": true, "message": "Like removed.", "liked": false, "likes_count": 35 }
```

---

### 10.4 Favorite / Unfavorite a Trainer

```
POST /api/trainers/{id}/favorite
Authorization: Bearer {token}
```

**Response `200`:**
```json
{ "success": true, "message": "Added to favorites.", "favorited": true }
```

---

### 10.5 My Favorite Trainers

```
GET /api/user/trainer-favorites
Authorization: Bearer {token}
```

**Response `200`:** Paginated list of favorited trainers (same structure as trainer list).

---

## 11. Schedule Management (Trainer)

### 11.1 Get My Schedule

```
GET /api/trainer/schedule
Authorization: Bearer {token}
```

**Response `200`:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "day_of_week": 0,
      "day_name": "Sunday",
      "day_name_ar": "الأحد",
      "start_time": "08:00",
      "end_time": "16:00",
      "is_available": true
    },
    {
      "id": 2,
      "day_of_week": 1,
      "day_name": "Monday",
      "day_name_ar": "الإثنين",
      "start_time": "08:00",
      "end_time": "16:00",
      "is_available": true
    }
  ]
}
```

**Day of week mapping:**

| Value | English | Arabic |
|---|---|---|
| `0` | Sunday | الأحد |
| `1` | Monday | الإثنين |
| `2` | Tuesday | الثلاثاء |
| `3` | Wednesday | الأربعاء |
| `4` | Thursday | الخميس |
| `5` | Friday | الجمعة |
| `6` | Saturday | السبت |

---

### 11.2 Set / Update Weekly Schedule

```
POST /api/trainer/schedule
Authorization: Bearer {token}
Content-Type: application/json
```

**Request:**
```json
{
  "schedule": [
    { "day_of_week": 0, "start_time": "08:00", "end_time": "16:00", "is_available": true },
    { "day_of_week": 1, "start_time": "08:00", "end_time": "16:00", "is_available": true },
    { "day_of_week": 4, "start_time": "09:00", "end_time": "14:00", "is_available": true },
    { "day_of_week": 5, "start_time": "00:00", "end_time": "00:00", "is_available": false },
    { "day_of_week": 6, "start_time": "10:00", "end_time": "15:00", "is_available": true }
  ]
}
```

> This is an **upsert** — each entry creates or updates that day's schedule.
> Days not included are left unchanged.
> Set `is_available: false` to mark a day as closed.

**Response `200`:**
```json
{ "success": true, "message": "Schedule updated successfully.", "data": [ ... ] }
```

---

## 12. Training Locations

### 12.1 Add a Location

```
POST /api/trainer/locations
Authorization: Bearer {token}
Content-Type: application/json
```

**Request:**
```json
{
  "location_name": "Al-Naseem Training Circuit",
  "location_name_ar": "حلبة النسيم للتدريب",
  "city_id": 1,
  "latitude": 24.7250,
  "longitude": 46.6900
}
```

**Field rules:**

| Field | Rule |
|---|---|
| `location_name` | Required, string, max 255 |
| `location_name_ar` | Optional, string, max 255 |
| `city_id` | Required, integer, must exist in cities table |
| `latitude` | Optional, numeric, -90 to 90 |
| `longitude` | Optional, numeric, -180 to 180 |

**Response `201`:**
```json
{
  "success": true,
  "message": "Location added successfully.",
  "data": {
    "id": 5,
    "location_name": "Al-Naseem Training Circuit",
    "location_name_ar": "حلبة النسيم للتدريب",
    "latitude": "24.7250000",
    "longitude": "46.6900000",
    "is_available": true,
    "city": { "id": 1, "name": "Riyadh" }
  }
}
```

---

### 12.2 Delete a Location

```
DELETE /api/trainer/locations/{location_id}
Authorization: Bearer {token}
```

**Response `200`:**
```json
{ "success": true, "message": "Location removed successfully" }
```

---

## 13. Error Codes & Validation Rules

### 13.1 Standard Error Response Format

```json
{
  "success": false,
  "message": "Short human-readable description"
}
```

For validation errors (`422`):
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": [
      "The field name is required."
    ],
    "another_field": [
      "The another field must be at most 255 characters."
    ]
  }
}
```

---

### 13.2 Authentication Errors

| HTTP Code | `message` | Cause |
|---|---|---|
| `401` | `"Token has expired"` | JWT is expired — refresh it |
| `401` | `"Token is invalid"` | Corrupted or tampered token |
| `401` | `"Token not provided"` | Missing Authorization header |
| `401` | `"Unauthenticated."` | Generic auth failure |

---

### 13.3 Booking Errors

| HTTP Code | `message` | Cause & Fix |
|---|---|---|
| `400` | `"Trainer is not currently available"` | `is_available = false` — check trainer detail |
| `400` | `"This time slot is already booked"` | Conflict with existing booking — pick another slot |
| `400` | `"Invalid location for this trainer"` | `location_id` doesn't belong to this trainer |
| `400` | `"Booking must be accepted by trainer before payment"` | Status is not `accepted` yet |
| `400` | `"Booking is already paid"` | Payment already completed |
| `400` | `"This booking cannot be cancelled in its current status"` | Status is not `pending` or `confirmed` |
| `400` | `"Only pending bookings can be accepted"` | Trainer tried to accept a non-pending booking |
| `400` | `"Not your booking"` | Wrong user/trainer for this booking |
| `403` | `"Not your session"` | Trainer tried to manage another trainer's session |
| `403` | `"No trainer profile found"` | User doesn't have a trainer profile |
| `404` | `"Booking not found"` | Invalid `booking_id` |
| `404` | `"Trainer not found"` | Trainer not approved or doesn't exist |

---

### 13.4 Trainer Registration Errors

| HTTP Code | `message` | Cause |
|---|---|---|
| `409` | `"You already have a trainer profile"` | User already registered as trainer |
| `422` | Validation errors | Missing required fields (see rules in Section 5.1) |

---

### 13.5 Review Errors

| HTTP Code | `message` | Cause |
|---|---|---|
| `400` | `"You have already reviewed this session."` | Duplicate review attempt |
| `400` | `"This session cannot be reviewed yet."` | Session not completed |
| `403` | `"Not your booking"` | Trying to review another user's booking |

---

### 13.6 Validation Rules Quick Reference

| Field | Min | Max | Format |
|---|---|---|---|
| `name` | 1 | 255 | string |
| `bio` | - | 3000 | string |
| `experience_years` | 0 | 50 | integer |
| `price_per_hour` | 0 | - | decimal |
| `duration_hours` | 1 | 4 | integer |
| `rating` (review) | 1 | 5 | integer |
| `comment` | - | 2000 | string |
| `notes` (booking) | - | 1000 | string |
| `booking_date` | tomorrow | - | `YYYY-MM-DD` |
| `start_time` | - | - | `HH:MM` (24h) |
| Photo upload | - | 2MB | jpg/png/gif/webp |
| Cover upload | - | 5MB | jpg/png/gif/webp |
| Certificate upload | - | 5MB each | jpg/png/pdf, max 10 files |

---

## 14. Complete Flow Diagrams

### 14.1 Become a Trainer Flow

```
User opens "Become a Trainer" screen
        │
        ▼
[Step 1 — Profile Info]
  POST /trainer/upload-photo     → save photo_path
  POST /trainer/upload-cover     → save cover_path
        │
        ▼
[Step 2 — Professional Details]
  POST /trainer/upload-certificates → save cert_paths[]
        │
        ▼
[Step 3 — Submit Registration]
  POST /trainer/register
  {
    name, name_ar, bio, bio_ar,
    specialty, experience_years, price_per_hour,
    certifications, photo_path, cover_path,
    certification_files: [cert_paths]
  }
  → response: { status: "pending" }
        │
        ▼
[Show "Pending Approval" screen]
  GET /trainer/me → poll status
        │
        │ (Admin approves in admin panel)
        │
        ▼
[Step 4 — Setup after Approval]
  POST /trainer/locations         → add training locations
  POST /trainer/schedule          → set weekly working hours
  POST /trainer/profile { is_available: 1 }  → go live
        │
        ▼
[Trainer appears in /api/trainers list]
```

---

### 14.2 Book a Session Flow (Client)

```
Client browses GET /api/trainers
        │
        ▼
Taps trainer → GET /api/trainers/{id}
        │
        ▼
Views availability → GET /api/trainers/{id}/availability
                     ?from_date=...&to_date=...&location_id=...
        │
        ▼
Selects a date + time slot + location
        │
        ▼
POST /api/trainers/{id}/book
{ booking_date, start_time, duration_hours, location_id, session_type, notes }
        │
        ├─ 400 → slot conflict → show error, pick new slot
        ├─ 422 → validation error → show field errors
        └─ 201 → { booking_id, status: "pending" }
        │
        ▼
[Show "Waiting for trainer" screen]
GET /api/trainer/bookings?status=pending → poll
        │
        │ Trainer accepts → status changes to "accepted"
        │
        ▼
POST /api/trainer/bookings/{id}/pay
→ { payment_url }
        │
        ▼
Open WebView with payment_url
        │
        ├─ User completes payment on PayTabs page
        │       │
        │       ▼
        │  PayTabs calls backend webhook
        │  Status → "confirmed", payment_status → "paid"
        │       │
        │       ▼
        │  Browser redirects to ?status=success
        │  App detects URL → close WebView
        │
        └─ User cancels / payment fails
                │
                ▼
           Status → "cancelled", payment_status → "failed"
           Show retry or pick new slot
        │
        ▼
[Show booking confirmation screen]
GET /api/trainer/bookings/{id}
```

---

### 14.3 Session Day Flow (Both Parties)

```
TRAINER SIDE                          CLIENT SIDE
     │                                     │
     │                               [Receives push notification]
     │                               "Your session starts soon"
     │                                     │
GET /trainer/sessions                      │
→ find confirmed session                   │
     │                                     │
POST /trainer/sessions/{id}/start          │
→ status: in_progress                      │
     │                                     │
     │ ← ─ ─ ─ session happens ─ ─ ─ ─ ─ ┤
     │                                     │
POST /trainer/sessions/{id}/complete       │
→ status: completed                        │
     │                                     │
     │                               [Push notification]
     │                               "Rate your session"
     │                                     │
     │                               POST /trainer/bookings/{id}/review
     │                               { rating: 5, comment: "..." }
     │                                     │
     │                               [Review pending admin approval]
```

---

### 14.4 Upload Gallery Flow (Trainer)

```
Trainer taps "Add to Gallery"
        │
        ▼
POST /trainer/gallery           ← (coming in next API version)
Content-Type: multipart/form-data
{ images[]: [file1, file2, ...] }
        │
        ▼
Response: { gallery: [ { id, url, thumbnail_url } ] }
        │
        ▼
Gallery appears on trainer public profile
GET /api/trainers/{id} → data.gallery[]
```

---

## 15. Localization & Language

### Language Header

Send the `Accept-Language` header to get localized content:

```
Accept-Language: ar    → Arabic content
Accept-Language: en    → English content (default)
```

### Localized Fields

When the language is Arabic, the following accessor fields return the Arabic version automatically:

| API Field | EN field | AR field |
|---|---|---|
| `localized_name` | `name` | `name_ar` |
| `localized_bio` | `bio` | `bio_ar` |

> **For the mobile developer:** Always render **both** `name` and `name_ar` in your models and let the UI pick based on app locale. Do not rely solely on `localized_name` — it changes based on the server's active locale, not the app's.

---

### Date & Time Format

All dates returned by the API are in **ISO 8601** format:
- Date: `"2026-06-21"` (`YYYY-MM-DD`)
- Time: `"10:00:00"` (`HH:MM:SS`) — use only `HH:MM` in requests
- DateTime: `"2026-06-21T10:00:00.000000Z"` (UTC)

> Convert all datetimes to the user's local timezone for display. The API stores and returns times in UTC.

---

### Currency

All prices are in **SAR (Saudi Riyals)** as a decimal string: `"150.00"`.
Display format: `150.00 SAR` or `١٥٠ ريال` in Arabic.

---

## Appendix A — Endpoint Quick Reference

### Public Endpoints (No Auth)

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/trainers` | List trainers (with filters) |
| `GET` | `/api/trainers/{id}` | Trainer detail |
| `GET` | `/api/trainers/{id}/availability` | Time slot availability |
| `GET` | `/api/trainers/{id}/reviews` | Trainer reviews |
| `GET` | `/api/trainers/{id}/comments` | Trainer comments |
| `GET` | `/api/trainer-locations` | All training locations |

### Auth Required — Client

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/trainers/{id}/book` | Book a session |
| `GET` | `/api/trainer/bookings` | My bookings |
| `POST` | `/api/trainer/bookings/{id}/cancel` | Cancel booking |
| `POST` | `/api/trainer/bookings/{id}/pay` | Initiate payment |
| `POST` | `/api/trainer/bookings/{id}/review` | Submit review |
| `POST` | `/api/trainers/{id}/like` | Toggle like |
| `POST` | `/api/trainers/{id}/favorite` | Toggle favorite |
| `GET` | `/api/user/trainer-favorites` | My favorites |
| `POST` | `/api/trainers/{id}/comments` | Post comment |

### Auth Required — Trainer (Provider)

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/trainer/register` | Register as trainer |
| `GET` | `/api/trainer/me` | My trainer profile |
| `POST` | `/api/trainer/profile` | Update profile |
| `POST` | `/api/trainer/upload-photo` | Upload profile photo |
| `DELETE` | `/api/trainer/upload-photo/{filename}` | Delete profile photo |
| `POST` | `/api/trainer/upload-cover` | Upload cover photo |
| `DELETE` | `/api/trainer/upload-cover/{filename}` | Delete cover photo |
| `POST` | `/api/trainer/upload-certificates` | Upload certificates |
| `DELETE` | `/api/trainer/upload-certificates/{filename}` | Delete certificate |
| `GET` | `/api/trainer/schedule` | Get my schedule |
| `POST` | `/api/trainer/schedule` | Set weekly schedule |
| `POST` | `/api/trainer/locations` | Add location |
| `DELETE` | `/api/trainer/locations/{id}` | Delete location |
| `GET` | `/api/trainer/sessions` | My sessions (incoming bookings) |
| `POST` | `/api/trainer/sessions/{id}/accept` | Accept booking |
| `POST` | `/api/trainer/sessions/{id}/reject` | Reject booking |
| `POST` | `/api/trainer/sessions/{id}/start` | Start session |
| `POST` | `/api/trainer/sessions/{id}/complete` | Complete session |

### Webhook (Server-to-Server — Do Not Call from App)

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/trainer/payments/callback` | PayTabs payment result webhook |
| `GET` | `/api/trainer/payments/return` | Browser return after payment |

---

## Appendix B — Specialty Values

Current valid values for the `specialty` field:

| Value | English Label | Arabic Label |
|---|---|---|
| `coaching` | Coaching | تدريب |
| `competition` | Competition | منافسة |
| `off-road` | Off-Road | طرق وعرة |
| `street` | Street Riding | قيادة الشوارع |
| `custom` | Custom | مخصص |

> **Note:** These will become dynamic in the next API version. The mobile app should fetch them from `/api/specialties` rather than hardcoding, to ensure compatibility after the upgrade.

---

## Appendix C — Session Type Values

| Value | Description |
|---|---|
| `beginner` | Beginner — fundamentals and safety |
| `intermediate` | Intermediate — advanced techniques |
| `advanced` | Advanced — race prep and high performance |
| `custom` | Custom session — define details in `notes` |

---

*Document maintained by the DabApp Backend Team. For questions or corrections, open an issue in the backend repository.*
