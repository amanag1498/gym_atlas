# Gym Atlas Smart Attendance setup and Member app guide

This guide covers the current Smart Attendance flow from Gym Admin setup through Member check-in on Android and iOS. It reflects the implementation through Phase 10, while the audit section specifically validates the Phase 1–5 foundation.

## What the system does

An Android entrance phone runs the separate **Atlas Smart Hub** app. The hub authenticates with Gym Atlas, broadcasts a public BLE identifier, and sends a heartbeat every 60 seconds. A signed-in **Gym Atlas** Member app detects that nearby signal and asks Laravel to record attendance. Laravel remains the authority for the member, selected gym, branch, membership, duplicate rules, and notification.

The first qualified presence starts a six-hour attendance window and becomes the in time. Further qualified detections inside that window update the visit's last-presence timestamp. After two hours without another presence, the latest timestamp becomes the out time. A return inside the original six-hour window reopens and updates the same visit; a presence after the window starts a new visit.

The BLE signal never contains a member name, member ID, gym name, branch name, API UUID, device secret, or login token.

## Before setup

Confirm the deployed environment has:

- all Smart Attendance migrations applied, including the attendance presence-session fields;
- the Laravel API and queue worker running;
- HTTPS available at the backend URL;
- push notification delivery configured if welcome pushes are required;
- a gym owner or staff user with the `attendance.manage` permission;
- an active gym, active branch, and active member membership;
- an Android 8.0 or newer entrance phone that supports BLE advertising;
- the current Gym Atlas Member app installed on member phones.

The entrance phone needs internet for its first activation. After that one-time setup, it can broadcast BLE without Wi-Fi or mobile data. The Member phone still needs its own internet connection because Laravel validates and records attendance online.

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

Keep the entrance phone powered and close to the entrance. Internet is optional after activation, although a connection lets Gym Admin receive heartbeats and show the hub as online. On devices with aggressive battery management, allow the Atlas Smart Hub app to run without battery restrictions. The foreground-service notification should remain visible while the broadcaster is running.

If broadcasting was running before a normal phone restart or app update, Atlas Smart Hub attempts to resume automatically. Android force-stop remains authoritative: after a force-stop, open the hub app and select **Start saved hub**.

### Verify hub-only offline operation

1. Complete activation while the entrance phone is online and confirm a Public ID is shown.
2. Turn off Wi-Fi and mobile data on the entrance phone.
3. Restart the entrance phone, or stop and select **Start saved hub**.
4. Confirm Atlas Smart Hub shows **Hub broadcasting offline** and **BLE broadcasting: On**.
5. Keep internet enabled on a Member phone and approach the entrance. Confirm attendance is recorded normally.
6. Restore internet on the entrance phone. Within the next heartbeat interval, the app should show **Hub online** again without reprovisioning.

Gym Admin will eventually label the hub **Offline** while the entrance phone has no internet because heartbeats cannot reach Laravel. That label describes backend connectivity; it does not mean the saved BLE broadcaster has stopped.

## 3. Use Smart Attendance on an Android Member phone

1. Sign in to the **Gym Atlas** Member app.
2. Select the same gym that owns the entrance hub.
3. Complete required consent and enable the attendance-related consent shown by the app.
4. Grant **Nearby devices** access on Android 12 or newer. On Android 11 or older, grant location access because those Android versions gate BLE scanning behind the location permission.
5. Turn on Bluetooth and internet access.
6. Open the Member app and approach the entrance hub. Stay near it for a few seconds so the proximity and signal checks can complete.
7. A successful check-in appears in attendance history and can produce the **Welcome to [gym]** notification.

On Android, background mode runs as a foreground BLE service with an ongoing Smart Attendance notification. If Flutter is detached while the native service remains alive, the service retains the latest detection for each nearby hub and replays it when the app reconnects. Manufacturer battery policies can still restrict the service. Force-stop disables all Android background work until the Member opens Gym Atlas again.

## 4. Use Smart Attendance on an iOS Member phone

1. Sign in to the **Gym Atlas** Member app.
2. Select the same gym that owns the entrance hub.
3. Complete required consent and enable the attendance-related consent shown by the app.
4. Allow Bluetooth access when iOS asks.
5. Turn on Bluetooth and internet access.
6. Open Gym Atlas once after installation and remain nearby for a few seconds while the first presence is recorded.
7. Confirm the new entry in attendance history and the welcome notification when notifications are enabled.

iOS uses CoreBluetooth background-central mode and state restoration. iOS controls scan frequency and wake-up timing, and force-quitting the app prevents restoration until the Member opens Gym Atlas again.

## Attendance session timing

- **In time:** the first qualified hub presence accepted by Laravel.
- **Attendance window:** six hours from that first presence.
- **Last presence:** refreshed by later valid detections during the window.
- **Out time:** the last presence timestamp after two continuous hours without another detection, or when the six-hour window ends.
- **Return during the window:** clears the provisional out time and continues the same visit.
- **Return after the window:** creates a new attendance visit.

The Member app stores the active visit in encrypted local storage so lifecycle changes and normal restarts do not lose the timing state. Laravel also runs `attendance:finalize-smart-visits` every minute; this server reconciliation saves the out time when a mobile operating system delays or suspends the app timer.

## 5. Verify the complete flow

Use one test member with an active membership and no attendance record for the current branch-local day.

1. Confirm the hub is **Online** in Gym Admin.
2. Confirm **BLE broadcasting: On** in Atlas Smart Hub.
3. Open Gym Atlas on the test Member phone and select the correct gym.
4. Approach the hub and wait at least three seconds.
5. Confirm exactly one attendance log was created with method `smart_attendance`, the expected in time, and the expected hub.
6. Stay near the hub and confirm later detections advance `last_presence_at` without creating another visit or welcome notification.
7. Stop the hub signal or move the Member phone away. After two hours, confirm the out time equals the final presence timestamp.
8. Return within six hours of the original in time and confirm the same visit becomes active again. Test after the six-hour boundary and confirm a new visit is created.
9. Repeat once on Android and once on a physical iPhone.

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
| Hub shows **Offline** in Gym Admin but the app says **Hub broadcasting offline** | Entrance phone internet and BLE state | This is expected without entrance-phone internet. Member check-ins continue when Member phones have internet. Restore hub internet to resume heartbeats. |
| Hub shows **Offline** and BLE broadcasting is off | Entrance phone service, Bluetooth, permissions, battery restrictions | Open Atlas Smart Hub, grant Bluetooth permissions, start the saved hub, and remove battery restrictions. |
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
| Six-hour visit session | Implemented | Implemented |
| Two-hour absence out time | App timer plus server reconciliation | App timer plus server reconciliation |
| Welcome notification | Implemented | Implemented |
| Background detection | Foreground BLE service with queued native detections | CoreBluetooth background central and restoration |
| Force-stopped/killed-app detection | Force-stop disables scanning; ordinary process loss can recover through the service queue | Force-quit disables restoration until reopened |

## Release sign-off

Code tests and simulator builds cannot prove radio behavior. Before production activation, test the exact entrance-phone model with at least one physical Android Member phone and one physical iPhone. Validate detection distance, repeated entry, midnight in the branch timezone, revoked/expired membership, secret rotation, disabled hub behavior, no-network recovery, and battery-restricted background behavior.
