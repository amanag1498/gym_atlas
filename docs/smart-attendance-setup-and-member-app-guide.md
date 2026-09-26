# Gym Atlas Smart Attendance setup and Member app guide

This guide covers the current Smart Attendance flow from Gym Admin setup through Member check-in on Android and iOS. It reflects the implementation through Phase 10, while the audit section specifically validates the Phase 1–5 foundation.

## What the system does

An Android entrance phone runs the separate **Atlas Smart Hub** app. The hub authenticates with Gym Atlas, broadcasts a public BLE identifier, and sends a heartbeat every 60 seconds. A signed-in **Gym Atlas** Member app detects that nearby signal and asks Laravel to record attendance. Laravel remains the authority for the member, selected gym, branch, membership, duplicate rules, and notification.

The BLE signal never contains a member name, member ID, gym name, branch name, API UUID, device secret, or login token.

## Before setup

Confirm the deployed environment has:

- both Smart Attendance migrations applied;
- the Laravel API and queue worker running;
- HTTPS available at the backend URL;
- push notification delivery configured if welcome pushes are required;
- a gym owner or staff user with the `attendance.manage` permission;
- an active gym, active branch, and active member membership;
- an Android 8.0 or newer entrance phone that supports BLE advertising;
- the current Gym Atlas Member app installed on member phones.

The entrance phone and the Member phone both need internet access. BLE detects proximity; the backend still validates and records attendance online.

## 1. Create the hub in Gym Admin

1. Sign in to Gym Admin.
2. Open **Attendance → Smart Attendance**.
3. Under **Create hub**, enter a clear device name such as `Main entrance phone`.
4. Select **Android** and the physical branch where the phone will stay. Prefer a branch-scoped hub over **Gym-wide** for normal entrances.
5. Select **Create hub and show secret**.
6. Keep the confirmation page open. Copy the **Hub UUID** and **Device secret**. The secret is displayed only after creation or rotation.

The **Public ID** is safe to display and is the value broadcast over BLE. Do not enter the Public ID in the hub app in place of the UUID.

## 2. Provision the Android entrance phone

1. Install and open the separate **Atlas Smart Hub** Android app on the entrance phone.
2. Allow the Bluetooth permissions. Notification permission is recommended so staff can see the persistent hub status notification.
3. Leave **Backend base URL** as `https://gymatlas.in` for production.
4. Paste the **Hub UUID** and **Device secret** from Gym Admin.
5. Select **Activate and start hub**.
6. Verify the app shows:
   - **Hub online**;
   - the correct gym and branch;
   - the expected Public ID;
   - **BLE broadcasting: On**;
   - **Backend connectivity: Connected**;
   - a recent heartbeat time.
7. Return to Gym Admin and refresh **Attendance → Smart Attendance**. The hub should show **Online** with a recent heartbeat.

Keep the entrance phone powered, connected to the internet, and close to the entrance. On devices with aggressive battery management, allow the Atlas Smart Hub app to run without battery restrictions. The foreground-service notification should remain visible while the broadcaster is running.

If broadcasting was running before a normal phone restart or app update, Atlas Smart Hub attempts to resume automatically. Android force-stop remains authoritative: after a force-stop, open the hub app and select **Start saved hub**.

## 3. Use Smart Attendance on an Android Member phone

1. Sign in to the **Gym Atlas** Member app.
2. Select the same gym that owns the entrance hub.
3. Complete required consent and enable the attendance-related consent shown by the app.
4. Grant **Nearby devices** access on Android 12 or newer. On Android 11 or older, grant location access because those Android versions gate BLE scanning behind the location permission.
5. Turn on Bluetooth and internet access.
6. Open the Member app and approach the entrance hub. Stay near it for a few seconds so the proximity and signal checks can complete.
7. A successful check-in appears in attendance history and can produce the **Welcome to [gym]** notification.

The Android app uses a low-power scan when it moves to the background, but Android and manufacturer battery policies can suspend or kill the app process. Keep the app open during rollout testing. A force-stopped or killed app is not guaranteed to detect the hub until the member opens Gym Atlas again.

## 4. Use Smart Attendance on an iOS Member phone

1. Sign in to the **Gym Atlas** Member app.
2. Select the same gym that owns the entrance hub.
3. Complete required consent and enable the attendance-related consent shown by the app.
4. Allow Bluetooth access when iOS asks.
5. Turn on Bluetooth and internet access.
6. Keep Gym Atlas open in the foreground while approaching the entrance hub, and remain nearby for a few seconds.
7. Confirm the new entry in attendance history and the welcome notification when notifications are enabled.

The current iOS implementation is foreground-only. iOS background central mode and state restoration belong to Phase 11 and are not enabled yet. Closing, force-quitting, or backgrounding the app can prevent detection.

## 5. Verify the complete flow

Use one test member with an active membership and no attendance record for the current branch-local day.

1. Confirm the hub is **Online** in Gym Admin.
2. Confirm **BLE broadcasting: On** in Atlas Smart Hub.
3. Open Gym Atlas on the test Member phone and select the correct gym.
4. Approach the hub and wait at least three seconds.
5. Confirm exactly one attendance log was created with method `smart_attendance` and the expected hub.
6. Confirm the Member app shows checked-in status/history.
7. Confirm one welcome notification was created. Repeated BLE detections must not create another same-day attendance row or notification when same-day duplicate prevention is enabled.
8. Repeat once on Android and once on a physical iPhone.

## Routine operations

### Move a hub to another branch

Stop broadcasting, update the hub branch in Gym Admin, and restart the Atlas Smart Hub service. Verify the gym and branch shown on the entrance phone before accepting live check-ins.

### Rotate a device secret

1. Select **Rotate secret** in Gym Admin.
2. Copy the newly displayed secret immediately.
3. Open Atlas Smart Hub, replace the saved secret, and select **Activate and start hub**.
4. Verify the hub returns to **Online**.

The old secret stops working immediately. Rotation deliberately returns the hub to a pending state until the device activates with the new secret.

### Disable or retire a hub

Select **Disable** in Gym Admin, then stop broadcasting or clear saved credentials on the entrance phone. Disabling the backend record prevents device authentication and Member check-ins through that hub.

## Troubleshooting

| Symptom | Check | Recovery |
| --- | --- | --- |
| Hub stays **Pending** | UUID/secret, HTTPS URL, internet | Paste the current credentials and activate again. Rotate the secret if it was lost. |
| Hub shows **Offline** | Entrance phone service, internet, heartbeat time, battery restrictions | Open Atlas Smart Hub, grant Bluetooth permissions, start the saved hub, and remove battery restrictions. |
| BLE broadcasting is off | Bluetooth state and BLE advertiser support | Turn on Bluetooth. Use a phone that supports multiple BLE advertisements. |
| Android Member app does not detect | Nearby devices permission, Bluetooth, selected gym, consent, distance | Grant the required permission, reopen Gym Atlas, and test within a few metres of the hub. |
| Older Android does not detect | Location permission and location services | Grant location access required by Android 11 and older, then restart scanning. |
| iPhone does not detect | App foreground state and Bluetooth permission | Keep Gym Atlas open, enable Bluetooth permission in iOS Settings, and approach again. |
| API reports inactive/no matching hub | Hub activation state and selected gym | Activate the hub and select the owning gym in the Member app. |
| Member is rejected | Active profile, membership dates, branch, gym operational access | Correct the membership or branch assignment in Gym Admin. |
| First check-in works but no second check-in occurs | Same-day duplicate policy and secure success cache | This is expected for the same branch-local attendance day. Test again after the gym day changes or with a fresh eligible member. |
| Attendance exists but push is missing | Notification preference, queue worker, Firebase/APNs configuration | Check in-app notification records and delivery/outbox status, then verify the push infrastructure. |

## Current security boundary

BLE V1 broadcasts a stable public hub ID. Backend authentication, gym/member scoping, membership validation, and duplicate protection prevent the BLE packet itself from writing attendance, but a copied V1 signal can still be replayed near or away from the entrance. Use Smart Attendance V1 as a convenience attendance signal. Phase 13 rotating authenticated proofs are required before treating BLE presence as strong anti-spoofing evidence.

Never share the Hub UUID and device secret with members. Store them only in the Atlas Smart Hub app, rotate the secret after suspected exposure, and disable devices that are lost or retired.

## Platform capability matrix

| Capability | Android Member | iOS Member |
| --- | --- | --- |
| Foreground BLE detection | Implemented | Implemented |
| Attendance API submission | Implemented | Implemented |
| Same-day local suppression | Implemented | Implemented |
| Welcome notification | Implemented | Implemented |
| Background detection | Best-effort while process remains alive | Not implemented yet |
| Force-stopped/killed-app detection | Not guaranteed | Not implemented |

## Release sign-off

Code tests and simulator builds cannot prove radio behavior. Before production activation, test the exact entrance-phone model with at least one physical Android Member phone and one physical iPhone. Validate detection distance, repeated entry, midnight in the branch timezone, revoked/expired membership, secret rotation, disabled hub behavior, no-network recovery, and battery-restricted background behavior.
