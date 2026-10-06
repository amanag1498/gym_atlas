# Gym Atlas App Store release kit

This directory contains the App Store Connect copy, privacy answers, review
notes, and iPhone screenshots for both iOS apps.

## Products

| App | Bundle ID | Version | Build | Platform |
|---|---|---:|---:|---|
| Gym Atlas | `com.techybugs.gymatlas.member` | `1.0.9` | `20` | iPhone |
| Gym Atlas Coach | `com.techybugs.gymatlas.trainer` | `1.0.6` | `16` | iPhone |

Both projects use automatic signing with Apple Developer Team `9BQZB27JWV`.
The release archive must be built with the production realtime HTTPS URL.

The exported Member IPA is at
`flutter_member_app/build/ios/ipa/Gym Atlas.ipa` (27,110,162 bytes),
with SHA-256 `b74ca7d953229d40accdc35b2cd177a4343874f048da7e4027a07da5f6de1988`.
The exported Coach IPA is at
`flutter_trainer_app/build/ios/ipa/Gym Atlas Coach.ipa` (26,802,118 bytes),
with SHA-256 `760bca62e85de393f6dc92a8cf70fe7f6667c78b9c98764248eed420421faf47`.
Both were exported locally with the new `G` app icon; neither was uploaded.

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
