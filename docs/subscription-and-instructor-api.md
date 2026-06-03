# DabApp — Subscription & Riding Instructor API
**Base URL:** `https://your-domain.com/api`  
**Auth:** Bearer token in `Authorization` header  
**Content-Type:** `application/json`

---

## 1. SUBSCRIPTION FLOW

### 1.1 List Subscription Plans
```
GET /subscription-plans?billing_cycle=monthly
```
**Auth:** Not required

**Query params:**

| Param | Type | Required | Description |
|-------|------|----------|-------------|
| billing_cycle | string | No | `monthly` or `yearly` |

**Response 200:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Basic Plan",
      "name_ar": "الخطة الأساسية",
      "price_monthly": 19.00,
      "price_yearly": 190.00,
      "max_services": 5,
      "max_bookings_per_month": 50,
      "features": ["Up to 5 services", "Email support"],
      "is_featured": false
    },
    {
      "id": 2,
      "name": "Business Plan",
      "name_ar": "خطة الأعمال",
      "price_monthly": 29.00,
      "price_yearly": 290.00,
      "max_services": 15,
      "max_bookings_per_month": 200,
      "features": ["Up to 15 services", "Priority support"],
      "is_featured": true
    },
    {
      "id": 3,
      "name": "Enterprise Plan",
      "name_ar": "الخطة المؤسسية",
      "price_monthly": 39.00,
      "price_yearly": 390.00,
      "max_services": null,
      "max_bookings_per_month": null,
      "features": ["Unlimited services", "24/7 support"],
      "is_featured": false
    }
  ]
}
```

---

### 1.2 Subscribe (Full Onboarding)
```
POST /subscriptions/subscribe
```
**Auth:** Required

**Request body — ALL fields:**

```json
{
  "plan_id": 2,
  "billing_cycle": "monthly",

  "business_name": "Elite Moto Services",
  "business_name_ar": "خدمات موتو إيليت",
  "description": "Professional motorcycle services in Riyadh",
  "description_ar": "خدمات دراجات نارية احترافية في الرياض",
  "phone": "+966501234567",
  "email": "contact@elitemoto.com",
  "address": "King Fahd Road, Riyadh",
  "address_ar": "طريق الملك فهد، الرياض",
  "city_id": 1,
  "country_id": 1,
  "latitude": 24.7136,
  "longitude": 46.6753,

  "price_per_hour": 150,
  "price_per_mission": 200,

  "service_category_ids": [1, 2],

  "working_hours": [
    { "day_of_week": 0, "is_open": false },
    { "day_of_week": 1, "is_open": true, "open_time": "08:00", "close_time": "18:00" },
    { "day_of_week": 2, "is_open": true, "open_time": "08:00", "close_time": "18:00" },
    { "day_of_week": 3, "is_open": true, "open_time": "08:00", "close_time": "18:00" },
    { "day_of_week": 4, "is_open": true, "open_time": "08:00", "close_time": "18:00" },
    { "day_of_week": 5, "is_open": true, "open_time": "08:00", "close_time": "14:00" },
    { "day_of_week": 6, "is_open": false }
  ]
}
```

**Fields reference:**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| plan_id | integer | ✅ | ID from `/subscription-plans` |
| billing_cycle | string | ✅ | `monthly` or `yearly` |
| business_name | string | ✅ | Provider business name (EN) |
| business_name_ar | string | ✅ | Provider business name (AR) |
| phone | string | ✅ | Provider contact phone |
| description | string | No | Business description (EN) |
| description_ar | string | No | Business description (AR) |
| email | string | No | Business email |
| address | string | No | Business address (EN) |
| address_ar | string | No | Business address (AR) |
| city_id | integer | No | City ID |
| country_id | integer | No | Country ID |
| latitude | decimal | No | GPS latitude |
| longitude | decimal | No | GPS longitude |
| price_per_hour | decimal | ✅ | Default hourly rate (SAR) |
| price_per_mission | decimal | ✅ | Default per-mission rate (SAR) |
| service_category_ids | array | ✅ | Min 1. Categories: 1=Transport, 2=Tow, 3=Instructor, 4=Wash, 5=Workshop |
| working_hours | array | ✅ | Min 1 entry. Send all 7 days. day_of_week: 0=Sunday … 6=Saturday |
| logo | string (URL) | No | Logo image URL — must be a valid URL |
| cover_image | string (URL) | No | Cover image URL — must be a valid URL |
| images | array of URLs | No | Gallery images |

**Response 200:**
```json
{
  "success": true,
  "message": "Payment initiated",
  "data": {
    "payment_url": "https://secure.paytabs.com/payment/wr/...",
    "subscription_id": 1,
    "transaction_id": 1
  }
}
```

> ⚠️ **Important:** Redirect the user to `payment_url` to complete payment on PayTabs. After payment, the subscription becomes `active` and the provider account is activated.

---

### 1.3 My Subscription Status
```
GET /my-subscription
```
**Auth:** Required

**Response 200 — Active subscription:**
```json
{
  "success": true,
  "data": {
    "has_subscription": true,
    "subscription": {
      "id": 1,
      "status": "active",
      "plan": {
        "id": 2,
        "name": "Business Plan",
        "name_ar": "خطة الأعمال"
      },
      "billing_cycle": "monthly",
      "current_price": 29.00,
      "current_period_start": "2026-06-02",
      "current_period_end": "2026-07-02",
      "next_billing_date": "2026-07-02",
      "days_until_renewal": 30,
      "auto_renew": true,
      "on_trial": false,
      "trial_ends_at": null
    },
    "usage": {
      "services_used": 2,
      "services_quota": 13,
      "bookings_this_month": 5,
      "bookings_quota": 195
    },
    "features": {
      "priority_support": true,
      "analytics_access": true
    }
  }
}
```

**Response 200 — No subscription:**
```json
{
  "success": true,
  "data": {
    "has_subscription": false,
    "message": "No active subscription found"
  }
}
```

**Subscription statuses:**

| Status | Meaning |
|--------|---------|
| `pending` | Waiting for payment |
| `active` | Paid and active |
| `cancelled` | Cancelled (access until period end) |
| `expired` | Period ended |
| `payment_failed` | Payment failed |

---

### 1.4 Cancel Subscription
```
POST /subscriptions/cancel
```
**Auth:** Required

```json
{
  "reason": "No longer needed"
}
```

**Response 200:**
```json
{
  "success": true,
  "message": "Subscription cancelled successfully. You will have access until 2026-07-02"
}
```

---

### 1.5 Subscription Transactions
```
GET /subscription/transactions
```
**Auth:** Required

---

## 2. SERVICE CATEGORIES

```
GET /service-categories
```
**Auth:** Not required

| ID | Slug | Name EN | Name AR |
|----|------|---------|---------|
| 1 | bike-transport | Bike Transport | نقل الدراجات |
| 2 | tow-service | Tow Service | خدمة السحب |
| 3 | riding-instructor | Riding Instructor | مدرب قيادة |
| 4 | bike-wash | Bike Wash | غسيل الدراجات |
| 5 | maintenance-workshops | Maintenance Workshops | ورش الصيانة |

---

## 3. RIDING INSTRUCTOR FLOW

### PROVIDER SIDE

#### 3.1 Create Instructor Profile
```
POST /provider/riding-instructors
```
**Auth:** Required (must have active subscription)

```json
{
  "instructor_name": "Khalid Al-Rashidi",
  "instructor_name_ar": "خالد الرشيدي",
  "bio": "Certified motorcycle instructor with 10 years of experience.",
  "bio_ar": "مدرب دراجات نارية معتمد بخبرة 10 سنوات.",
  "experience_years": 10,
  "certifications": ["FIM Level 2", "Saudi Motorsport Federation"]
}
```

| Field | Type | Required |
|-------|------|----------|
| instructor_name | string | ✅ |
| instructor_name_ar | string | No |
| bio | string | No |
| bio_ar | string | No |
| experience_years | integer | ✅ |
| certifications | array of strings | No |

**Response 201:**
```json
{
  "success": true,
  "message": "Riding instructor added successfully",
  "data": {
    "id": 1,
    "provider_id": 1,
    "instructor_name": "Khalid Al-Rashidi",
    "instructor_name_ar": "خالد الرشيدي",
    "bio": "...",
    "experience_years": 10,
    "certifications": ["FIM Level 2", "Saudi Motorsport Federation"],
    "is_available": true,
    "rating_average": 0,
    "total_sessions": 0
  }
}
```

> Save `data.id` as `instructor_id` for next steps.

---

#### 3.2 Create Riding Instructor Service
```
POST /my-services
```
**Auth:** Required

```json
{
  "category_id": 3,
  "name": "Riding Instructor Sessions",
  "name_ar": "جلسات مدرب القيادة",
  "description": "One-on-one riding sessions for all levels",
  "description_ar": "جلسات قيادة فردية لجميع المستويات",
  "price": 200,
  "price_type": "per_hour",
  "currency": "SAR",
  "is_available": true,
  "requires_booking": true
}
```

> ⚠️ `category_id: 3` is mandatory for instructor services. The schedule endpoint (3.3) requires this service to exist.

---

#### 3.3 Set Instructor Schedule
```
POST /provider/riding-instructors/{instructor_id}/schedule
```
**Auth:** Required

```json
{
  "schedule": [
    { "day_of_week": 1, "start_time": "08:00", "end_time": "18:00", "is_available": true },
    { "day_of_week": 2, "start_time": "08:00", "end_time": "18:00", "is_available": true },
    { "day_of_week": 3, "start_time": "08:00", "end_time": "18:00", "is_available": true },
    { "day_of_week": 4, "start_time": "08:00", "end_time": "18:00", "is_available": true },
    { "day_of_week": 5, "start_time": "08:00", "end_time": "14:00", "is_available": true }
  ]
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| schedule | array | ✅ | Min 1 entry |
| day_of_week | integer | ✅ | 0=Sunday … 6=Saturday |
| start_time | string | ✅ | Format `HH:MM` |
| end_time | string | ✅ | Format `HH:MM`, must be after start_time |
| is_available | boolean | No | Default: true |

**Response 200:**
```json
{
  "success": true,
  "message": "Schedule updated successfully",
  "data": {
    "service_id": 1,
    "updated_days": 5,
    "schedule": [...]
  }
}
```

---

#### 3.4 Get Instructor Schedule
```
GET /provider/riding-instructors/{instructor_id}/schedule
```
**Auth:** Required

---

### USER SIDE

#### 3.5 List Instructors
```
GET /riding-instructors?is_available=1&sort_by=rating&per_page=10
```
**Auth:** Required

**Query params:**

| Param | Type | Description |
|-------|------|-------------|
| is_available | boolean | Filter available instructors |
| sort_by | string | `rating` or `experience` |
| min_rating | decimal | Minimum rating (e.g. 4) |
| city_id | integer | Filter by city |
| search | string | Search by name |
| per_page | integer | Default: 20 |

**Response 200:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "instructor_name": "Khalid Al-Rashidi",
        "instructor_name_ar": "خالد الرشيدي",
        "bio": "...",
        "experience_years": 10,
        "rating_average": 4.8,
        "total_sessions": 120,
        "is_available": true,
        "certifications": ["FIM Level 2"],
        "locations": [
          {
            "id": 1,
            "location_name": "Training Circuit",
            "location_name_ar": "حلبة التدريب",
            "latitude": 24.7136,
            "longitude": 46.6753
          }
        ]
      }
    ],
    "total": 1,
    "per_page": 10,
    "current_page": 1
  }
}
```

---

#### 3.6 Instructor Profile
```
GET /riding-instructors/{instructor_id}
```
**Auth:** Required

---

#### 3.7 Training Locations
```
GET /instructor-locations?instructor_id={instructor_id}
```
**Auth:** Required

**Response 200:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "instructor_id": 1,
      "location_name": "Training Circuit",
      "location_name_ar": "حلبة التدريب",
      "latitude": 24.7136,
      "longitude": 46.6753,
      "city_id": 1,
      "is_available": true
    }
  ]
}
```

> Save `data[0].id` as `location_id` for booking.

---

#### 3.8 Check Availability
```
GET /riding-instructors/{instructor_id}/availability
```
**Auth:** Required

**Query params:**

| Param | Type | Required | Notes |
|-------|------|----------|-------|
| from_date | date | ✅ | Format `YYYY-MM-DD` |
| to_date | date | ✅ | Format `YYYY-MM-DD` |
| location_id | integer | No | Filter by training location |

**Response 200:**
```json
{
  "success": true,
  "data": {
    "instructor": {
      "id": 1,
      "name": "Khalid Al-Rashidi"
    },
    "available_slots": [
      {
        "date": "2026-07-01",
        "day_name": "Tuesday",
        "time_slots": ["08:00-10:00", "10:00-12:00", "14:00-16:00", "16:00-18:00"]
      },
      {
        "date": "2026-07-02",
        "day_name": "Wednesday",
        "time_slots": ["08:00-10:00", "10:00-12:00"]
      }
    ],
    "schedule_source": "configured",
    "period": {
      "from": "2026-07-01",
      "to": "2026-07-07"
    }
  }
}
```

> `time_slots` are 2-hour blocks. A slot disappears once booked.

---

#### 3.9 Book a Session
```
POST /riding-instructors/{instructor_id}/book-session
```
**Auth:** Required

```json
{
  "booking_date": "2026-07-01",
  "start_time": "08:00",
  "duration_hours": 2,
  "location_id": 1,
  "session_type": "beginner",
  "notes": "First time rider, never ridden before"
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| booking_date | date | ✅ | Format `YYYY-MM-DD` |
| start_time | string | ✅ | Format `HH:MM` — must match an available slot |
| duration_hours | integer | No | Default: 1. Use 2 for a 2-hour slot |
| location_id | integer | ✅ | From `/instructor-locations` |
| session_type | string | No | `beginner`, `intermediate`, `advanced` |
| notes | string | No | Special instructions |

**Response 201:**
```json
{
  "success": true,
  "data": {
    "booking": {
      "id": 1,
      "status": "pending",
      "booking_date": "2026-07-01",
      "start_time": "08:00",
      "end_time": "10:00",
      "session_type": "beginner",
      "notes": "First time rider"
    },
    "total_price": 400,
    "currency": "SAR"
  }
}
```

**Error 400 — Slot already booked:**
```json
{
  "success": false,
  "message": "This time slot is already booked"
}
```

---

#### 3.10 Cancel a Session
```
POST /bookings/{session_booking_id}/cancel
```
**Auth:** Required

```json
{
  "cancellation_reason": "Work commitment conflict"
}
```

---

#### 3.11 Rate a Session (after completion)
```
POST /bookings/{session_booking_id}/review
```
**Auth:** Required

```json
{
  "rating": 5,
  "comment": "Outstanding instructor! Very patient and professional.",
  "comment_ar": "مدرب متميز! صبور ومحترف جداً."
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| rating | integer | ✅ | 1 to 5 |
| comment | string | No | Review in English |
| comment_ar | string | No | Review in Arabic |

---

## 4. COMPLETE FLOWS

### Provider onboarding flow
```
1. GET  /subscription-plans              → user picks a plan
2. POST /subscriptions/subscribe         → send all business info + categories + hours + pricing
3.      Redirect to payment_url          → user pays on PayTabs
4. GET  /my-subscription                 → verify status = "active"
5. POST /provider/riding-instructors     → create instructor profile
6. POST /my-services                     → create service (category_id: 3)
7. POST /provider/riding-instructors/{id}/schedule → set weekly schedule
```

### User booking flow
```
1. GET  /riding-instructors              → browse instructors
2. GET  /riding-instructors/{id}         → view instructor profile
3. GET  /instructor-locations            → get training locations
4. GET  /riding-instructors/{id}/availability?from_date=...&to_date=...&location_id=...
5. POST /riding-instructors/{id}/book-session → book a free slot
6. POST /bookings/{id}/review            → rate after completion
```

---

## 5. WORKING DAYS REFERENCE

| day_of_week | Day |
|-------------|-----|
| 0 | Sunday |
| 1 | Monday |
| 2 | Tuesday |
| 3 | Wednesday |
| 4 | Thursday |
| 5 | Friday |
| 6 | Saturday |

---

## 6. ERROR RESPONSES

All errors follow this format:

```json
{
  "success": false,
  "message": "Error description"
}
```

Validation errors:
```json
{
  "success": false,
  "errors": {
    "business_name": ["The business name field is required."],
    "plan_id": ["The selected plan id is invalid."]
  }
}
```

| HTTP Code | Meaning |
|-----------|---------|
| 200 | Success |
| 201 | Created |
| 400 | Bad request (conflict, already exists) |
| 401 | Unauthenticated |
| 403 | Forbidden (not a provider, not your resource) |
| 404 | Not found |
| 422 | Validation error |
| 500 | Server error |
