# Atlas Smart Hub Android

Atlas Smart Hub is the temporary native Android entrance-hub app for Gym Atlas Smart Attendance Phase 3.

The app is intentionally separate from the Member and Trainer Flutter apps. It does not identify members and it does not write attendance. Its job is to provision a trusted entrance device, broadcast the Smart Attendance BLE signal, and keep the Laravel Smart Attendance Hub heartbeat alive.

## V1 responsibilities

- Activate/provision a hub with Laravel using the hub UUID and one-time device secret from Gym Admin.
- Store credentials with an Android Keystore-backed AES/GCM key.
- Start a foreground service that keeps the device visible as an operating hub.
- Broadcast BLE with the Atlas service UUID, protocol version, and compact public hub ID.
- Send heartbeat to Laravel every 60 seconds.
- Show gym name, branch name, hub public ID, BLE advertising state, backend connectivity, and last heartbeat.

## Backend contract

The app uses the Phase 1 Smart Attendance endpoints:

- `POST /api/smart-attendance/hubs/{hubUuid}/activate`
- `POST /api/smart-attendance/hubs/{hubUuid}/heartbeat`
- `GET /api/smart-attendance/hubs/{hubUuid}/config`

The raw secret is sent only as `X-GymAtlas-Device-Token`. The secret is never advertised over BLE.

## BLE V1 protocol

The frozen BLE V1 contract is documented in `../docs/smart-attendance-ble-v1.md`. This app implements that contract:

- Service UUID: `8b0f9c60-4f6d-4b40-9e8d-2d5d3f73a1a1`
- Main advertisement: Atlas service UUID only.
- Scan response service data: first byte protocol version, then UTF-8 public hub ID.
- Protocol version: `1`.
- Public hub ID limit: 20 bytes.
- No gym name, branch name, member data, API token, or device secret is advertised.

## Build

```bash
./gradlew -p atlas_smart_hub_android :app:assembleDebug
```

The debug APK is generated at:

```text
atlas_smart_hub_android/app/build/outputs/apk/debug/app-debug.apk
```
