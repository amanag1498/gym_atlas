# Gym Atlas Smart Attendance BLE V1 protocol

Status: frozen for Smart Attendance Phase 4. Later phases may add a new protocol version, but Phase 5 Member foreground scanning must implement this V1 contract exactly.

## Purpose

BLE V1 lets a trusted entrance hub tell the Gym Atlas Member app, “you are near a provisioned Smart Attendance hub.” The BLE packet is only a discovery signal. It is not proof of attendance by itself and it must not contain member data, gym data, secrets, API tokens, or anything that can authorize an attendance write.

The Member app will later submit the public hub ID and detection metadata to Laravel. Laravel remains responsible for resolving the hub, validating gym/member scope, applying duplicate rules, and calling the existing attendance service.

## Identifiers

| Field | Value | Notes |
| --- | --- | --- |
| Atlas BLE Service UUID | `8b0f9c60-4f6d-4b40-9e8d-2d5d3f73a1a1` | One global service UUID for all Smart Attendance hubs. |
| Protocol version | `0x01` | Unsigned one-byte integer. |
| Public hub ID | ASCII/UTF-8 string, max 20 bytes | Comes from `smart_attendance_hubs.public_id`, not the API UUID. Current backend format is `SAH` plus 13 uppercase random characters. |

The backend hub `uuid` and raw device secret are never advertised. The public hub ID is stable in V1 and is intentionally only a lookup identifier. Phase 13 can introduce authenticated rotating proof in protocol v2.

## Advertisement layout

V1 uses a non-connectable BLE advertisement with active-scan response data.

### Primary advertisement

| AD structure | Required | Value |
| --- | --- | --- |
| Complete List of 128-bit Service UUIDs | Yes | `8b0f9c60-4f6d-4b40-9e8d-2d5d3f73a1a1` |
| Local name | No | Must be omitted. |
| TX power | No | Should be omitted for packet size; clients may use platform RSSI instead. |
| Connectable | No | Hubs must advertise as non-connectable. |

The primary advertisement contains only the service UUID so it remains inside the legacy 31-byte advertising payload and stays discoverable by Android, iOS foreground scans, and ESP32 broadcasters.

### Scan response

| AD structure | Required | Value |
| --- | --- | --- |
| Service Data - 128-bit UUID | Yes | Service UUID plus V1 payload bytes. |

V1 payload bytes inside the service-data value:

| Offset | Length | Type | Description |
| --- | ---: | --- | --- |
| `0` | 1 byte | unsigned integer | Protocol version. Must be `0x01`. |
| `1..n` | 1 to 20 bytes | UTF-8 ASCII-compatible string | Public hub ID. |

Current example payload for public ID `SAHABC123DEF4567`:

```text
01 53 41 48 41 42 43 31 32 33 44 45 46 34 35 36 37
```

A scanner must ignore packets where:

- the service UUID does not match;
- service data is missing;
- protocol version is not `0x01`;
- the public hub ID is empty;
- the public hub ID exceeds 20 bytes after decoding;
- the public hub ID contains characters outside `[A-Z0-9_-]`.

## Size constraints

V1 is designed for legacy BLE advertising limits:

- Primary advertisement must fit inside 31 bytes.
- Scan response must fit inside 31 bytes.
- Public hub ID is capped at 20 bytes.
- The hub must not include device name, gym name, branch name, member identifiers, tokens, or metadata in BLE.

The Android Phase 3 hub app places the public ID in scan-response service data to avoid exceeding primary advertisement limits with a 128-bit UUID plus service data. ESP32 firmware should use the same split layout.

## Backend relationship

`smart_attendance_hubs.public_id` is the only hub identifier allowed in BLE V1. Member-originated attendance endpoints must accept the public hub ID and resolve it server-side inside the selected gym context.

The backend must not trust BLE alone. Later validation must include at least:

- authenticated member user;
- selected gym from the Member app context;
- active membership/profile for that gym;
- active hub matching the submitted public hub ID;
- hub belongs to the selected gym and, if branch-scoped, maps to the allowed branch behavior;
- duplicate attendance protection in the existing attendance service.

## Client scanning requirements for Phase 5

Member foreground scanning must:

- scan for the Atlas BLE Service UUID;
- use active scanning when the platform supports it so scan response service data is available;
- parse only protocol version `0x01`;
- expose public hub ID, RSSI, detection timestamp, and raw platform source to the Smart Attendance controller;
- debounce repeated detections locally;
- not create attendance in Phase 5.

If a platform surfaces the service UUID but not scan-response service data, the scanner should report a diagnostic-only detection with `public_id = null` and must not proceed to attendance validation.

## Backward compatibility

Protocol versions are additive:

- V1 clients must ignore unknown protocol versions.
- Future hubs may advertise multiple compatible payloads only if packet size allows it.
- If a future protocol requires rotating proof, it should use protocol version `0x02` and keep the same service UUID unless discovery behavior forces a new UUID.
- Backend APIs should accept an explicit `protocol_version` field from Member detections once check-in is implemented.

## ESP32 compatibility

ESP32-C3/C6 firmware for Phase 15 must:

- advertise the same Atlas service UUID;
- set non-connectable advertising mode unless a separate provisioning mode is intentionally entered;
- place service data in the scan response using the same V1 byte layout;
- cap public ID to 20 bytes;
- never store or advertise Laravel API secrets in BLE payloads;
- continue sending backend heartbeat/config through the Phase 1 hub API contract.

## Security and privacy rules

BLE V1 must never advertise:

- member ID, email, phone, name, or membership status;
- gym name, branch name, or owner/staff data;
- API UUID, raw device secret, hashed secret, bearer token, session token, or webhook token;
- attendance state or check-in result;
- continuous tracking metadata.

RSSI and detection timing are local client diagnostics until a member explicitly triggers or qualifies for the later check-in API flow. Continuous RSSI upload is out of scope.
