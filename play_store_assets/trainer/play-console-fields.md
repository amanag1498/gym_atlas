# Atlas Trainer — Play Console fields

Copy these values into the Atlas Trainer app in Play Console.

## App setup

- App name: `Atlas Trainer`
- Default language: `English (India) – en-IN`
- App or game: `App`
- Free or paid: `Free`
- Package name: `com.techybugs.gymatlas.trainer`
- Category: `Health & Fitness`
- Suggested tags: `Fitness`, `Personal trainer`, `Workout`, `Gym`
- Contains ads: `No`
- Countries/regions for first production release: `India`

## Main store listing

### App name

```text
Atlas Trainer
```

### Short description

```text
Manage clients, plans, follow-ups, progress and coaching conversations.
```

### Full description

```text
Atlas Trainer gives coaches a focused workspace for the clients assigned by their gym.

Review member context, manage workout and diet plans, organize follow-ups, monitor progress, and keep coaching conversations connected to each client. Atlas Trainer is designed for clear daily action without a noisy admin-style interface.

Key features:

• Assigned-client overview and member detail
• Today’s clients and pending follow-ups
• Workout plan, template and exercise management
• Diet-plan creation and assignment
• Attendance, progress and workout-log visibility
• Private trainer notes and follow-up completion
• Direct member chat and actionable notifications
• In-app chat reporting, blocking and respectful-use controls
• Trainer profile and user-selected certification uploads
• Privacy, support and account-deletion controls

Access requires an active Atlas trainer account and permissions assigned by a participating gym.
```

## Contact details

- Developer/support email: `support@gymatlas.in`
- Website: `https://gymatlas.in`
- Privacy policy: `https://gymatlas.in/privacy-policy`
- Account deletion URL:
  `https://gymatlas.in/account-deletion?app=trainer`
- Support page: `https://gymatlas.in/contact`

Create and test the `support@gymatlas.in` mailbox before submission. The email
is displayed publicly on Google Play.

## Graphic assets

- App icon: `../branding/atlas-play-store-icon.png` — 512 × 512 PNG
- Feature graphic: `feature-graphic-1024x500.png` — 1024 × 500 PNG
- Phone screenshots, in upload order:
  1. `screenshots/01-coach-with-clarity.png`
  2. `screenshots/02-build-programs.png`
  3. `screenshots/03-know-every-client.png`
  4. `screenshots/04-stay-connected.png`
- Promo video: leave blank
- 7-inch/10-inch tablet, Chromebook and Wear OS assets: leave blank unless
  those form factors are explicitly added to the release

Screenshot alt text:

1. `Atlas Trainer feature illustration showing clients, daily coaching tasks, sessions, plans and follow-ups.`
2. `Atlas Trainer feature illustration showing workout templates, exercise library and program assignment.`
3. `Atlas Trainer feature illustration showing client progress, attendance, workout completion and coaching notes.`
4. `Atlas Trainer feature illustration showing member chat, coaching alerts, reports and blocking controls.`

## Release

- Release name: `Atlas Trainer 1.0.0 (1)`
- Release notes:

```text
Initial Atlas Trainer release with assigned clients, workout and diet planning, follow-ups, progress review, coaching chat safety controls, notifications, profile uploads, privacy controls and account deletion.
```

- App signing: `Google Play App Signing`
- Release file:
  `flutter_trainer_app/build/app/outputs/bundle/release/app-release.aab`

## App access

Select: `All or some functionality is restricted`.

Reviewer instructions:

```text
Atlas Trainer uses Google sign-in and requires an approved trainer account.

Reviewer Google account:
Email: [CREATE REVIEWER TRAINER EMAIL]
Password: [ENTER REVIEWER TRAINER PASSWORD IN PLAY CONSOLE ONLY]

Steps:
1. Open Atlas Trainer.
2. Tap Continue with Google.
3. Sign in with the reviewer account above.
4. The account opens directly to the trainer dashboard.

The reviewer account is active, has completed onboarding, and has assigned members, workout and diet plans, follow-ups, progress data, notifications and a sample member conversation. No OTP, payment or additional approval is required.
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
- Location collection: `No`
- User-generated content/online interaction: `Yes` — trainer/member chat

Use the detailed content-rating, health and data-safety answers in
`../app-content-answers.md` and `../data-safety.md`.
