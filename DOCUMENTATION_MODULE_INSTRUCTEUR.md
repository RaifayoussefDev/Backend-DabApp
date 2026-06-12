# DabApp — Instructor & Trainer Module


## Who are the actors?

| Actor | Role |
|-------|------|
| **Client** | Any DabApp user looking to book a riding session |
| **Instructor / Trainer** | The service provider registered on DabApp |
| **DabApp** | The platform — mediator between both parties |

---

## SCENARIO 1 — An Instructor Joins DabApp

**Context:** Ahmed is a motorcycle riding instructor. He wants to offer his sessions through DabApp.

1. Ahmed opens the DabApp app and taps **"Register like Provider"**
2. He fills in his basic information: name, phone number, city
3. He selects his type: **Riding Instructor** or **Trainer**
4. He completes his profile:
   - Profile photo
   - Short bio (what he teaches, his style)
   - Years of experience
   - Certifications (optional)
5. He adds his **training locations** (the places where he gives sessions): name of the location, city, GPS pin on the map
6. He sets his **weekly schedule**: which days he works and from what time to what time
7. He sets his **price per hour**
8. He submits his profile — DabApp reviews and **activates** it
9. Ahmed's profile is now **visible to all clients** on the platform
10. Ahmed receives:
    - 📲 **Push notification:** *"Welcome to DabApp! Your profile is now live."*
    - 📧 **Email:** Welcome email with his profile summary and next steps

> Ahmed paid nothing. No subscription. Just registration.

---

## SCENARIO 2 — A Client Finds an Instructor

**Context:** Sara wants to learn to ride a motorcycle. She opens DabApp.

1. Sara goes to the **Instructors** section in the app
2. She sees a list of available instructors near her city
3. She can filter by:
   - City
   - Rating (e.g. only 4 stars and above)
   - Experience (e.g. minimum 5 years)
   - Availability
4. She taps on **Ahmed's profile** and sees:
   - His photo, bio, certifications
   - His rating and number of completed sessions
   - Reviews left by previous clients
   - His training locations on a map
5. She taps **"Check Availability"**
6. She picks a date and sees the available time slots for that day
7. She selects her slot, reads the total price, and taps **"Book"**

---

## SCENARIO 3 — Booking & Payment

**Context:** Sara has selected a 2-hour session with Ahmed on Saturday at 10:00 AM.

1. Sara confirms her booking details:
   - Date and time
   - Training location
   - Session type (beginner / intermediate / advanced)
   - Optional note to the instructor (e.g. "it's my first time")
2. She taps **"Confirm & Pay"**
3. DabApp redirects her to the **secure payment page** (card payment)
4. Sara enters her card details and confirms
5. **Payment is accepted:**
   - Sara receives:
     - 📲 **Push notification:** *"Your session is confirmed with Ahmed on Saturday at 10:00 AM"*
     - 📧 **Email:** Booking confirmation with date, time, location, and price
   - Ahmed receives:
     - 📲 **Push notification:** *"New session booked — Sara — Saturday 10:00 AM"*
     - 📧 **Email:** New booking details with client name and session info
6. **Payment is declined:**
   - Sara receives:
     - 📲 **Push notification:** *"Payment failed. Please try again or use a different card."*
     - 📧 **Email:** Payment failure notice with a retry link
7. The booking appears in Sara's **"My Bookings"** section
8. Ahmed sees the new booking in his **"My Sessions"** dashboard

> DabApp holds the payment. Ahmed receives his share after the session is completed.

---

## SCENARIO 4 — The Session Takes Place

**Context:** Saturday arrives.

1. Sara goes to the training location
2. Ahmed marks the session as **"In Progress"** on his app
   - Sara receives:
     - 📲 **Push notification:** *"Your session with Ahmed has started. Enjoy your ride!"*
3. The session happens
4. At the end, Ahmed marks it as **"Completed"**
5. Sara receives:
   - 📲 **Push notification:** *"Your session is complete! Leave a review for Ahmed."*
   - 📧 **Email:** Session summary with date, duration, amount paid, and a review link
6. Ahmed receives:
   - 📲 **Push notification:** *"Session completed. Your payment is being processed."*
   - 📧 **Email:** Session earnings summary

---

## SCENARIO 5 — Review, Rating & Comments

**Context:** After the session, Sara wants to share her experience.

1. Sara opens the notification or goes to her booking history
2. She taps **"Leave a Review"**
3. She gives a **star rating** (1 to 5)
4. She writes a **comment**: *"Ahmed was very patient and clear. Highly recommend for beginners!"*
5. She submits
6. The review goes through a quick moderation check by DabApp
7. Once approved, it appears on Ahmed's public profile
8. Ahmed's **average rating is automatically updated**
9. Ahmed receives:
   - 📲 **Push notification:** *"Sara left you a 5-star review! Check it out."*
   - 📧 **Email:** New review notification with the comment and rating

---

## SCENARIO 6 — Likes & Favorites

**Context:** Sara wants to save Ahmed's profile and show appreciation.

- She taps the **heart icon** on Ahmed's profile → he's added to her **Favorites**
- She can access her favorites anytime from her profile page
- She can also tap the **like button** on his profile to show appreciation (publicly visible like count)
- Ahmed can see how many likes and favorites his profile has received

---

## SCENARIO 7 — A Client Cancels a Booking

**Context:** Sara has an emergency and needs to cancel.

1. Sara goes to **"My Bookings"**
2. She selects the upcoming booking and taps **"Cancel"**
3. DabApp applies the **cancellation policy** (to be defined: e.g. free cancellation up to 24h before)
4. If eligible for a refund → Sara gets her money back automatically
   - Sara receives:
     - 📲 **Push notification:** *"Your booking has been cancelled. A refund has been issued."*
     - 📧 **Email:** Cancellation confirmation with refund details and timeline
5. Ahmed receives:
   - 📲 **Push notification:** *"Sara cancelled her session on Saturday at 10:00 AM."*
   - 📧 **Email:** Cancellation notice with the freed-up slot details
6. The time slot becomes available again for other clients

---

## SCENARIO 8 — An Instructor Manages His Schedule

**Context:** Ahmed has a busy week and wants to block some days.

1. Ahmed opens his **Provider Dashboard**
2. He goes to **"My Schedule"**
3. He can:
   - Turn off availability for specific days
   - Adjust his working hours
   - Add or remove training locations
4. Changes take effect immediately — no new bookings can be made for blocked slots
5. Existing confirmed bookings are **not affected**
6. If a change affects an already-confirmed booking:
   - Impacted client receives:
     - 📲 **Push notification:** *"Your instructor has updated his schedule. Please check your booking."*
     - 📧 **Email:** Schedule change notice with booking details and support contact

---

## SCENARIO 9 — Admin Oversight

**Context:** DabApp team manages the platform.

1. Admin can see all registered instructors and trainers
2. Admin **validates or rejects** new instructor registrations
   - If validated → Instructor receives:
     - 📲 **Push notification:** *"Congratulations! Your profile has been approved."*
     - 📧 **Email:** Approval confirmation with profile link
   - If rejected → Instructor receives:
     - 📲 **Push notification:** *"Your profile needs some updates before going live."*
     - 📧 **Email:** Rejection reason and instructions to resubmit
3. Admin **moderates reviews** before they go public
   - If approved → Ahmed receives:
     - 📲 **Push notification:** *"A new review on your profile is now visible."*
4. Admin can **deactivate** a profile in case of a complaint
   - Instructor receives:
     - 📲 **Push notification:** *"Your profile has been temporarily suspended. Please contact support."*
     - 📧 **Email:** Suspension notice with reason and appeal process
5. Admin has access to statistics:
   - Total sessions booked
   - Revenue generated
   - Top-rated instructors
   - Most active cities

---

## Summary of Features

| Feature | Who Uses It |
|---------|-------------|
| Register as instructor / trainer | Instructor |
| Set profile, locations, schedule, price | Instructor |
| Browse and filter instructors | Client |
| View profile, availability, reviews | Client |
| Book a session | Client |
| Pay securely through DabApp | Client |
| Receive booking notifications | Both |
| Mark session as completed | Instructor |
| Leave a review and rating | Client |
| Like and favorite an instructor | Client |
| Cancel a booking | Client |
| Manage schedule and availability | Instructor |
| Validate profiles and moderate content | DabApp Admin |
| View platform statistics | DabApp Admin |


