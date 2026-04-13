# Ads Module – Admin Panel Developer Guide

## Overview

The Ads module allows the business to create paid advertisements (photo or video) displayed inside the mobile app. When a user taps the call-to-action button, a lead capture form appears. Submitted leads are stored in the database and automatically synced to a linked Google Sheet.

Ads are stored in the existing `banners` table (filtered by `has_form = true`). Leads are stored in `ad_submissions`.

---

## Base URL

```
http://your-domain.com/api
```

All admin endpoints require a **Bearer JWT token** in the `Authorization` header.

```
Authorization: Bearer <admin_token>
```

---

## Endpoints

### 1. List All Ads

```
GET /api/admin/ads
```

**Query params:**

| Param    | Type    | Default | Description         |
|----------|---------|---------|---------------------|
| per_page | integer | 15      | Items per page      |
| search   | string  | –       | Filter by title     |

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "total": 3,
    "data": [
      {
        "id": 1,
        "title": "New Car 2026",
        "description": "Discover the new model exclusively.",
        "type": "video",
        "image": "https://cdn.dabapp.com/ads/car-thumb.jpg",
        "media_url": "https://cdn.dabapp.com/ads/car.mp4",
        "button_text": "I am interested",
        "google_sheet_id": "15ln515Ecn1lw1ZdHlqe3BdDuXzlxskZLmfdpa7wjXNU",
        "order": 1,
        "is_active": true,
        "start_date": "2026-04-14",
        "end_date": "2026-12-31",
        "ad_submissions_count": 42,
        "created_at": "2026-04-14 10:00:00"
      }
    ]
  }
}
```

---

### 2. Create Ad

```
POST /api/admin/ads
Content-Type: application/json
```

**Body:**

| Field           | Type    | Required | Description                              |
|-----------------|---------|----------|------------------------------------------|
| title           | string  | YES      | Ad title                                 |
| description     | string  | no       | Ad description                           |
| type            | string  | no       | `photo` or `video` (default: `photo`)    |
| image           | string  | no       | Thumbnail/photo URL                      |
| media_url       | string  | no       | Video URL (for type=video)               |
| button_text     | string  | no       | CTA button label (e.g. "I am interested")|
| google_sheet_id | string  | no       | Google Sheets spreadsheet ID             |
| order           | integer | no       | Display order (default: 0)               |
| is_active       | boolean | no       | Active status (default: true)            |
| start_date      | date    | no       | Activation start date (YYYY-MM-DD)       |
| end_date        | date    | no       | Activation end date (YYYY-MM-DD)         |

**Example body:**
```json
{
  "title": "New Car 2026",
  "description": "Discover the new model exclusively.",
  "type": "video",
  "media_url": "https://cdn.dabapp.com/ads/car.mp4",
  "image": "https://cdn.dabapp.com/ads/car-thumb.jpg",
  "button_text": "I am interested",
  "google_sheet_id": "15ln515Ecn1lw1ZdHlqe3BdDuXzlxskZLmfdpa7wjXNU",
  "order": 1,
  "is_active": true,
  "start_date": "2026-04-14",
  "end_date": "2026-12-31"
}
```

**Response `201`:**
```json
{
  "success": true,
  "message": "Ad created successfully",
  "data": { "id": 1, "title": "New Car 2026", ... }
}
```

---

### 3. Get Single Ad

```
GET /api/admin/ads/{id}
```

Returns full ad details including `ad_submissions_count`.

---

### 4. Update Ad

```
PUT /api/admin/ads/{id}
Content-Type: application/json
```

Send only the fields to update. All fields are optional.

---

### 5. Delete Ad

```
DELETE /api/admin/ads/{id}
```

Deletes the ad and all its submissions (cascade).

---

### 6. Toggle Active / Inactive

```
POST /api/admin/ads/{id}/toggle
```

**Response `200`:**
```json
{
  "success": true,
  "message": "Status updated",
  "is_active": false
}
```

---

### 7. View Leads (Submissions)

```
GET /api/admin/ads/{id}/submissions
```

**Query params:**

| Param    | Type    | Default | Description                              |
|----------|---------|---------|------------------------------------------|
| per_page | integer | 25      | Items per page                           |
| search   | string  | –       | Filter by first_name, last_name or phone |

**Response `200`:**
```json
{
  "success": true,
  "ad": { "id": 1, "title": "New Car 2026" },
  "data": {
    "current_page": 1,
    "total": 42,
    "data": [
      {
        "id": 1,
        "user_id": 5,
        "user_name": "Mohammed Ali",
        "first_name": "Ahmed",
        "last_name": "Benali",
        "phone": "0551234567",
        "email": "ahmed.benali@example.com",
        "city_id": 1,
        "city": "Dubai",
        "synced_to_sheet": true,
        "submitted_at": "2026-04-14 10:30:00"
      }
    ]
  }
}
```

> `synced_to_sheet: false` means the Google Sheet sync failed — the lead is still saved in the database.

---

## Google Sheet Setup

1. Create a Google Sheet and add headers in row 1:

| A  | B          | C         | D     | E     | F    | G            |
|----|------------|-----------|-------|-------|------|--------------|
| ID | First Name | Last Name | Phone | Email | City | Submitted At |

2. Share the sheet with the service account email: `dabapp-sheets@dabapp-78824.iam.gserviceaccount.com` (Editor permission)
3. Copy the Sheet ID from the URL: `https://docs.google.com/spreadsheets/d/{SHEET_ID}/edit`
4. Paste the Sheet ID into the `google_sheet_id` field when creating/updating the ad.

---

## Database Tables

### `banners` (extended)

| Column          | Type         | Description                    |
|-----------------|--------------|--------------------------------|
| id              | bigint       | Primary key                    |
| title           | varchar      | Ad title                       |
| description     | text         | Ad description                 |
| image           | varchar      | Photo/thumbnail URL            |
| type            | enum         | `photo` or `video`             |
| media_url       | varchar      | Video URL                      |
| button_text     | varchar      | CTA button label               |
| has_form        | boolean      | Always `true` for ads          |
| google_sheet_id | varchar      | Linked Google Sheet ID         |
| order           | integer      | Display order                  |
| is_active       | boolean      | Active status                  |
| start_date      | date         | Activation start               |
| end_date        | date         | Activation end                 |

### `ad_submissions`

| Column          | Type    | Description                       |
|-----------------|---------|-----------------------------------|
| id              | bigint  | Primary key                       |
| banner_id       | bigint  | FK → banners.id                   |
| user_id         | bigint  | FK → users.id (nullable)          |
| first_name      | varchar | Lead first name                   |
| last_name       | varchar | Lead last name                    |
| phone           | varchar | Lead phone number                 |
| email           | varchar | Lead email (nullable)             |
| city_id         | bigint  | FK → cities.id                    |
| synced_to_sheet | boolean | Google Sheet sync status          |
| created_at      | timestamp | Submission timestamp            |
