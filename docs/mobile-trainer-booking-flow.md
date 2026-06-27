# DabApp — Trainer Booking : Payment & Confirmation Flow (Mobile Guide)

**Base URL:** `https://be.dabapp.co/api`
**Auth header:** `Authorization: Bearer {token}`

---

## 1. PAYMENT FLOW

### Step 1 — Client books session
```
POST /api/trainers/{trainer_id}/book
Authorization: Bearer {client_token}
Content-Type: application/json

{
  "booking_date":   "2026-06-25",
  "start_time":     "10:00",
  "duration_hours": 2,
  "session_type":   "beginner",
  "location_id":    1
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "booking_id":  4,
    "payment_url": "https://secure.paytabs.com/payment/OfxE..."
  }
}
```

### Step 2 — Open payment page
```
// Mobile: open WebView with payment_url
// Web: window.location.href = payment_url

// After payment, PayTabs redirects browser to:
// https://dabapp.co/trainers/booking-confirmation?booking_id=4&status=success
// OR
// https://dabapp.co/trainers/booking-confirmation?booking_id=4&status=failed
```

### Step 3 — Verify payment status
```
GET /api/trainer/bookings/{booking_id}
Authorization: Bearer {client_token}
```

**Response:**
```json
{
  "data": {
    "id":             4,
    "status":         "confirmed",
    "payment_status": "paid",
    "booking_date":   "2026-06-25",
    "start_time":     "10:00",
    "end_time":       "12:00",
    "price":          300,
    "trainer": {
      "name":      "Ahmed Al-Rashidi",
      "photo_url": "https://be.dabapp.co/storage/..."
    },
    "location": {
      "location_name": "Abu Dhabi Training Circuit",
      "city": { "name": "Riyadh" }
    }
  }
}
```

**Payment status values:**
| `payment_status` | Meaning |
|-----------------|---------|
| `pending` | Not paid yet → show Pay button |
| `paid` | ✅ Payment confirmed |
| `failed` | ❌ Payment failed → retry |
| `refunded` | 💸 Refunded |

---

## 2. BOOKING STATUS FLOW

```
pending
   │
   ▼ (payment done — PayTabs webhook automatic)
confirmed          ← trainer must come to client
   │
   ▼ (trainer clicks Start)
in_progress
   │
   ▼ (trainer clicks Complete)
awaiting_confirmation   ← client must confirm
   │              │
   ▼ confirm      ▼ dispute
completed       disputed   → admin reviews
   │
   ▼ (client leaves review)
review submitted (pending moderation)
```

---

## 3. CONFIRMATION FLOW (Client side)

### Detect when confirmation is needed
```
GET /api/trainer/bookings
Authorization: Bearer {client_token}

// Check bookings where status === "awaiting_confirmation"
// Show notification / badge to client
```

### Client confirms session was done
```
POST /api/trainer/bookings/{booking_id}/confirm-completion
Authorization: Bearer {client_token}

// No body needed
```

**Response:**
```json
{
  "success":    true,
  "message":    "Session confirmed as completed. You can now leave a review.",
  "can_review": true
}
```

### Client disputes (session not done properly)
```
POST /api/trainer/bookings/{booking_id}/dispute
Authorization: Bearer {client_token}
Content-Type: application/json

{
  "reason": "Trainer arrived 1 hour late and left early"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Dispute submitted. Admin will review."
}
```

---

## 4. REVIEW (after confirmed completed)

```
POST /api/trainer/bookings/{booking_id}/review
Authorization: Bearer {client_token}
Content-Type: application/json

{
  "rating":  5,
  "comment": "Excellent trainer, very professional!"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Review submitted and pending moderation"
}
```

> Reviews appear publicly after admin approval.

---

## 5. TRAINER SESSION ACTIONS

### Trainer sees his sessions
```
GET /api/trainer/sessions
Authorization: Bearer {trainer_token}
```

**Response:**
```json
{
  "data": {
    "data": [{
      "id":             4,
      "status":         "confirmed",
      "payment_status": "paid",
      "booking_date":   "2026-06-25",
      "start_time":     "10:00",
      "end_time":       "12:00",
      "price":          300,
      "user": {
        "first_name": "Khalid",
        "last_name":  "Al-Mansouri",
        "phone":      "+966501234567"
      },
      "location": {
        "location_name": "Abu Dhabi Training Circuit"
      }
    }]
  }
}
```

### Trainer starts session
```
POST /api/trainer/sessions/{id}/start
Authorization: Bearer {trainer_token}

// confirmed → in_progress
```

### Trainer marks as done
```
POST /api/trainer/sessions/{id}/complete
Authorization: Bearer {trainer_token}

// in_progress → awaiting_confirmation
// Client receives notification to confirm
```

---

## 6. FULL SCENARIO SUMMARY

```
CLIENT                    SERVER                      TRAINER
  │                          │                            │
  ├─ POST /book ────────────►│                            │
  │◄─ { payment_url } ───────┤                            │
  │                          │                            │
  ├─ Open WebView ──────────►│ (PayTabs)                  │
  │◄─ redirect back ─────────┤                            │
  │                          │                            │
  ├─ GET /bookings/{id} ────►│ status=confirmed ──────────┤►
  │  verify payment=paid     │                            │
  │                          │                     ───────┤
  │                          │            GET /sessions    │
  │                          │            status=confirmed │
  │                          │                            │
  │                          │            POST .../start   │
  │                          │◄───────────────────────────┤
  │                          │  status = in_progress       │
  │                          │                            │
  │                          │            POST .../complete│
  │                          │◄───────────────────────────┤
  │                          │  status = awaiting_confirm  │
  │                          │                            │
  │ 🔔 Notification          │                            │
  │◄─────────────────────────┤                            │
  │                          │                            │
  ├─ POST .../confirm ──────►│  status = completed         │
  │                          │                            │
  ├─ POST .../review ───────►│  review pending moderation  │
  │                          │                            │
```

---

## 7. STATUS QUICK REFERENCE (Mobile UI)

### Booking status → what to show client
| status | Label (EN) | Label (AR) | Action button |
|--------|-----------|-----------|---------------|
| `pending` | Pending Payment | في انتظار الدفع | **Pay Now** |
| `confirmed` | Confirmed | مؤكد | — (wait) |
| `in_progress` | In Progress | جارية | — (wait) |
| `awaiting_confirmation` | Confirm Session? | تأكيد الجلسة؟ | **Confirm / Dispute** |
| `completed` | Completed | مكتملة | **Leave Review** |
| `disputed` | Under Review | قيد المراجعة | — |
| `cancelled` | Cancelled | ملغي | — |

### Session status → what to show trainer
| status | Label (EN) | Label (AR) | Action button |
|--------|-----------|-----------|---------------|
| `confirmed` | Go to Client | اذهب للعميل | **Start Session** |
| `in_progress` | Session Active | الجلسة نشطة | **Complete Session** |
| `awaiting_confirmation` | Waiting Client | انتظار العميل | — (spinner) |
| `completed` | Done ✓ | منجزة ✓ | — |
| `disputed` | Disputed | متنازع | — |

---

## 8. PUSH NOTIFICATION TRIGGERS

| Event | Who receives | Suggested message |
|-------|-------------|-------------------|
| Booking created + paid | Trainer | "New booking on {date} at {time}" |
| Payment confirmed | Client | "Booking confirmed! Trainer will come to you" |
| Session started | Client | "Your trainer has arrived and started the session" |
| Session awaiting confirmation | Client | "Rate your session with {trainer_name}" |
| Session confirmed by client | Trainer | "Client confirmed session completed ✓" |
| Dispute submitted | Admin | "New dispute on booking #{id}" |
