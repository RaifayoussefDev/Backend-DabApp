# Prompt — Claude Web (DB Architecture Diagram)

> Paste everything below this line into Claude Web

---

Generate a clean **Entity-Relationship (ER) diagram** for the following database architecture. Use a visual diagram with tables, columns, and relationships (arrows showing FK links). Group tables by module with color coding:
- 🟦 Blue = Riding Instructor module
- 🟩 Green = Trainer module
- ⬜ Gray = Shared / Existing tables (users, cities, payments)

---

## SHARED TABLES (already exist)

**users**
- id (PK)
- first_name, last_name
- email, phone
- avatar
- timestamps

**cities**
- id (PK)
- name, name_ar

**payments**
- id (PK)
- user_id (FK → users)
- payable_type (instructor_bookings / trainer_bookings) ← polymorphic
- payable_id
- amount
- payment_status (pending / paid / refunded / failed)
- tran_ref, cart_id (PayTabs)
- resp_code, resp_message
- timestamps

---

## MODULE 1 — RIDING INSTRUCTOR 🟦

**riding_instructors**
- id (PK)
- user_id (FK → users) ← direct link, NO subscription required
- name, name_ar
- bio, bio_ar
- photo
- certifications (JSON)
- experience_years
- price_per_hour
- rating_average
- total_sessions
- likes_count
- is_available (boolean)
- status (pending / approved / rejected / suspended) ← admin validates
- timestamps

**instructor_locations**
- id (PK)
- instructor_id (FK → riding_instructors)
- location_name, location_name_ar
- city_id (FK → cities)
- latitude, longitude
- is_available (boolean)
- timestamps

**instructor_bookings**
- id (PK)
- instructor_id (FK → riding_instructors)
- user_id (FK → users)
- location_id (FK → instructor_locations)
- booking_date
- start_time, end_time
- duration_hours
- session_type (beginner / intermediate / advanced / custom)
- status (pending / confirmed / in_progress / completed / cancelled / rejected)
- price
- payment_id (FK → payments, nullable)
- payment_status (pending / paid / refunded / failed)
- notes
- confirmed_at, completed_at, cancelled_at
- timestamps

**instructor_reviews**
- id (PK)
- booking_id (FK → instructor_bookings)
- instructor_id (FK → riding_instructors)
- user_id (FK → users)
- rating (1–5)
- comment
- is_approved (boolean)
- timestamps

**instructor_comments**
- id (PK)
- instructor_id (FK → riding_instructors)
- user_id (FK → users)
- parent_id (FK → instructor_comments, nullable) ← nested replies
- content
- is_approved (boolean)
- timestamps

**instructor_favorites**
- id (PK)
- instructor_id (FK → riding_instructors)
- user_id (FK → users)
- timestamps

**instructor_likes**
- id (PK)
- instructor_id (FK → riding_instructors)
- user_id (FK → users)
- timestamps

---

## MODULE 2 — TRAINER 🟩

**trainers**
- id (PK)
- user_id (FK → users) ← direct link, NO subscription required
- name, name_ar
- bio, bio_ar
- photo
- specialty (coaching / competition / off-road / street / custom)
- certifications (JSON)
- experience_years
- price_per_hour
- rating_average
- total_sessions
- likes_count
- is_available (boolean)
- status (pending / approved / rejected / suspended) ← admin validates
- timestamps

**trainer_locations**
- id (PK)
- trainer_id (FK → trainers)
- location_name, location_name_ar
- city_id (FK → cities)
- latitude, longitude
- is_available (boolean)
- timestamps

**trainer_bookings**
- id (PK)
- trainer_id (FK → trainers)
- user_id (FK → users)
- location_id (FK → trainer_locations)
- booking_date
- start_time, end_time
- duration_hours
- session_type
- status (pending / confirmed / in_progress / completed / cancelled / rejected)
- price
- payment_id (FK → payments, nullable)
- payment_status (pending / paid / refunded / failed)
- notes
- confirmed_at, completed_at, cancelled_at
- timestamps

**trainer_reviews**
- id (PK)
- booking_id (FK → trainer_bookings)
- trainer_id (FK → trainers)
- user_id (FK → users)
- rating (1–5)
- comment
- is_approved (boolean)
- timestamps

**trainer_comments**
- id (PK)
- trainer_id (FK → trainers)
- user_id (FK → users)
- parent_id (FK → trainer_comments, nullable) ← nested replies
- content
- is_approved (boolean)
- timestamps

**trainer_favorites**
- id (PK)
- trainer_id (FK → trainers)
- user_id (FK → users)
- timestamps

**trainer_likes**
- id (PK)
- trainer_id (FK → trainers)
- user_id (FK → users)
- timestamps

---

## KEY DESIGN DECISIONS TO SHOW IN THE DIAGRAM

1. Both `riding_instructors` and `trainers` link **directly to `users`** — no subscription or service_provider required
2. Both have their **own separate booking tables** — completely independent from the existing `service_bookings` table
3. **Payments are polymorphic** — one `payments` table handles both instructor and trainer bookings via `payable_type` / `payable_id`
4. **Admin validates** every instructor and trainer before they go live (`status` field)
5. **Comments support nested replies** via `parent_id` self-reference
6. **Likes and Favorites are separate tables** — one row per user per instructor/trainer

---

Please generate the ER diagram with all tables, columns, primary keys (PK), foreign keys (FK), and relationship arrows clearly labeled.
