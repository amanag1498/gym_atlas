# App Store submission checklist

## Already prepared in source

- [x] Production bundle IDs and matching Firebase plist files
- [x] Member version `1.0.9`, build `20`; Coach version `1.0.6`, build `16`
- [x] Automatic signing with Apple Developer Team `9BQZB27JWV`
- [x] iPhone-only release targets
- [x] Push Notifications and Sign in with Apple capabilities
- [x] HealthKit capability and usage descriptions for Gym Atlas
- [x] Camera, photo-library, location, and health permission text where used
- [x] Remote-notification background mode; unused background fetch removed
- [x] Non-exempt encryption declaration set to false
- [x] App privacy manifests included in both application bundles
- [x] Production Atlas icons and branded launch screen
- [x] Member Bluetooth usage text and `bluetooth-central` background mode for
      optional Smart Attendance hub detection; Trainer does not use Bluetooth.
- [x] App Store Connect export options
- [x] 1284 x 2778 App Store Connect iPhone screenshots without alpha
- [x] Store copy, review notes, privacy mapping, and account-deletion URLs

## Apple account and production gates

- [ ] Accept current agreements and verify paid Apple Developer membership.
- [ ] Create or confirm App Store Connect records for both bundle IDs.
- [ ] Confirm the names `Gym Atlas` and `Gym Atlas Coach` are available.
- [ ] Enable Push Notifications and Sign in with Apple for both identifiers;
      enable HealthKit for the member identifier.
- [ ] Create APNs authentication key or production APNs certificates and upload
      them to the matching Firebase iOS apps.
- [x] Export signed Member and Coach IPAs using Apple Developer Team
      `9BQZB27JWV`.
- [ ] Set App Store Connect roles, pricing/availability, tax category, age
      rating, content rights, and release mode.
- [ ] Enter Support, Marketing, and Privacy Policy URLs from the listing files.
- [ ] Complete App Privacy using `app-privacy.md`.
- [ ] Provide stable member and trainer demo accounts in App Review Information.
- [ ] Explain Member Smart Attendance to App Review: after the member enables
      it, the app detects nearby gym BLE hubs to maintain visit in/out times,
      including background detection. Show how to enable/disable it and provide
      a test hub or a demonstration video if reviewers cannot reach a gym hub.
- [ ] Verify both deletion links and in-app deletion flows against production.
- [ ] Confirm production realtime URL and `/ready` health before building.
- [x] Build signed IPAs for Member `1.0.9 (20)` and Coach `1.0.6 (16)`.
- [ ] Upload with Xcode Organizer or Transporter, wait for processing, attach the
      build, answer export-compliance questions, and submit for review.

## Final device checks

Run these on physical iPhones using the exact release build:

- Fresh install, login, logout, relaunch, expired session, and account deletion.
- Google and Apple sign-in on both apps.
- Member camera/gallery, Apple Health grant/deny/revoke, and location
  grant/deny/revoke.
- Trainer profile/certification picker.
- Foreground, background, and terminated push notifications.
- Trainer-to-member and member-to-trainer chat with each recipient outside the
  conversation, then notification tap routing.
- Assigned workouts and meal-based diets visible to the member.
- Open/closed gym status across its configured timezone and overnight hours.
- Poor-network and offline error handling without crashes or blank screens.

Record the tested iOS version, device model, build number, and demo accounts
used. Remove any test data that should not remain after review.
