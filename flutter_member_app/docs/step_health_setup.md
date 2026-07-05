# Member Step Health Setup

This repo's member app is Flutter, not React Native. The step tracker was added in the real Flutter structure:

- `lib/src/features/member/health/step_health_service.dart`
- `lib/src/features/member/services/step_sync_service.dart`

## Package install

```bash
cd /Users/amanagarwal/Desktop/gym_ecosystem/flutter_member_app
/Users/amanagarwal/Desktop/flutter/bin/flutter pub get
```

If CocoaPods needs refresh:

```bash
cd /Users/amanagarwal/Desktop/gym_ecosystem/flutter_member_app/ios
pod install
```

## AndroidManifest

Required manifest permissions for this implementation:

- `android.permission.ACTIVITY_RECOGNITION`
- `android.permission.health.READ_STEPS`
- `android.permission.health.READ_DISTANCE`
- `android.permission.health.READ_ACTIVE_CALORIES_BURNED`
- `android.permission.health.READ_HEALTH_DATA_HISTORY`

Health Connect package visibility was also added under `<queries>`.
The Android app now also:

- uses `FlutterFragmentActivity` for Health Connect permission flows
- sets `minSdk` to at least `26`
- exposes the Health Connect permission rationale intent
- includes `ViewPermissionUsageActivity` for the Health permissions privacy-policy flow
- falls back to the native Android step counter sensor when Health Connect is unavailable

### Android fallback behavior

If Health Connect is missing on Android, the member app now falls back to the
device step counter sensor through the app's native Android activity.

- source sent to backend: `android_sensor`
- steps come from `Sensor.TYPE_STEP_COUNTER`
- distance and calories are estimated locally from the step count

Important limitation:

- if the phone has been running since before midnight and the app has never
  captured a baseline for the current day, Android's step counter sensor cannot
  reconstruct exact today's steps from history on its own
- in that case, the first fallback read seeds today's baseline and later reads
  track the day's movement from that point onward until a more complete source
  like Health Connect is available

You should make sure the app has a privacy policy route that `MainActivity` can open if Google review requires it.

## iOS setup

`Info.plist` now includes:

- `NSHealthShareUsageDescription`
- `NSHealthUpdateUsageDescription`

You still need to enable the `HealthKit` capability in Xcode:

1. Open `ios/Runner.xcworkspace`
2. Select the `Runner` target
3. Go to `Signing & Capabilities`
4. Add `HealthKit`

`Runner.entitlements` is now included and wired into the Xcode target, but you should still open Xcode once and confirm the capability is visible under `Signing & Capabilities`.

## Runtime sync rules

- sync on app open
- sync when the dashboard becomes focused
- do not auto-sync more than once every 15 minutes
- manual refresh bypasses the 15 minute throttle
- sync failures are logged and do not crash the dashboard

## Scope

- reads today's totals from local start of day to current time
- returns a normalized result for steps, distance, calories, source, and permission state
- no aggressive background tracking
- no continuous polling
