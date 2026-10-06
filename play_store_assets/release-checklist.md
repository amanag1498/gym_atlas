# Atlas Play Store release checklist

## Completed in this checkout

- Unique production package IDs for Member and Trainer.
- Android 16 / API 36 compile and target configuration.
- Java/Kotlin 17 release toolchain.
- Release signing configuration with separate upload keys.
- Debug-key signing removed from release builds.
- Internet and notification permissions declared in the release manifests.
- Broad trainer photo permissions removed; system pickers remain.
- Member Health Connect permissions and permission-rationale activity present.
- Member Bluetooth scan and connected-device foreground-service permissions
  declared for Smart Attendance; Trainer does not request Bluetooth access.
- Production API defaults use `https://gymatlas.in/api`.
- Firebase Android clients match both package IDs.
- New Atlas launcher, round and adaptive icons installed.
- 512 x 512 store icon and 1024 x 500 feature graphics prepared.
- Public privacy policy, support and deletion URLs available in both apps.
- Listing copy, reviewer notes and exact data-safety answers prepared.
- Four real UI screenshots available per app; the older generated visuals still
  show the blue `A` and should not be uploaded with the new `G` listing.
- Outfit font bundled for deterministic offline and first-launch rendering.
- Play UGC controls implemented: chat terms acceptance, report, block/unblock,
  and server-side enforcement for REST and realtime sends.

## Before uploading

1. Back up both ignored `android/keystore/*.jks` files and
   `android/key.properties` files in a secure password manager/vault. Losing an
   upload key creates avoidable recovery work.
2. Add each upload certificate SHA-1 and SHA-256 fingerprint to the matching
   Firebase Android app, download fresh `google-services.json` files, and verify
   Google sign-in in a signed internal-test build.
3. Review the real UI screenshots against the final signed build. Refresh the
   older generated visuals before using them with the new `G` listing.
4. Complete App content: privacy policy, ads, content rating, target audience,
   app access, data safety, account deletion, and Health apps declaration for
   Gym Atlas.
   For Gym Atlas, also complete the connected-device foreground-service
   declaration. Explain that optional Smart Attendance detects nearby gym BLE
   hubs to record visits while a persistent notification is shown. If scanning
   is delayed or interrupted, automatic arrival/departure detection may be
   delayed or missed. Provide a real device demonstration video when requested.
5. Enroll both apps in Play App Signing and upload the `.aab`, not an APK.
6. Upload native debug symbols and keep Dart split-debug-info artifacts for the
   exact release version if obfuscation is enabled.
7. Run internal testing on Android 8 (Member minimum), Android 10/12, Android 13,
   Android 14, Android 15 and Android 16 where available.
8. Verify notifications, Google sign-in, media pickers, Health Connect, location,
   account deletion link, chat notification opens, and cold starts.

## Build commands

From each app directory:

```sh
/Users/amanagarwal/Desktop/flutter/bin/flutter clean
/Users/amanagarwal/Desktop/flutter/bin/flutter pub get
/Users/amanagarwal/Desktop/flutter/bin/flutter analyze
/Users/amanagarwal/Desktop/flutter/bin/flutter test
/Users/amanagarwal/Desktop/flutter/bin/flutter build appbundle --release
```

For a final obfuscated build, create a version-specific symbols directory outside
the generated build tree:

```sh
/Users/amanagarwal/Desktop/flutter/bin/flutter build appbundle --release \
  --obfuscate \
  --split-debug-info=release-symbols/1.0.0+1
```

Do not delete or overwrite the symbols directory after publishing that version.
