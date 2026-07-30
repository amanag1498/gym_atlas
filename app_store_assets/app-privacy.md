# App Store privacy answers

These answers reflect the current source and dependency audit. Recheck them if
production adds analytics, crash reporting, advertising, attribution, payment
card entry, background location, contacts, microphone, or other collection.

## Shared answers

- Privacy policy: `https://gymatlas.in/privacy-policy`
- Tracking: `No`
- Data used for third-party advertising: `No`
- Data used for developer advertising or marketing: `No`
- Data sold: `No`
- Data shared by Atlas with other companies for their independent use: `No`
- Firebase, hosting, email, and notification services are processors supporting
  app functionality.
- All types listed below are linked to the user's identity and are not used for
  tracking.

## Gym Atlas Member

| App Store data type | Purpose |
|---|---|
| Contact Info — Name | App Functionality |
| Contact Info — Email Address | App Functionality |
| Contact Info — Phone Number | App Functionality |
| Identifiers — User ID | App Functionality |
| Identifiers — Device ID | App Functionality |
| User Content — Photos or Videos | App Functionality; Product Personalization |
| Health & Fitness — Health | App Functionality; Product Personalization |
| Health & Fitness — Fitness | App Functionality; Product Personalization |
| Location — Precise Location | App Functionality |
| Purchases — Purchase History | App Functionality |
| Usage Data — Product Interaction | App Functionality; Product Personalization |
| User Content — Other User Content | App Functionality |

Collection notes:

- Health and fitness includes workouts, body measurements, progress, steps,
  distance, active calories, and diet-plan context.
- Photos are uploaded only after the member chooses profile or progress media.
- Precise location is used only during nearby-gym discovery, never in the
  background.
- Other user content includes member/trainer messages and support requests.
- Purchase history is gym membership and payment-status data; the app does not
  collect card or bank details.

## Gym Atlas Trainer

| App Store data type | Purpose |
|---|---|
| Contact Info — Name | App Functionality |
| Contact Info — Email Address | App Functionality |
| Contact Info — Phone Number | App Functionality |
| Identifiers — User ID | App Functionality |
| Identifiers — Device ID | App Functionality |
| User Content — Photos or Videos | App Functionality; Product Personalization |
| Health & Fitness — Health | App Functionality |
| Health & Fitness — Fitness | App Functionality |
| Usage Data — Product Interaction | App Functionality |
| User Content — Other User Content | App Functionality |

Collection notes:

- Health and fitness is assigned-member workout, diet, measurement and progress
  context needed for coaching.
- User content includes certifications, plans, private coaching notes,
  follow-ups, member messages, and support requests.
- The trainer app does not collect location or financial information.

The app-level `PrivacyInfo.xcprivacy` files mirror these answers. Keep the
manifest and App Store Connect selections synchronized.
