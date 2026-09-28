# Atlas Smart Hub Android

Atlas Smart Hub is the temporary native Android entrance-hub app for Gym Atlas Smart Attendance Phase 3.

The app is intentionally separate from the Member and Trainer Flutter apps. It does not identify members and it does not write attendance. Its job is to provision a trusted entrance device, broadcast the Smart Attendance BLE signal, and keep the Laravel Smart Attendance Hub heartbeat alive.

## Responsibilities

- Activate/provision a hub with Laravel using the hub UUID and one-time device secret from Gym Admin.
- Store credentials with an Android Keystore-backed AES/GCM key.
- Start a foreground service that keeps the device visible as an operating hub.
- Broadcast BLE with the Atlas service UUID, protocol version, and compact public hub ID.
- Start BLE immediately from the encrypted saved Public ID after the first successful activation, including after reboot with no Wi-Fi.
- Send heartbeat to Laravel every 60 seconds, refresh assignment changes, and reconnect immediately when internet returns.
- React immediately when Bluetooth is turned off or restored instead of waiting for the next heartbeat.
- Show gym, branch, public ID, advertising mode, backend connectivity, heartbeat, battery, and actionable errors after the app is reopened.
- Fall back to extended BLE advertising when a device rejects the compact legacy packet as too large.

## Backend contract

The app uses the Phase 1 Smart Attendance endpoints:

- `POST /api/smart-attendance/hubs/{hubUuid}/activate`
- `POST /api/smart-attendance/hubs/{hubUuid}/heartbeat`
- `GET /api/smart-attendance/hubs/{hubUuid}/config`

The raw secret is sent only as `X-GymAtlas-Device-Token`. The secret is never advertised over BLE.

## Offline operation

Internet is required once to activate the entrance phone and receive its public hub ID. After that activation, the app stores the credentials with Android Keystore-backed encryption and can start BLE broadcasting without Wi-Fi or mobile data. A normal reboot resumes the foreground service when it was running before shutdown.

While the entrance phone is offline, the app shows **Hub broadcasting offline** and Gym Admin eventually shows the hub as **Offline** because it cannot receive heartbeats. BLE check-in detection continues. The Member phone still needs its own internet connection to send the attendance request to Laravel. When the hub internet connection returns, its next scheduled heartbeat restores backend connectivity automatically.

## Entrance-phone setup

1. In Gym Admin, open **Attendance → Smart Attendance**, create or select the Hub, and copy its UUID and current one-time Device secret.
2. On the entrance Android phone, open **Atlas Smart Hub**, confirm Bluetooth and Nearby devices are ready, and set battery access to **Unrestricted**.
3. Paste the UUID and Device secret, then tap **Activate and start hub** once while online.
4. Confirm the live card says **Hub online**, **Entrance BLE signal: Active**, and **Backend connectivity: Connected**.
5. Leave the persistent Hub notification enabled. After this first setup, BLE can restart after a reboot and continue when the Hub phone loses internet.

If the screen reports that the advertiser is busy, stop another Bluetooth broadcaster such as Nearby Share and tap **Refresh live status**. If it reports a phone Bluetooth error, toggle Bluetooth and refresh. A revoked/deleted Hub stops broadcasting until it is activated with current credentials.

## BLE protocol

The deployed packet is documented in `../docs/smart-attendance-ble-v2.md`. The Member app continues to parse the original V1 packet during rollout:

- Service UUID: `8b0f9c60-4f6d-4b40-9e8d-2d5d3f73a1a1`
- Main advertisement: Atlas service UUID only.
- Scan response service data: protocol byte `2`, then the 13-character public-ID suffix packed as nine base-36 bytes.
- Total service-data value: 10 bytes, which fits the 31-byte legacy BLE scan-response limit alongside a 128-bit UUID.
- On devices that still reject the legacy packet with Android error 1, the same UUID and service data are sent through an extended, non-connectable advertisement.
- No gym name, branch name, member data, API token, or device secret is advertised.

## Build

```bash
./gradlew -p atlas_smart_hub_android :app:assembleDebug
```

The debug APK is generated at:

```text
atlas_smart_hub_android/app/build/outputs/apk/debug/app-debug.apk
```
