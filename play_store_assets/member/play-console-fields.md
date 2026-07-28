# Atlas Member — Play Console fields

Copy these values into the Atlas Member app in Play Console.

## App setup

- App name: `Atlas Member`
- Default language: `English (India) – en-IN`
- App or game: `App`
- Free or paid: `Free`
- Package name: `com.techybugs.gymatlas.member`
- Category: `Health & Fitness`
- Suggested tags: `Fitness`, `Workout`, `Personal trainer`, `Gym`
- Contains ads: `No`
- Countries/regions for first production release: `India`

Important: a free Play app cannot later be changed to paid. Atlas can still
offer gym-managed memberships outside the downloaded app.

## Main store listing

### App name

```text
Atlas Member
```

### Short description

```text
Train smarter with workouts, progress, gym discovery and coach support.
```

### Full description

```text
Atlas Member brings your gym journey into one calm, connected place.

Follow assigned workout and diet plans, log training sessions and sets, review attendance and membership details, and track body progress over time. With your permission, Atlas can also show daily steps, distance and active calories from supported health services.

Discover nearby gyms, review available plans and trial options, connect with your assigned trainer, and keep coaching conversations alongside the work they relate to.

Key features:

• Assigned workout plans, session logging and workout history
• Diet plans and coaching guidance
• Progress measurements and user-selected progress photos
• Daily step, distance and active-calorie summaries
• Attendance, membership and payment-status visibility
• Nearby gym discovery and trial requests
• Direct trainer chat and notifications
• In-app chat reporting, blocking and respectful-use controls
• Profile, privacy, support and account-deletion controls

Health and location access is optional and is requested only when you use the related feature. Some features require an active Atlas gym membership or trainer assignment.
```

## Contact details

- Developer/support email: `support@gymatlas.in`
- Website: `https://gymatlas.in`
- Privacy policy: `https://gymatlas.in/privacy-policy`
- Account deletion URL:
  `https://gymatlas.in/account-deletion?app=member`
- Support page: `https://gymatlas.in/contact`

Create and test the `support@gymatlas.in` mailbox before submission. The email
is displayed publicly on Google Play.

## Graphic assets

- App icon: `../branding/atlas-play-store-icon.png` — 512 × 512 PNG
- Feature graphic: `feature-graphic-1024x500.png` — 1024 × 500 PNG
- Phone screenshots, in upload order:
  1. `screenshots/01-fitness-connected.png`
  2. `screenshots/02-train-with-plan.png`
  3. `screenshots/03-see-every-win.png`
  4. `screenshots/04-coaching-close.png`
- Promo video: leave blank
- 7-inch/10-inch tablet, Chromebook and Wear OS assets: leave blank unless
  those form factors are explicitly added to the release

Screenshot alt text:

1. `Atlas Member feature illustration showing connected workouts, daily steps, progress and membership access.`
2. `Atlas Member feature illustration showing assigned workout plans, set logging and training history.`
3. `Atlas Member feature illustration showing body progress, attendance milestones, membership and gym access.`
4. `Atlas Member feature illustration showing trainer chat, diet plans, alerts and chat safety controls.`

## Release

- Release name: `Atlas Member 1.0.0 (1)`
- Release notes:

```text
Initial Atlas Member release with workout and diet plans, progress tracking, optional step and nearby-gym features, memberships, trainer chat safety controls, notifications, privacy controls and account deletion.
```

- App signing: `Google Play App Signing`
- Release file:
  `flutter_member_app/build/app/outputs/bundle/release/app-release.aab`

## App access

Select: `All or some functionality is restricted`.

Reviewer instructions:

```text
Atlas Member uses Google sign-in and requires an approved member account.

Reviewer Google account:
Email: [CREATE REVIEWER MEMBER EMAIL]
Password: [ENTER REVIEWER MEMBER PASSWORD IN PLAY CONSOLE ONLY]

Steps:
1. Open Atlas Member.
2. Tap Continue with Google.
3. Sign in with the reviewer account above.
4. The account opens directly to the member dashboard.

The reviewer account has an active gym membership, an assigned trainer, workout and diet plans, attendance history, progress data and a sample trainer conversation. Health Connect, location and notifications are optional; choose Not now if prompted. No OTP, payment or additional approval is required.
```

Never place the reviewer password in this repository. Store it only in the
restricted Play Console app-access field.

## Declarations

- Target audience: `18 and over` only
- Designed for children: `No`
- News app: `No`
- Government app: `No`
- Financial features: `No financial features`
- COVID-19 contact tracing/status app: `No`
- Ads: `No`
- In-app purchases: `No`
- Health apps: `Activity and fitness` and `Nutrition and weight management`
- Medical device: `No`
- Clinical/medical diagnosis or treatment: `No`
- Location is shared with other users: `No`
- User-generated content/online interaction: `Yes` — member/trainer chat

Use the detailed content-rating, health and data-safety answers in
`../app-content-answers.md` and `../data-safety.md`.
