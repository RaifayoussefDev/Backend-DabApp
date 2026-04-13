# Ads Module – Mobile Developer Documentation

## Overview

The Ads module displays paid advertisements (photo or video) inside the app. When the user taps the CTA button, a lead capture form slides up. The user fills in their details and submits. **No authentication required** — these are fully public endpoints.

---

## Base URL

```
http://your-domain.com/api
```

---

## Flow

```
1. App opens → fetch active ads → display first ad (photo or video)
2. User taps button_text button → show lead form
3. User fills form (first_name, last_name, phone, email, city_id) → POST submit
4. Show success message
```

---

## Endpoints

### 1. Get Active Ads

```
GET /api/ads
Accept: application/json
```

No authentication required.

**Response `200`:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "New Car 2026",
      "description": "Discover the new model exclusively.",
      "type": "video",
      "image": "https://cdn.dabapp.com/ads/car-thumb.jpg",
      "media_url": "https://cdn.dabapp.com/ads/car.mp4",
      "button_text": "I am interested",
      "has_form": true
    },
    {
      "id": 2,
      "title": "Ramadan Special Offer",
      "description": "Get 30% off during Ramadan.",
      "type": "photo",
      "image": "https://cdn.dabapp.com/ads/ramadan.jpg",
      "media_url": null,
      "button_text": "Learn More",
      "has_form": true
    }
  ]
}
```

**Field reference:**

| Field       | Type    | Description                                              |
|-------------|---------|----------------------------------------------------------|
| id          | integer | Ad ID — use this for the submit endpoint                 |
| title       | string  | Ad headline                                              |
| description | string  | Ad body text (nullable)                                  |
| type        | string  | `photo` → show `image` / `video` → show `media_url`     |
| image       | string  | Photo URL or video thumbnail URL                         |
| media_url   | string  | Video URL (only present when type = `video`)             |
| button_text | string  | Label to display on the CTA button                       |
| has_form    | boolean | Always `true` — always show lead form on button tap      |

---

### 2. Get Single Ad

```
GET /api/ads/{id}
Accept: application/json
```

Same response structure as the list, single object under `data`.

**Response `404`:**
```json
{ "success": false, "message": "Ad not found" }
```

---

### 3. Submit Lead Form

Called when the user taps the CTA button and submits the form.

```
POST /api/ads/{id}/submit
Content-Type: application/json
Accept: application/json
```

**Body:**

| Field      | Type    | Required | Description                     |
|------------|---------|----------|---------------------------------|
| first_name | string  | YES      | User first name (max 100 chars) |
| last_name  | string  | YES      | User last name (max 100 chars)  |
| phone      | string  | YES      | Phone number (max 20 chars)     |
| email      | string  | no       | Email address (valid email)     |
| city_id    | integer | YES      | City ID from `/api/cities`      |

**Example:**
```json
{
  "first_name": "Ahmed",
  "last_name": "Benali",
  "phone": "0551234567",
  "email": "ahmed.benali@example.com",
  "city_id": 1
}
```

**Response `201` – Success:**
```json
{
  "success": true,
  "message": "Thank you! Your information has been submitted."
}
```

**Response `422` – Validation error:**
```json
{
  "success": false,
  "message": "Validation error",
  "errors": {
    "first_name": ["The first name field is required."],
    "city_id": ["The selected city id is invalid."]
  }
}
```

**Response `404` – Ad inactive or not found:**
```json
{
  "success": false,
  "message": "Ad not found or inactive"
}
```

---

## Get Cities List

Use this endpoint to populate the city picker in the form.

```
GET /api/cities
Accept: application/json
```

**Response `200`:**
```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "Dubai" },
    { "id": 2, "name": "Abu Dhabi" },
    { "id": 3, "name": "Riyadh" }
  ]
}
```

---

## UI Implementation Guide

### Display Logic

```
if (ad.type === "video") {
    → play ad.media_url (mp4)
    → use ad.image as poster/thumbnail
} else {
    → display ad.image as full-screen photo
}

→ show button labeled ad.button_text
→ on button tap: open lead form modal
```

### Lead Form Fields

```
1. First Name   → text input   → maps to: first_name
2. Last Name    → text input   → maps to: last_name
3. Phone        → phone input  → maps to: phone
4. Email        → email input  → maps to: email (optional)
5. City         → dropdown     → maps to: city_id  (GET /api/cities)
6. Submit button → POST /api/ads/{id}/submit
```

### After Submit

- Show success message from `response.message`
- Close the form modal
- Do NOT submit again if user re-watches the ad

---

## Error Handling

| HTTP Status | Meaning                          | Action                        |
|-------------|----------------------------------|-------------------------------|
| 201         | Submitted successfully           | Show success message          |
| 404         | Ad not found or inactive         | Hide the ad from the list     |
| 422         | Validation failed                | Show field errors to the user |
| 500         | Server error                     | Show generic error message    |
