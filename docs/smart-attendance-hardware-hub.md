# Smart Attendance physical hardware contract

Gym Atlas supports dedicated BLE hardware as well as the Atlas Smart Hub Android app. A physical device is represented by the same branch-scoped Smart Attendance Hub record, uses the same revocable device credential, and is validated by Laravel before a Member attendance record can be written.

## Supported transmitter profile

For the broadest Android and iOS background support, hardware should broadcast the Atlas iBeacon profile continuously:

- Company ID: `0x004C` (Apple iBeacon framing identifier)
- Type and length: `0x02 0x15`
- Proximity UUID: `8b0f9c60-4f6d-4b40-9e8d-2d5d3f73a1a1`
- Major: upper 16 bits of the numeric Hub `id`
- Minor: lower 16 bits of the numeric Hub `id`
- Measured power: `-59` by default, calibrated for the enclosure and installation if possible
- Non-connectable advertising, normally 250–1000 ms interval

The Member apps reconstruct `hub_id = (major << 16) | minor` and submit protocol version 3. Laravel then validates that exact hub against the signed-in member's selected gym, branch, membership, active state, and device activation state. The numeric ID is only a public lookup value; it is not an attendance credential.

Hardware may additionally advertise Atlas BLE V2 for compatibility and diagnostics:

- Service UUID: `8b0f9c60-4f6d-4b40-9e8d-2d5d3f73a1a1`
- Service-data byte 0: `0x02`
- Bytes 1–9: the 13-character public-ID suffix encoded as an unsigned, big-endian base-36 integer

The authenticated config response provides `ble.service_data_hex` so firmware does not need to reproduce the base-36 conversion.

## Provisioning and heartbeat

1. In Gym Admin, open **Attendance → Smart Attendance**.
2. Create an **ESP32** or **Other BLE hardware** hub for the entrance branch.
3. Save the one-time Hub UUID and device secret in protected flash/NVS. Never put either value in BLE advertising.
4. Call `POST /api/smart-attendance/hubs/{hubUuid}/activate` over HTTPS with `X-GymAtlas-Device-Token: {secret}`.
5. Read `data.ble.ibeacon` and configure the radio from those returned values.
6. Send `POST /api/smart-attendance/hubs/{hubUuid}/heartbeat` every 60 seconds. Include the firmware version, battery percentage when available, and whether BLE advertising is active.
7. Refresh `GET /api/smart-attendance/hubs/{hubUuid}/config` after reconnect and periodically so branch changes, beacon configuration, or credential rotation are applied.

Example activation body:

```json
{
  "firmware_version": "atlas-esp32-1.0.0",
  "ble_advertising": true,
  "battery_percent": 100,
  "metadata": {
    "model": "ESP32-C6",
    "radio": "BLE 5"
  }
}
```

Example relevant config response:

```json
{
  "data": {
    "hub": { "id": 1193046, "public_id": "SAHABC123DEF4567" },
    "heartbeat_interval_seconds": 60,
    "ble": {
      "protocol_version": 2,
      "service_uuid": "8b0f9c60-4f6d-4b40-9e8d-2d5d3f73a1a1",
      "service_data_hex": "0202A64921B5366CEA2F",
      "public_id": "SAHABC123DEF4567",
      "ibeacon": {
        "uuid": "8b0f9c60-4f6d-4b40-9e8d-2d5d3f73a1a1",
        "major": 18,
        "minor": 13398,
        "measured_power": -59,
        "protocol_version": 3
      }
    }
  }
}
```

## Offline behavior

Cache the last authenticated beacon config in protected local storage. If the hardware loses Wi-Fi after successful activation, BLE advertising can continue and Member phones can still submit attendance through their own internet connection. Gym Admin will show the hardware offline after heartbeats stop, but the attendance API currently accepts only hubs whose stored status has reached `online`; an already activated hub remains stored as online until disabled or reprovisioned. Credential rotation or disabling a hub stops new attendance validation server-side.

Use a hardware watchdog, brownout recovery, automatic BLE restart, and boot-time config restore. A status LED should distinguish provisioning, BLE active, backend connected, and fault states. Do not expose the device secret through serial logs, a public setup access point, BLE GATT, or the advertising packet.
