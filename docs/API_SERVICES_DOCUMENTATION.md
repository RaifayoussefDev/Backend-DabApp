# DabApp — Services API Documentation
> For Frontend Developers · Base URL: `https://your-server.com` · All responses are JSON

---

## Table of Contents
1. [Authentication](#1-authentication)
2. [Service Categories](#2-service-categories)
3. [Service Providers](#3-service-providers)
4. [Services (General)](#4-services-general)
5. [Tow Service](#5-tow-service)
6. [Bike Transport](#6-bike-transport)
7. [Riding Instructor](#7-riding-instructor)
8. [Bike Wash & Maintenance Workshops](#8-bike-wash--maintenance-workshops)
9. [Bookings](#9-bookings)
10. [Reviews](#10-reviews)
11. [Favorites](#11-favorites)
12. [Chat Sessions](#12-chat-sessions)
13. [Provider — My Services](#13-provider--my-services)
14. [Provider — Working Hours](#14-provider--working-hours)
15. [Subscriptions (Provider)](#15-subscriptions-provider)
16. [Error Responses](#16-error-responses)

---

## 1. Authentication

All protected endpoints require the JWT token in the `Authorization` header.

```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

### Login
```
POST /api/login
```
**Body:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```
**Response `200`:**
```json
{
  "token": "eyJ0eXAiOiJKV1Qi...",
  "user": {
    "id": 1,
    "first_name": "Ahmed",
    "last_name": "Ali",
    "email": "user@example.com",
    "phone": "+966500000000",
    "avatar": "https://...",
    "role": "user"
  }
}
```

### Refresh Token
```
POST /api/refresh
Authorization: Bearer {token}
```

### Logout
```
POST /api/logout
Authorization: Bearer {token}
```

---

## 2. Service Categories

> Public — No auth required

### List All Categories
```
GET /api/service-categories
```
**Query Params:**
| Param | Type | Description |
|---|---|---|
| `is_active` | `0\|1` | Filter active only |
| `with_services_count` | `boolean` | Include service count per category |

**Response `200`:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Bike Transport",
      "name_ar": "نقل الدراجات",
      "slug": "bike-transport",
      "description": "Secure carrier service for moving your motorcycle safely",
      "description_ar": "خدمة نقل آمنة لنقل دراجتك النارية بأمان",
      "icon": "truck",
      "color": "#FF5722",
      "is_active": true,
      "order_position": 1,
      "services_count": 12
    },
    {
      "id": 2,
      "name": "Tow Service",
      "name_ar": "خدمة السحب",
      "slug": "tow-service",
      "color": "#F44336",
      "icon": "tow-truck",
      "is_active": true,
      "order_position": 2,
      "services_count": 8
    },
    {
      "id": 3,
      "name": "Riding Instructor",
      "name_ar": "مدرب قيادة",
      "slug": "riding-instructor",
      "color": "#2196F3",
      "is_active": true,
      "order_position": 3
    },
    {
      "id": 4,
      "name": "Bike Wash",
      "name_ar": "غسيل الدراجات",
      "slug": "bike-wash",
      "color": "#00BCD4",
      "is_active": true,
      "order_position": 4
    },
    {
      "id": 5,
      "name": "Maintenance Workshops",
      "name_ar": "ورش الصيانة",
      "slug": "maintenance-workshops",
      "color": "#9C27B0",
      "is_active": true,
      "order_position": 5
    }
  ]
}
```

### Get Category by ID
```
GET /api/service-categories/{id}
```
**Query Params:**
| Param | Type | Description |
|---|---|---|
| `with_services` | `boolean` | Include services list |

### Get Category by Slug
```
GET /api/service-categories/slug/{slug}
```
Slugs: `bike-transport` · `tow-service` · `riding-instructor` · `bike-wash` · `maintenance-workshops`

---

## 3. Service Providers

> Public — No auth required for listing

### List Providers
```
GET /api/service-providers
```
**Query Params:**
| Param | Type | Description |
|---|---|---|
| `city_id` | `integer` | Filter by city |
| `category_id` | `integer` | Filter by service category |
| `search` | `string` | Search by name |
| `min_rating` | `number` | Minimum rating (1-5) |
| `is_verified` | `0\|1` | Verified providers only |
| `per_page` | `integer` | Default: 15 |

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "business_name": "Elite Bike Transport",
        "business_name_ar": "نقل الدراجات الممتاز",
        "logo": "https://...",
        "cover_image": "https://...",
        "rating_average": 4.7,
        "reviews_count": 128,
        "completed_orders": 350,
        "is_verified": true,
        "is_active": true,
        "city_id": 1,
        "address": "King Fahd Road, Riyadh",
        "phone": "+966500000000",
        "email": "contact@elitebike.com"
      }
    ],
    "current_page": 1,
    "total": 45,
    "per_page": 15
  }
}
```

### Get Provider Details
```
GET /api/service-providers/{id}
```
**Response `200`:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "business_name": "Elite Bike Transport",
    "description": "Professional transport services...",
    "description_ar": "خدمات نقل احترافية...",
    "logo": "https://...",
    "cover_image": "https://...",
    "images": ["https://...", "https://..."],
    "rating_average": 4.7,
    "reviews_count": 128,
    "completed_orders": 350,
    "is_verified": true,
    "phone": "+966500000000",
    "email": "contact@elitebike.com",
    "address": "King Fahd Road, Riyadh",
    "latitude": 24.7136,
    "longitude": 46.6753,
    "working_hours": [
      { "day_of_week": 0, "is_open": false },
      { "day_of_week": 1, "is_open": true, "open_time": "08:00", "close_time": "18:00" }
    ],
    "services": [ ... ],
    "categories": [ ... ]
  }
}
```

### Nearby Providers
```
GET /api/service-providers/nearby
```
**Query Params:**
| Param | Type | Required | Description |
|---|---|---|---|
| `latitude` | `number` | ✅ | User latitude |
| `longitude` | `number` | ✅ | User longitude |
| `radius` | `integer` | — | Km, default 10, max 100 |
| `category_id` | `integer` | — | Filter by category |

### Become a Provider
```
POST /api/become-provider
Authorization: Bearer {token}
```
**Body:**
```json
{
  "business_name": "My Bike Service",
  "business_name_ar": "خدمة دراجتي",
  "phone": "+966500000000",
  "address": "123 Main St, Riyadh",
  "city_id": 1,
  "latitude": 24.7136,
  "longitude": 46.6753,
  "logo": "https://...",
  "cover_image": "https://..."
}
```

### Provider Status (Check onboarding progress)
```
GET /api/provider/status
Authorization: Bearer {token}
```
**Response `200`:**
```json
{
  "success": true,
  "data": {
    "is_provider": true,
    "has_active_subscription": false,
    "is_active": false,
    "is_verified": false,
    "next_action": "subscribe",
    "provider_id": 1
  }
}
```
> `next_action` values: `complete_profile` · `subscribe` · `active`

### My Provider Profile
```
GET /api/provider/my-profile
Authorization: Bearer {token}
```

### Update Provider Profile
```
POST /api/provider/profile
Authorization: Bearer {token}
Content-Type: multipart/form-data
```
**Body (all optional):**
```json
{
  "business_name": "Updated Name",
  "business_name_ar": "الاسم المحدث",
  "description": "...",
  "description_ar": "...",
  "phone": "+966500000000",
  "email": "new@email.com",
  "address": "New Address",
  "city_id": 2,
  "latitude": 24.7,
  "longitude": 46.6,
  "logo": "file or URL",
  "cover_image": "file or URL"
}
```

---

## 4. Services (General)

> All 5 service categories share this generic Services API.
> Filter by `category_id` to get services for a specific category.

### List Services
```
GET /api/services
```
**Query Params:**
| Param | Type | Description |
|---|---|---|
| `category_id` | `integer` | **Required to filter by service type** |
| `provider_id` | `integer` | Filter by provider |
| `city_id` | `integer` | Filter by city |
| `price_type` | `fixed\|per_hour\|per_km\|custom` | Price model |
| `min_price` | `number` | Minimum price |
| `max_price` | `number` | Maximum price |
| `search` | `string` | Search by name |
| `is_available` | `0\|1` | Available now |
| `sort_by` | `price\|rating\|created_at` | Sort field |
| `sort_order` | `asc\|desc` | Sort direction |
| `per_page` | `integer` | Default: 15 |

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "name": "Professional Bike Transport",
        "name_ar": "نقل الدراجات الاحترافي",
        "description": "Safe and secure transport...",
        "description_ar": "نقل آمن...",
        "image": "https://...",
        "price": 150.00,
        "price_type": "fixed",
        "currency": "SAR",
        "duration_minutes": 60,
        "is_available": true,
        "rating_average": 4.8,
        "reviews_count": 45,
        "category": { "id": 1, "name": "Bike Transport" },
        "provider": {
          "id": 1,
          "business_name": "Elite Bike Transport",
          "rating_average": 4.7,
          "is_verified": true
        },
        "has_online_consultation": false
      }
    ],
    "current_page": 1,
    "total": 30,
    "per_page": 15
  }
}
```

### Get Service Details
```
GET /api/services/{id}
```
**Response `200`:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Professional Bike Transport",
    "name_ar": "نقل الدراجات الاحترافي",
    "description": "...",
    "image": "https://...",
    "price": 150.00,
    "price_type": "fixed",
    "currency": "SAR",
    "duration_minutes": 60,
    "is_available": true,
    "max_capacity": 1,
    "has_online_consultation": true,
    "consultation_price_per_session": 50.00,
    "consultation_email": "expert@provider.com",
    "category": { "id": 1, "name": "Bike Transport", "slug": "bike-transport" },
    "provider": {
      "id": 1,
      "business_name": "Elite Bike Transport",
      "logo": "https://...",
      "rating_average": 4.7,
      "phone": "+966500000000",
      "address": "...",
      "working_hours": [ ... ]
    },
    "schedules": [
      { "day_of_week": 1, "start_time": "08:00", "end_time": "18:00" }
    ],
    "pricing_rules": [
      { "type": "per_km", "price": 5.00 }
    ],
    "required_documents": [
      {
        "id": 1,
        "document_name": "Vehicle Registration",
        "document_name_ar": "تسجيل المركبة",
        "is_required": true
      }
    ],
    "reviews_summary": {
      "average_rating": 4.8,
      "total_reviews": 45,
      "rating_breakdown": {
        "5": 30, "4": 10, "3": 4, "2": 1, "1": 0
      }
    },
    "other_services_from_provider": [ ... ]
  }
}
```

### Search Services
```
POST /api/services/search
```
**Body:**
```json
{
  "query": "bike wash",
  "category_id": 4,
  "city_id": 1,
  "per_page": 10
}
```

---

## 5. Tow Service

> Specific to the **Tow Service** category

### Get Tow Types
```
GET /api/tow-types
```
**Query Params:**
| Param | Type | Description |
|---|---|---|
| `is_active` | `0\|1` | Active types only |

**Response `200`:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Standard Tow",
      "name_ar": "سحب عادي",
      "description": "For standard motorcycles under 500cc",
      "description_ar": "للدراجات العادية أقل من 500cc",
      "icon": "truck",
      "image": "https://...",
      "base_price": 50.00,
      "price_per_km": 3.00,
      "is_active": true,
      "order_position": 1
    },
    {
      "id": 2,
      "name": "Heavy Tow",
      "name_ar": "سحب ثقيل",
      "base_price": 80.00,
      "price_per_km": 5.00
    }
  ]
}
```

### Get Tow Type Details
```
GET /api/tow-types/{id}
```

### Calculate Tow Price
```
POST /api/tow-service/calculate-price
Authorization: Bearer {token}
```
**Body** (use `tow_type_id` for tow type, or `service_id` for provider service):
```json
{
  "tow_type_id": 1,
  "pickup_latitude": 24.7136,
  "pickup_longitude": 46.6753,
  "dropoff_latitude": 24.8000,
  "dropoff_longitude": 46.7000
}
```
**Response `200`:**
```json
{
  "success": true,
  "data": {
    "distance_km": 12.5,
    "base_price": 50.00,
    "distance_price": 37.50,
    "subtotal": 87.50,
    "discount_amount": 0.00,
    "total_price": 87.50,
    "currency": "SAR",
    "estimated_duration_minutes": 25
  }
}
```

### Request a Tow
```
POST /api/tow-service/request
Authorization: Bearer {token}
```
**Body:**
```json
{
  "tow_type_id": 1,
  "pickup_latitude": 24.7136,
  "pickup_longitude": 46.6753,
  "pickup_location": "King Fahd Road, near Starbucks",
  "pickup_city_id": 1,
  "dropoff_latitude": 24.8000,
  "dropoff_longitude": 46.7000,
  "dropoff_location": "My home - Al Olaya District",
  "dropoff_city_id": 1,
  "notes": "Motorcycle is fully loaded",
  "phone": "+966500000000",
  "promo_code": "SAVE10"
}
```
**Response `201`:**
```json
{
  "success": true,
  "data": {
    "booking_id": 45,
    "status": "pending",
    "price": 87.50,
    "currency": "SAR",
    "estimated_duration_minutes": 25
  },
  "message": "Tow service requested successfully"
}
```

### Available Tow Providers
```
GET /api/tow-service/available-providers
Authorization: Bearer {token}
```
**Query Params:**
| Param | Type | Required | Description |
|---|---|---|---|
| `latitude` | `number` | ✅ | Pickup latitude |
| `longitude` | `number` | ✅ | Pickup longitude |
| `tow_type_id` | `integer` | — | Filter by tow type |
| `radius` | `integer` | — | Search radius in km (default 20) |

---

## 6. Bike Transport

> Specific to the **Bike Transport** category
> Routes are scheduled trips with available slots.

### List Transport Routes
```
GET /api/transport-routes
```
**Query Params:**
| Param | Type | Description |
|---|---|---|
| `departure_city` | `string` | Departure city name |
| `arrival_city` | `string` | Arrival city name |
| `route_date` | `YYYY-MM-DD` | Exact date filter |
| `from_date` | `YYYY-MM-DD` | Date range start |
| `to_date` | `YYYY-MM-DD` | Date range end |
| `min_slots_available` | `integer` | Minimum slots needed |
| `per_page` | `integer` | Default: 15 |

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "route_date": "2026-07-01",
        "departure_point": "Riyadh",
        "departure_point_ar": "الرياض",
        "arrival_point": "Jeddah",
        "arrival_point_ar": "جدة",
        "departure_time": "08:00",
        "arrival_time": "14:00",
        "available_slots": 10,
        "booked_slots": 4,
        "remaining_slots": 6,
        "price_per_slot": 200.00,
        "is_active": true,
        "provider": {
          "id": 1,
          "business_name": "Express Bike Transport",
          "rating_average": 4.6
        },
        "stops": [
          {
            "stop_order": 1,
            "location_name": "Riyadh Central Station",
            "city": { "id": 1, "name": "Riyadh" }
          },
          {
            "stop_order": 2,
            "location_name": "Jeddah Corniche",
            "city": { "id": 2, "name": "Jeddah" }
          }
        ]
      }
    ],
    "current_page": 1,
    "total": 12
  }
}
```

### Get Route Details
```
GET /api/transport-routes/{id}
```

### Book Slots on a Route
```
POST /api/transport-routes/{id}/book
Authorization: Bearer {token}
```
**Body:**
```json
{
  "slots_count": 2,
  "pickup_stop_id": 1,
  "dropoff_stop_id": 2,
  "notes": "Handle with care",
  "promo_code": "RIDE20"
}
```
**Response `201`:**
```json
{
  "success": true,
  "data": {
    "booking_id": 46,
    "slots_booked": 2,
    "total_price": 400.00,
    "currency": "SAR",
    "route": {
      "date": "2026-07-01",
      "departure": "Riyadh",
      "arrival": "Jeddah"
    }
  },
  "message": "Route booked successfully"
}
```

### Check Required Documents for a Service
```
GET /api/services/{service_id}/required-documents
```
**Response `200`:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "document_name": "Vehicle Registration",
      "document_name_ar": "تسجيل المركبة",
      "description": "Current vehicle registration certificate",
      "is_required": true
    },
    {
      "id": 2,
      "document_name": "Insurance Certificate",
      "document_name_ar": "شهادة التأمين",
      "is_required": true
    }
  ]
}
```

---

## 7. Riding Instructor

> Specific to the **Riding Instructor** category

### List Instructors
```
GET /api/riding-instructors
```
**Query Params:**
| Param | Type | Description |
|---|---|---|
| `city_id` | `integer` | Filter by city |
| `min_experience_years` | `integer` | Minimum years of experience |
| `min_rating` | `number` | Minimum rating (1-5) |
| `is_available` | `0\|1` | Currently available |
| `search` | `string` | Search by name |
| `sort_by` | `rating\|experience\|sessions` | Sort by field |
| `per_page` | `integer` | Default: 15 |

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "instructor_name": "Mohammed Al-Rashid",
        "instructor_name_ar": "محمد الراشد",
        "bio": "Certified riding instructor with 10 years of experience",
        "bio_ar": "مدرب قيادة معتمد...",
        "photo": "https://...",
        "experience_years": 10,
        "certifications": ["FIM Level 2", "UAE Riding Academy"],
        "rating_average": 4.9,
        "total_sessions": 320,
        "is_available": true,
        "provider": {
          "id": 1,
          "business_name": "Pro Riders Academy"
        },
        "locations": [
          {
            "id": 1,
            "location_name": "Al Olaya Training Ground",
            "city": { "id": 1, "name": "Riyadh" }
          }
        ]
      }
    ]
  }
}
```

### Get Instructor Details
```
GET /api/riding-instructors/{id}
```
**Response `200`:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "instructor_name": "Mohammed Al-Rashid",
    "bio": "...",
    "photo": "https://...",
    "experience_years": 10,
    "certifications": ["FIM Level 2"],
    "rating_average": 4.9,
    "total_sessions": 320,
    "is_available": true,
    "locations": [ ... ],
    "provider": { ... },
    "schedule": [
      { "day_of_week": 1, "start_time": "09:00", "end_time": "17:00", "is_available": true },
      { "day_of_week": 5, "start_time": "09:00", "end_time": "14:00", "is_available": true }
    ],
    "recent_reviews": [ ... ],
    "other_services": [ ... ]
  }
}
```

### Get Instructor Locations
```
GET /api/instructor-locations
```
**Query Params:** `city_id`, `instructor_id`

### Get Instructor Availability
```
GET /api/riding-instructors/{id}/availability
```
**Query Params:**
| Param | Type | Required | Description |
|---|---|---|---|
| `from_date` | `YYYY-MM-DD` | ✅ | Start date |
| `to_date` | `YYYY-MM-DD` | ✅ | End date |
| `location_id` | `integer` | — | Filter by location |

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "2026-07-01": {
      "available": true,
      "slots": [
        { "start_time": "09:00", "end_time": "10:00", "is_booked": false },
        { "start_time": "10:00", "end_time": "11:00", "is_booked": true },
        { "start_time": "11:00", "end_time": "12:00", "is_booked": false }
      ]
    },
    "2026-07-02": {
      "available": false,
      "slots": []
    }
  }
}
```

### Book a Session
```
POST /api/riding-instructors/{id}/book-session
Authorization: Bearer {token}
```
**Body:**
```json
{
  "booking_date": "2026-07-10",
  "start_time": "09:00",
  "duration_hours": 2,
  "location_id": 1,
  "session_type": "beginner",
  "notes": "First time on a motorcycle"
}
```
> `session_type`: `beginner` · `intermediate` · `advanced` · `custom`

**Response `201`:**
```json
{
  "success": true,
  "data": {
    "booking_id": 47,
    "instructor_name": "Mohammed Al-Rashid",
    "booking_date": "2026-07-10",
    "start_time": "09:00",
    "end_time": "11:00",
    "duration_hours": 2,
    "total_price": 300.00,
    "currency": "SAR",
    "session_type": "beginner",
    "location": "Al Olaya Training Ground"
  },
  "message": "Session booked successfully"
}
```

---

## 8. Bike Wash & Maintenance Workshops

> Both services use the **Generic Service + Booking** system.
> Filter by `category_id=4` for Bike Wash, `category_id=5` for Maintenance.

### Get Bike Wash Services
```
GET /api/services?category_id=4
```

### Get Maintenance Workshop Services
```
GET /api/services?category_id=5
```

### Request a Session / Book an Appointment
> Same endpoint for both. See [Bookings → Create a Booking](#create-a-booking).

```
POST /api/bookings
Authorization: Bearer {token}
```

### Ask a Question (Chat)
> After booking is **confirmed**, start a chat session with the provider.
> See [Chat Sessions](#12-chat-sessions).

---

## 9. Bookings

### Create a Booking
```
POST /api/bookings
Authorization: Bearer {token}
```
**Body:**
```json
{
  "service_id": 5,
  "booking_date": "2026-07-15",
  "start_time": "10:00",
  "end_time": "11:00",
  "pickup_latitude": 24.7136,
  "pickup_longitude": 46.6753,
  "pickup_location": "My home address",
  "dropoff_latitude": 24.8000,
  "dropoff_longitude": 46.7000,
  "dropoff_location": "Workshop location",
  "notes": "Front brake issue",
  "promo_code": "SAVE10"
}
```
> `pickup/dropoff` fields are optional depending on service type.

**Response `201`:**
```json
{
  "success": true,
  "data": {
    "id": 48,
    "status": "pending",
    "service": {
      "id": 5,
      "name": "Full Bike Wash",
      "price": 80.00
    },
    "provider": {
      "id": 2,
      "business_name": "Sparkle Bike Wash"
    },
    "booking_date": "2026-07-15",
    "start_time": "10:00",
    "end_time": "11:00",
    "price": 80.00,
    "discount_amount": 8.00,
    "total_price": 72.00,
    "currency": "SAR",
    "payment_required": true
  },
  "message": "Booking created successfully"
}
```

### List My Bookings
```
GET /api/bookings
Authorization: Bearer {token}
```
**Query Params:**
| Param | Type | Description |
|---|---|---|
| `status` | `string` | `pending\|confirmed\|in_progress\|completed\|cancelled\|rejected` |
| `service_id` | `integer` | Filter by service |
| `from_date` | `YYYY-MM-DD` | Date range start |
| `to_date` | `YYYY-MM-DD` | Date range end |
| `upcoming` | `0\|1` | Upcoming bookings only |
| `per_page` | `integer` | Default: 15 |

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 48,
        "status": "confirmed",
        "booking_date": "2026-07-15",
        "start_time": "10:00",
        "total_price": 72.00,
        "service": { "id": 5, "name": "Full Bike Wash", "image": "https://..." },
        "provider": { "id": 2, "business_name": "Sparkle Bike Wash" },
        "can_cancel": true,
        "can_review": false,
        "can_start_chat": true
      }
    ]
  }
}
```

### Get Booking Details
```
GET /api/bookings/{id}
Authorization: Bearer {token}
```
**Response `200`:**
```json
{
  "success": true,
  "data": {
    "id": 48,
    "status": "confirmed",
    "booking_date": "2026-07-15",
    "start_time": "10:00",
    "end_time": "11:00",
    "price": 80.00,
    "discount_amount": 8.00,
    "total_price": 72.00,
    "currency": "SAR",
    "notes": "Front brake issue",
    "payment_status": "paid",
    "can_cancel": true,
    "can_review": false,
    "can_start_chat": true,
    "service": { ... },
    "provider": { ... },
    "review": null,
    "chat_session": null
  }
}
```

### Cancel a Booking
```
POST /api/bookings/{id}/cancel
Authorization: Bearer {token}
```
**Body:**
```json
{
  "cancellation_reason": "Schedule conflict"
}
```
> ⚠️ Cancellation is only allowed **more than 24 hours** before the booking date.

**Response `200`:**
```json
{
  "success": true,
  "message": "Booking cancelled successfully"
}
```

**Booking Status Flow:**
```
pending → confirmed → in_progress → completed
                   ↘ cancelled
                   ↘ rejected
```

---

## 10. Reviews

### Get Reviews for a Service
```
GET /api/services/{service_id}/reviews
```
**Query Params:**
| Param | Type | Description |
|---|---|---|
| `sort_by` | `recent\|rating_high\|rating_low` | Sort order |
| `per_page` | `integer` | Default: 10 |

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "statistics": {
      "average_rating": 4.8,
      "total_reviews": 45,
      "rating_breakdown": {
        "5": 30,
        "4": 10,
        "3": 4,
        "2": 1,
        "1": 0
      }
    },
    "reviews": {
      "data": [
        {
          "id": 1,
          "rating": 5,
          "comment": "Excellent service, very professional!",
          "comment_ar": "خدمة ممتازة جداً!",
          "user": {
            "id": 12,
            "first_name": "Khalid",
            "avatar": "https://..."
          },
          "created_at": "2026-06-01T10:30:00Z"
        }
      ],
      "current_page": 1,
      "total": 45
    }
  }
}
```

### Submit a Review
```
POST /api/bookings/{booking_id}/review
Authorization: Bearer {token}
```
> ⚠️ Booking must have `status = completed`

**Body:**
```json
{
  "rating": 5,
  "comment": "Excellent service, very professional!",
  "comment_ar": "خدمة ممتازة جداً!"
}
```
> `rating`: integer 1-5 (required) · `comment`: 10-1000 chars (required)

**Response `201`:**
```json
{
  "success": true,
  "data": {
    "id": 55,
    "rating": 5,
    "comment": "Excellent service!",
    "created_at": "2026-06-04T12:00:00Z"
  },
  "message": "Review submitted successfully"
}
```

### My Reviews
```
GET /api/my-reviews
Authorization: Bearer {token}
```

### Update a Review
```
PUT /api/reviews/{id}
Authorization: Bearer {token}
```

### Delete a Review
```
DELETE /api/reviews/{id}
Authorization: Bearer {token}
```

---

## 11. Favorites

### Toggle Favorite (Add or Remove)
```
POST /api/services/{service_id}/favorite
Authorization: Bearer {token}
```
**Response `200`:**
```json
{
  "success": true,
  "is_favorited": true,
  "message": "Service added to favorites"
}
```

### Add to Favorites
```
POST /api/services/{service_id}/favorite/add
Authorization: Bearer {token}
```

### Remove from Favorites
```
DELETE /api/services/{service_id}/favorite/remove
Authorization: Bearer {token}
```

### Check if Favorited
```
GET /api/services/{service_id}/is-favorited
Authorization: Bearer {token}
```
**Response `200`:**
```json
{
  "success": true,
  "is_favorited": true
}
```

### List My Favorites
```
GET /api/my-favorite-services
Authorization: Bearer {token}
```
**Query Params:** `category_id`, `city_id`, `is_available`, `per_page`

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "name": "Professional Bike Transport",
        "image": "https://...",
        "price": 150.00,
        "rating_average": 4.8,
        "is_available": true,
        "category": { "id": 1, "name": "Bike Transport" },
        "provider": { "id": 1, "business_name": "Elite Bike Transport" }
      }
    ]
  }
}
```

### Favorite Statistics
```
GET /api/my-favorite-services/stats
Authorization: Bearer {token}
```
**Response `200`:**
```json
{
  "success": true,
  "data": {
    "total_favorites": 8,
    "available_services": 6,
    "unavailable_services": 2,
    "by_category": [
      { "category": "Bike Transport", "count": 3 },
      { "category": "Bike Wash", "count": 5 }
    ]
  }
}
```

### Clear All Favorites
```
DELETE /api/my-favorite-services/clear
Authorization: Bearer {token}
```

---

## 12. Chat Sessions

> Chat is linked to a **confirmed or in_progress booking**.
> Real-time messages via **WebSocket (Reverb)**.

### WebSocket Connection (Real-time)
```
ws://your-server.com:8080/app/{REVERB_APP_KEY}
```

**Subscribe to chat channel after authenticating:**
```
Channel: private-chat.{session_id}
Event:   message.sent
```

**Session notifications:**
```
Channel: private-chat.user.{user_id}
Channel: private-chat.provider.{provider_id}
Event:   session.started
```

**Authenticate WebSocket channel:**
```
POST /broadcasting/auth
Authorization: Bearer {token}

Body:
{
  "socket_id": "123.456",
  "channel_name": "private-chat.{session_id}"
}
```

---

### Start a Chat Session
```
POST /api/bookings/{booking_id}/start-chat
Authorization: Bearer {token}
```
> ⚠️ Booking must have `status = confirmed` or `in_progress`

**Response `201`:**
```json
{
  "success": true,
  "data": {
    "id": 10,
    "booking_id": 48,
    "user_id": 3,
    "provider_id": 2,
    "session_status": "active",
    "session_price": 0.00,
    "started_at": "2026-06-04T14:00:00Z"
  },
  "message": "Chat session started successfully"
}
```

### List My Chat Sessions
```
GET /api/my-chat-sessions
Authorization: Bearer {token}
```
**Query Params:**
| Param | Type | Description |
|---|---|---|
| `status` | `string` | `active\|pending\|completed\|expired` |

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 10,
        "session_status": "active",
        "started_at": "2026-06-04T14:00:00Z",
        "booking": { "id": 48, "service": { "name": "Full Bike Wash" } },
        "user": { "id": 3, "first_name": "Ahmed", "avatar": "https://..." },
        "provider": { "id": 2, "business_name": "Sparkle Bike Wash" },
        "last_message": {
          "message": "Hello, I have a question...",
          "created_at": "2026-06-04T14:05:00Z"
        },
        "unread_count": 2
      }
    ]
  }
}
```

### Send a Message
```
POST /api/chat-sessions/{session_id}/send-message
Authorization: Bearer {token}
```

**Text message:**
```json
Content-Type: application/json

{
  "message": "Hello, is the service available tomorrow?",
  "message_type": "text"
}
```

**Image/File message:**
```
Content-Type: multipart/form-data

attachment: [file]     (max 10MB)
message: "Photo of the issue"
```
> `message_type` is auto-detected: `text` · `image` · `file`

**Response `201`:**
```json
{
  "success": true,
  "data": {
    "id": 101,
    "session_id": 10,
    "sender_id": 3,
    "sender_type": "user",
    "message": "Hello, is the service available tomorrow?",
    "message_type": "text",
    "attachment_url": null,
    "is_read": false,
    "created_at": "2026-06-04T14:05:00Z"
  }
}
```

> 📡 The other participant receives the message **instantly via WebSocket** (`message.sent` event on `private-chat.{session_id}`).

### Get Messages
```
GET /api/chat-sessions/{session_id}/messages
Authorization: Bearer {token}
```
**Query Params:**
| Param | Type | Description |
|---|---|---|
| `per_page` | `integer` | Default: 50 |
| `before_id` | `integer` | Load messages before this ID (infinite scroll) |

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "messages": {
      "data": [
        {
          "id": 101,
          "sender_id": 3,
          "sender_type": "user",
          "message": "Hello, is the service available?",
          "message_type": "text",
          "attachment_url": null,
          "is_read": true,
          "read_at": "2026-06-04T14:06:00Z",
          "created_at": "2026-06-04T14:05:00Z"
        },
        {
          "id": 102,
          "sender_id": 2,
          "sender_type": "provider",
          "message": "Yes, we are available!",
          "message_type": "text",
          "attachment_url": null,
          "is_read": false,
          "created_at": "2026-06-04T14:06:00Z"
        }
      ],
      "total": 15,
      "current_page": 1
    },
    "session": { "id": 10, "session_status": "active" },
    "unread_count": 1
  }
}
```
> ⚠️ Calling this endpoint **automatically marks messages from the other side as read**.

### Mark as Read
```
POST /api/chat-sessions/{session_id}/mark-read
Authorization: Bearer {token}
```
**Response `200`:**
```json
{
  "success": true,
  "messages_marked": 3,
  "message": "Messages marked as read"
}
```

### End Chat Session
```
POST /api/chat-sessions/{session_id}/end
Authorization: Bearer {token}
```
**Response `200`:**
```json
{
  "success": true,
  "data": {
    "id": 10,
    "session_status": "completed",
    "started_at": "2026-06-04T14:00:00Z",
    "ended_at": "2026-06-04T15:30:00Z",
    "duration_minutes": 90
  }
}
```

---

## 13. Provider — My Services

> Only accessible by users who have an **active provider profile + subscription**

### My Services (Provider)
```
GET /api/provider/services
Authorization: Bearer {token}
```
**Response `200`:**
```json
{
  "success": true,
  "data": {
    "subscription_info": {
      "has_active_subscription": true,
      "plan_name": "Business Plan",
      "can_add_service": true,
      "services_used": 8,
      "max_services": 15,
      "remaining_quota": 7,
      "limit_reached": false
    },
    "services": [ ... ]
  }
}
```

### Create a Service
```
POST /api/provider/services
Authorization: Bearer {token}
Content-Type: multipart/form-data
```
**Body:**
```json
{
  "category_id": 4,
  "name": "Premium Bike Wash",
  "name_ar": "غسيل دراجات ممتاز",
  "description": "Full wash with wax protection",
  "description_ar": "غسيل كامل مع حماية الشمع",
  "price": 120.00,
  "price_type": "fixed",
  "duration_minutes": 90,
  "is_available": true,
  "max_capacity": 3,
  "image": "[file]",
  "has_online_consultation": false,
  "schedules": [
    { "day_of_week": 1, "start_time": "08:00", "end_time": "18:00" },
    { "day_of_week": 2, "start_time": "08:00", "end_time": "18:00" }
  ],
  "pricing_rules": [
    { "type": "per_km", "price": 2.00 }
  ]
}
```
> `price_type`: `fixed` · `per_hour` · `per_km` · `custom`

### Update a Service
```
PUT /api/provider/services/{id}
Authorization: Bearer {token}
```

### Delete a Service
```
DELETE /api/provider/services/{id}
Authorization: Bearer {token}
```

### Provider Transport Routes
```
GET  /api/provider/transport-routes
POST /api/provider/transport-routes
Authorization: Bearer {token}
```
**Create Route Body:**
```json
{
  "route_date": "2026-08-01",
  "departure_time": "08:00",
  "arrival_time": "14:00",
  "departure_point": "Riyadh",
  "departure_point_ar": "الرياض",
  "arrival_point": "Jeddah",
  "arrival_point_ar": "جدة",
  "available_slots": 10,
  "price_per_slot": 200.00,
  "stops": [
    {
      "stop_order": 1,
      "location_name": "Riyadh Central Station",
      "city_id": 1,
      "latitude": 24.7136,
      "longitude": 46.6753
    },
    {
      "stop_order": 2,
      "location_name": "Jeddah Corniche",
      "city_id": 2,
      "latitude": 21.5434,
      "longitude": 39.1728
    }
  ]
}
```

### Provider Riding Instructor Schedule
```
GET  /api/provider/riding-instructors/{id}/schedule
POST /api/provider/riding-instructors/{id}/schedule
Authorization: Bearer {token}
```
**Update Schedule Body:**
```json
{
  "schedule": [
    { "day_of_week": 1, "start_time": "09:00", "end_time": "17:00", "is_available": true },
    { "day_of_week": 2, "start_time": "09:00", "end_time": "17:00", "is_available": true },
    { "day_of_week": 5, "start_time": "09:00", "end_time": "13:00", "is_available": true },
    { "day_of_week": 0, "is_available": false },
    { "day_of_week": 6, "is_available": false }
  ]
}
```
> `day_of_week`: 0 = Sunday, 1 = Monday, ... 6 = Saturday

---

## 14. Provider — Working Hours

```
GET /api/provider/working-hours
Authorization: Bearer {token}
```
**Response `200`:**
```json
{
  "success": true,
  "data": [
    { "day_of_week": 0, "is_open": false, "open_time": null, "close_time": null },
    { "day_of_week": 1, "is_open": true, "open_time": "08:00", "close_time": "18:00" },
    { "day_of_week": 2, "is_open": true, "open_time": "08:00", "close_time": "18:00" },
    { "day_of_week": 3, "is_open": true, "open_time": "08:00", "close_time": "18:00" },
    { "day_of_week": 4, "is_open": true, "open_time": "08:00", "close_time": "18:00" },
    { "day_of_week": 5, "is_open": true, "open_time": "08:00", "close_time": "14:00" },
    { "day_of_week": 6, "is_open": false, "open_time": null, "close_time": null }
  ]
}
```

```
PUT /api/provider/working-hours
Authorization: Bearer {token}
```
**Body:**
```json
{
  "schedule_type": "specific_days",
  "hours_type": "specific_time",
  "specific_days": [1, 2, 3, 4, 5],
  "open_time": "08:00",
  "close_time": "18:00"
}
```
> `schedule_type`: `all_week` · `specific_days`
> `hours_type`: `24_hours` · `specific_time`

---

## 15. Subscriptions (Provider)

> Required for providers to publish and manage services.

### View Subscription Plans
```
GET /api/subscription-plans
```
**Response `200`:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Basic Plan",
      "name_ar": "الخطة الأساسية",
      "slug": "basic-plan",
      "description": "Perfect for getting started",
      "price_monthly": 19.00,
      "price_yearly": 190.00,
      "features": [
        "Up to 5 services",
        "Up to 50 bookings per month",
        "Basic analytics",
        "Email support"
      ],
      "max_services": 5,
      "max_bookings_per_month": 50,
      "priority_support": false,
      "analytics_access": false,
      "is_featured": false,
      "is_active": true,
      "order_position": 1
    },
    {
      "id": 2,
      "name": "Business Plan",
      "slug": "business-plan",
      "price_monthly": 29.00,
      "price_yearly": 290.00,
      "max_services": 15,
      "max_bookings_per_month": 200,
      "is_featured": true
    },
    {
      "id": 3,
      "name": "Enterprise Plan",
      "slug": "enterprise-plan",
      "price_monthly": 39.00,
      "price_yearly": 390.00,
      "max_services": null,
      "max_bookings_per_month": null
    }
  ]
}
```
> `max_services: null` and `max_bookings_per_month: null` = **Unlimited**

### Subscribe to a Plan
```
POST /api/subscriptions/subscribe
Authorization: Bearer {token}
```
**Body (existing provider):**
```json
{
  "plan_id": 2,
  "billing_cycle": "monthly"
}
```
> `billing_cycle`: `monthly` · `yearly`

**Response `200`:**
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
> Redirect the user to `payment_url` to complete payment via PayTabs.

### My Subscription
```
GET /api/my-subscription
Authorization: Bearer {token}
```
**Response `200`:**
```json
{
  "success": true,
  "data": {
    "has_subscription": true,
    "subscription": {
      "id": 1,
      "status": "active",
      "plan": { "id": 2, "name": "Business Plan" },
      "billing_cycle": "monthly",
      "current_price": 29.00,
      "current_period_start": "2026-06-01",
      "current_period_end": "2026-07-01",
      "next_billing_date": "2026-07-01",
      "days_until_renewal": 27,
      "auto_renew": true,
      "on_trial": false,
      "trial_ends_at": null
    },
    "usage": {
      "services_used": 8,
      "services_quota": 7,
      "bookings_this_month": 45,
      "bookings_quota": 155
    },
    "features": {
      "priority_support": true,
      "analytics_access": true
    }
  }
}
```

### Cancel Subscription
```
POST /api/subscriptions/cancel
Authorization: Bearer {token}
```
**Body:**
```json
{
  "reason": "Too expensive"
}
```

### Subscription Transactions
```
GET /api/subscription/transactions
Authorization: Bearer {token}
```
**Response `200`:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "invoice_number": "INV-2026-001",
      "amount": 29.00,
      "currency": "SAR",
      "status": "completed",
      "transaction_type": "subscription",
      "billing_period_start": "2026-06-01",
      "billing_period_end": "2026-07-01",
      "processed_at": "2026-06-01T10:00:00Z"
    }
  ]
}
```
> `status`: `pending` · `completed` · `failed` · `refunded`

---

## 16. Error Responses

All errors follow the same structure:

```json
{
  "success": false,
  "message": "Human-readable error message"
}
```

| HTTP Code | Meaning |
|---|---|
| `400` | Bad request (e.g. session already ended, can't cancel) |
| `401` | Unauthenticated — missing or invalid JWT token |
| `403` | Unauthorized — you don't have permission |
| `404` | Resource not found |
| `422` | Validation error |
| `500` | Server error |

### Validation Error (422)
```json
{
  "success": false,
  "errors": {
    "rating": ["The rating field is required."],
    "booking_date": ["The booking date must be after today."]
  }
}
```

---

## Quick Reference — Endpoints by Service

| Service | List | Detail | Book | Price |
|---|---|---|---|---|
| **Tow Service** | `GET /api/tow-types` | `GET /api/tow-types/{id}` | `POST /api/tow-service/request` | `POST /api/tow-service/calculate-price` |
| **Bike Transport** | `GET /api/transport-routes` | `GET /api/transport-routes/{id}` | `POST /api/transport-routes/{id}/book` | In route detail |
| **Riding Instructor** | `GET /api/riding-instructors` | `GET /api/riding-instructors/{id}` | `POST /api/riding-instructors/{id}/book-session` | Service price × hours |
| **Bike Wash** | `GET /api/services?category_id=4` | `GET /api/services/{id}` | `POST /api/bookings` | In service detail |
| **Maintenance** | `GET /api/services?category_id=5` | `GET /api/services/{id}` | `POST /api/bookings` | In service detail |

---

*Generated for DabApp Backend — 2026-06-04*
