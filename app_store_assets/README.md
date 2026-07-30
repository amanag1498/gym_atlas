# Gym Atlas App Store release kit

This directory contains the App Store Connect copy, privacy answers, review
notes, and Apple-compatible 6.9-inch iPhone screenshots for both iOS apps.

## Products

| App | Bundle ID | Version | Build | Platform |
|---|---|---:|---:|---|
| Gym Atlas Member | `com.techybugs.gymatlas.member` | `1.0.0` | `2` | iPhone |
| Gym Atlas Trainer | `com.techybugs.gymatlas.trainer` | `1.0.0` | `2` | iPhone |

Both projects use automatic signing with Apple Developer Team `9BQZB27JWV`.
The release archive must be built with the production realtime HTTPS URL.

## Included

- `member/listing.md` and `trainer/listing.md`: ready-to-paste metadata and
  App Review notes.
- `app-privacy.md`: App Store Connect privacy questionnaire mapping.
- `submission-checklist.md`: account-side and upload gates.
- `member/screenshots-6.5` and `trainer/screenshots-6.5`: App Store Connect's
  accepted 1284 x 2778 PNGs without alpha.
- `member/screenshots-6.9` and `trainer/screenshots-6.9`: 1290 x 2796 source
  variants retained for newer media slots.

Do not commit reviewer passwords, Apple credentials, signing certificates, API
keys, or provisioning profiles. Enter reviewer credentials only in App Store
Connect.
