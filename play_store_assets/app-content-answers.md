# Play Console app-content answers

Apply these answers separately to Atlas Member and Atlas Trainer unless a
section says otherwise.

## Privacy policy

- Privacy policy URL: `https://gymatlas.in/privacy-policy`
- The policy is available from inside each app: `Yes`
- Account deletion:
  - Member: `https://gymatlas.in/account-deletion?app=member`
  - Trainer: `https://gymatlas.in/account-deletion?app=trainer`
- Retention disclosed in the public policy:
  - Active account/service data: while the account is active.
  - Verified deletion requests: delete or de-identify personal data from active
    systems within 30 calendar days.
  - Encrypted backups: expire within 90 days.
  - Closed support/deletion requests and safety/security records: up to 24
    months.
  - Transaction or statutory records: up to 8 years where required for tax,
    accounting, contractual, dispute or legal obligations.
- Deletion process: the user submits the web form using the account email;
  support verifies ownership, deactivates access, completes deletion or
  de-identification on the published schedule, and emails confirmation. Any
  legally required exception is disclosed to the requester.

Deploy the backend changes before submitting so these URLs are public,
non-geoblocked, readable without login and return HTTP 200.

## Ads

- Does the app contain ads? `No`
- Advertising ID use: `No`

## App access

- Is all functionality available without special access? `No`
- Select: `All or some functionality is restricted`
- Paste the app-specific instructions from each `play-console-fields.md`.
- Credentials must remain valid throughout review and must not require OTP,
  CAPTCHA, a new invitation or approval.

## Target audience and content

- Target age groups: select only `18 and over`
- Appeal to children: `No`
- Store listing unintentionally appeals to children: `No`
- Families policy: `Not applicable`

## Content rating questionnaire

Use the email address monitored by the publishing team.

For both apps:

- App category: `All other app types`
- Violence: `No`
- Sexuality/nudity: `No`
- Language: `No`
- Controlled substances: `No`
- Gambling: `No`
- Scary content: `No`
- User interaction/communication: `Yes`
- Users exchange text messages: `Yes`
- Users exchange images: `No` for the current chat implementation
- Users share precise physical location with other users: `No`
- User-generated content: `Yes`
- UGC moderation/reporting: `Yes` — chat includes clear in-app Report and
  Block/Unblock controls; the backend records reports and enforces blocks.
- Purchases of digital goods: `No`
- Unrestricted web access: `No`

Submit the questionnaire and use the generated rating; do not manually select a
rating.

## Health apps declaration

Complete this for both apps.

- Is this a health app? `Yes`
- Health features:
  - `Activity and fitness`
  - `Nutrition and weight management`
- Medical device: `No`
- Regulated healthcare service: `No`
- Diagnosis, treatment or clinical decision support: `No`
- Research involving human subjects: `No`
- Emergency/urgent care: `No`
- Disclaimer:

```text
Atlas supports general fitness coaching, workout planning, nutrition guidance and progress tracking. It is not a medical device and does not provide medical diagnosis, treatment or emergency services. Users should consult a qualified healthcare professional before making decisions that may affect their health.
```

Member-specific:

- Reads Health Connect data: `Yes`
- Data types: `Steps`, `Distance`, `Active calories burned`
- Access is optional and user-controlled: `Yes`
- Writes Health Connect data: `No`
- Use explanation:

```text
Atlas Member reads steps, distance and active calories only after the user grants permission. The data is used to show the member’s daily activity summary and fitness progress inside the app. It is not used for advertising, sold, or shared with other users.
```

Trainer-specific:

- Direct Health Connect access: `No`
- Trainers may view member fitness records supplied by the Atlas gym platform
  to provide coaching: `Yes`

## Financial features

- Select: `My app does not provide any financial features`
- Loans, banking, payments, wallets, investing, crypto, insurance, buy-now-pay-
  later and money transfer: all `No`

Membership status or gym billing history is informational platform content; the
current apps do not process a financial transaction.

## Other declarations

- News/magazine app: `No`
- Government app: `No`
- COVID-19 contact tracing/status app: `No`
- Data deletion mechanism: `Yes`
- Account creation: `Yes`, through approved Google-authenticated Atlas accounts
- Users can request deletion in-app and on the web: `Yes`

## Store settings

- App availability: `Available`
- First production geography: `India`
- Managed publishing: `On` for the first release
- External marketing: `On`
- Device catalog: exclude only devices shown as incompatible by the uploaded
  bundle; do not manually exclude supported phones.
- App integrity: enroll in `Play App Signing`
