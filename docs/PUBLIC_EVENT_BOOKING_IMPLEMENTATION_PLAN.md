# Public Event Booking Implementation Plan

## Objective

Allow a gym or the Gym Atlas platform to publish an event that:

- remains directly bookable by eligible members in the Member app;
- has a safe public URL and QR code;
- accepts bookings from Atlas users who belong to another gym or no gym;
- accepts guest bookings without forcing Atlas registration or gym enrollment;
- lets a guest optionally claim the booking after signing in to Atlas;
- uses the existing capacity, waitlist, cancellation, attendance, audit, and notification lifecycle.

## Product decisions

### Event ownership and audience are separate

`scope` continues to identify who owns and manages the event (`gym` or `global`). New fields control booking eligibility and discovery:

- `booking_audience`: `gym_members`, `atlas_members`, or `anyone`.
- `app_visibility`: `hosting_gym`, `all_atlas`, or `link_only`.
- `public_booking_enabled`: controls whether the public page can accept bookings.
- `public_token`: a random, rotatable identifier used in links instead of the database ID.

Defaults preserve current behaviour: gym events use `gym_members` and `hosting_gym`; global events use `atlas_members` and `all_atlas`.

`all_atlas` is platform-only discovery. A gym cannot broadcast its event into every Gym Atlas app. When a gym chooses `atlas_members`, members outside that gym can resolve and book only after opening the UUID event link or QR. Hosting-gym members may still discover the event normally when visibility is `hosting_gym`.

### Guest bookings do not create Atlas accounts

Submitting an unverified form must not create a login-capable `users` row or a gym `member_profiles` row. The booking stores an attendee snapshot and a secure management token. Account creation/association happens only after verified Google/Apple authentication or possession of the secure booking-management link.

Booking an event never enrolls the attendee into the hosting gym.

### One booking engine

App and public bookings must call the same transactional capacity service. There will be no parallel guest capacity counter or waitlist.

## Data model

### `events`

Add:

- `public_token` UUID, nullable and unique;
- `public_booking_enabled` boolean;
- `booking_audience` string;
- `app_visibility` string;
- `public_link_rotated_at` timestamp;
- `registration_form_schema` JSON for future allow-listed custom questions.

### `event_bookings`

Change `user_id` to nullable and add:

- `attendee_name`, `attendee_email`, `attendee_phone` snapshots;
- `attendee_key`, an application-keyed HMAC used for per-event deduplication;
- `booking_source`: `member_app`, `public_web`, `admin`, or `claimed_guest`;
- `manage_token_hash` plus encrypted token recovery for transactional attendee messages;
- `claimed_at`;
- `registration_answers` JSON.

Keep booking, pricing, cancellation, waitlist, promotion, and attendance snapshots unchanged.

### `event_reminders`

Make `user_id` nullable. The booking is the source of truth for the delivery target.

## Public routes

- `GET /events/{publicToken}`: public event page.
- `POST /events/{publicToken}/book`: guest booking.
- `GET /events/{publicToken}/booking/{booking}/manage?token=...`: secure booking status.
- `POST /events/{publicToken}/booking/{booking}/cancel`: secure cancellation.
- Authenticated claim endpoint for converting a guest booking to a user-linked booking.

All public mutation routes require rate limiting, CSRF protection, honeypot validation, duplicate rejection, and server-side event eligibility checks.

## Public experience

1. Show gym branding, event cover, schedule, venue, price, availability, and cancellation policy.
2. Offer `Open in Gym Atlas` / `Continue with Atlas` without making it mandatory.
3. Guest form collects name, email, phone, and configured event questions.
4. Booking result is confirmed or waitlisted.
5. Confirmation exposes calendar, directions, and secure manage/cancel actions.
6. Offer optional account sign-in to save the booking in Atlas.

## Atlas member experience

- Hosting-gym members continue to discover and book the event in the app.
- Gym `atlas_members` events can be opened by any authenticated member who possesses the event link, but are not added to the global Member-app feed.
- `app_visibility` decides whether an event appears in feeds; possession of a valid public link may still allow detail access.
- Event deep links survive login and reopen the original event.
- Claiming attaches a guest reservation to the authenticated user without creating a gym membership.
- Mobile number is mandatory for every reservation. Atlas members reuse their profile number; if it is missing, the Member app asks once during booking, stores it on the Atlas profile, and snapshots it on the event booking without OTP verification.

## Admin experience

Both gym and platform Laravel panels receive:

- booking audience and app visibility controls;
- public booking switch;
- copy-link, QR download, preview, and disable actions;
- attendee source/type badges in the roster;
- guest-safe roster rendering and attendance;
- delivery state and future resend/export controls.

## Notifications

- Linked users continue through `NotificationService` for in-app, FCM, and configured WhatsApp delivery.
- Guests use transactional contact delivery for confirmation, waitlist promotion, reminder, update, cancellation, and event cancellation.
- Event reminders resolve either a linked user or guest contact from the booking.
- Marketing campaigns remain separate from transactional event messages.

## Delivery phases

### Phase 1: Public booking foundation

- Schema and model changes.
- Audience-aware event access.
- Shared transactional booking/cancellation engine.
- Public page, guest booking, secure manage link, and QR/share controls.
- Guest-capable roster and attendance.
- Explicit roster contact column showing attendee email and mobile number for member and guest bookings.
- Free and pay-at-venue events only.

### Phase 2: Account linking and mobile continuity

- Preserve event target through Member-app authentication.
- Allow public events to resolve for eligible Atlas users.
- Claim guest bookings after authenticated identity resolution.
- Merge duplicate guest/user reservations without changing capacity.

### Phase 3: Guest notifications

- Transactional guest delivery adapter.
- Reminder, promotion, update, and cancellation support.
- Delivery reporting and retry visibility.

### Phase 4: Event operations

- Allow-listed custom registration questions.
- CSV export, manual booking, resend confirmation, check-in codes, and analytics.

### Phase 5: Online payment

- Payment-pending holds, callbacks, idempotency, refunds, settlement, tax, and reconciliation.
- This remains separate from free and pay-at-venue delivery.

## Acceptance requirements

- Current event lifecycle tests remain green.
- Guest booking never creates a user or member profile.
- Existing Atlas accounts are reused after authentication.
- Public booking does not create gym membership.
- Duplicate submissions cannot create a second active reservation.
- Concurrent final-seat bookings cannot overbook.
- Waitlist promotion works across guest and user bookings.
- Guest cancellation can promote the next attendee.
- Claiming does not create duplicate reservations.
- Draft, expired, cancelled, disabled, and closed events reject public booking.
- Public tokens are non-enumerable, UUID-constrained, and can be disabled without breaking existing attendee management links.
- Gym isolation applies to management and roster endpoints.
- Guest notifications never attempt in-app/FCM delivery before account linking.

## Production rollout gates

1. Run migrations and focused event/public-booking tests.
2. Deploy backend and compiled Laravel assets.
3. Restart workers and scheduler consumers.
4. Smoke-test a free event with one member, one external Atlas user, and one guest.
5. Verify capacity, waitlist, cancellation, roster, notification delivery, and link rotation.
6. Release Member-app deep-link changes only after the public web fallback is live.

## Implementation status (2026-08-24)

Completed in the current working tree:

- Phase 1 public booking schema, public page, guest reservation, capacity, waitlist, secure management, cancellation, roster, and QR/share controls.
- Phase 2 authenticated claim and Member-app deep-link/login continuity, including safe duplicate merge without gym enrollment.
- Phase 3 transactional guest email for confirmation, reminders, promotion, event changes, and cancellation. Linked users continue through the existing in-app/FCM/WhatsApp notification service.
- Gym and platform Laravel controls for audience, app visibility, public booking, QR, link preview, and guest-safe attendance.
- Managed event-cover uploads for gym and platform admins. Images are validated, optimized, stored on the public disk, replaceable/removable, and old managed files are cleaned up automatically; raw cover URL entry is no longer exposed.
- Public event API, authenticated token resolver, Android App Links, iOS associated-domain path, and custom Member-app scheme.
- Operational isolation: suspended gyms and inactive branches cannot expose or accept new public bookings, while existing secure guest management links remain usable.
- Production hardening audit: booking eligibility and event edits are checked under the event row lock; impossible audience/visibility combinations are rejected; link-only authenticated bookings require possession of the UUID; claim links remain usable after public booking is disabled; and public POST uses redirect-after-submit to prevent browser refresh duplicates.

Still intentionally deferred:

- A user-friendly custom registration-question builder (the schema storage/renderer is prepared, but no raw JSON editor is exposed).
- CSV export, manual admin booking, resend controls, check-in tickets/codes, and event analytics.
- Online payment collection, refunds, and settlement. Existing free and pay-at-venue behaviour is supported.
- Public-link rotation requires management URLs to be separated from the share token first so existing attendees do not lose cancellation access.
