# Google Play data-safety answers

These answers reflect the current source audit. Recheck them if production uses
analytics, crash reporting, advertising, an MMP, or another SDK/service not
present in this checkout.

## Shared answers

- Does the app collect or share required user data types? `Yes`
- Is all collected data encrypted in transit? `Yes`
- Can users request deletion of their data? `Yes`
- Account deletion URL:
  - Member: `https://gymatlas.in/account-deletion?app=member`
  - Trainer: `https://gymatlas.in/account-deletion?app=trainer`
- Data shared with third parties: `No`, provided Firebase, hosting, email and
  notification vendors are used only as service providers acting on Atlas
  instructions
- Data sold: `No`
- Independent security review badge: `No` unless a qualifying assessment has
  actually been completed
- Payments policy badge: `Not applicable`

For each collected type below, select purposes only from:
`App functionality`, `Account management`, `Developer communications`,
`Personalization`, and `Fraud prevention, security and compliance`, as mapped.

## Atlas Member

| Play data type | Collected | Required? | Purpose |
|---|---:|---|---|
| Personal info — Name | Yes | Required | App functionality; Account management |
| Personal info — Email address | Yes | Required | Account management; Developer communications |
| Personal info — Phone number | Yes | Optional | App functionality; Account management |
| Personal info — User IDs | Yes | Required | App functionality; Account management; Security |
| Photos and videos — Photos | Yes | Optional | App functionality; Personalization |
| Health and fitness — Fitness info | Yes | Optional | App functionality; Personalization |
| Health and fitness — Health info | Yes | Optional | App functionality; Personalization |
| Location — Approximate location | Yes | Optional | App functionality |
| Location — Precise location | Yes | Optional | App functionality |
| Messages — Other in-app messages | Yes | Optional | App functionality; Developer communications |
| App activity — App interactions | Yes | Required | App functionality; Personalization |
| App activity — Other user-generated content | Yes | Optional | App functionality |
| Device or other IDs | Yes | Required | App functionality; Security |
| Financial info — Purchase history | Yes | Optional | App functionality |

Collection notes:

- Fitness/health includes workouts, measurements, progress, steps, distance,
  active calories and diet-plan context.
- Photos are uploaded only after the user selects profile/progress media.
- Location is collected only while nearby-gym discovery is used; it is not
  background location.
- Messages are member/trainer chat and support requests.
- Device IDs include Firebase messaging/installation and authentication IDs.
- Purchase history means gym membership/payment-status records displayed from
  the Atlas backend. No payment card or bank-account data is collected by the
  app.
- Health, location, photos, phone and messages are optional/user initiated.

## Atlas Trainer

| Play data type | Collected | Required? | Purpose |
|---|---:|---|---|
| Personal info — Name | Yes | Required | App functionality; Account management |
| Personal info — Email address | Yes | Required | Account management; Developer communications |
| Personal info — Phone number | Yes | Optional | App functionality; Account management |
| Personal info — User IDs | Yes | Required | App functionality; Account management; Security |
| Photos and videos — Photos | Yes | Optional | App functionality; Personalization |
| Files and docs — Files and docs | Yes | Optional | App functionality; Account management |
| Health and fitness — Fitness info | Yes | Required | App functionality |
| Health and fitness — Health info | Yes | Optional | App functionality |
| Messages — Other in-app messages | Yes | Optional | App functionality; Developer communications |
| App activity — App interactions | Yes | Required | App functionality; Security |
| App activity — Other user-generated content | Yes | Optional | App functionality |
| Device or other IDs | Yes | Required | App functionality; Security |

Collection notes:

- Fitness/health data is assigned-client workout, diet, measurement and progress
  context needed for coaching.
- Photos/files are trainer-selected profile and certification uploads.
- The trainer app uses system pickers and does not request broad photo-library
  permission.
- User-generated content includes plans, private coaching notes and follow-ups.
- Messages are member/trainer chat and support requests.
- Device IDs include Firebase messaging/installation and authentication IDs.
- Location and financial information are not collected by the trainer app.

## Final production cross-check

Before pressing Save, confirm that production does not add:

- Google Analytics for Firebase or another analytics SDK
- Firebase Crashlytics or another crash/diagnostic SDK
- advertising or attribution SDKs
- payment-card entry or an in-app payment SDK
- background location
- contact, calendar, microphone or camera collection

If any are present, update this declaration before release.
