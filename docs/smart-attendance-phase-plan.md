# Gym Atlas Smart Attendance implementation plan

This plan turns Smart Attendance into a separate hub-based attendance system that can start with an Android entrance phone and later move to ESP32 without changing the Member app or Laravel contract. The current biometric integration remains separate because biometric devices are terminal/enrollment specific and include biometric-member concepts that should not become the BLE hub contract.

## Current architecture constraints

- Backend is Laravel.
- Gym Admin and Platform Admin are Laravel surfaces.
- Member and Trainer apps are Flutter.
- Existing attendance write behavior stays inside `AttendanceService::recordCheckIn()`.
- Existing biometric device integration already has device UUID/secret, heartbeat, replay protection, event monitoring, and branch scoping.
- Phase 1 must not add BLE, Flutter code, check-out, offline queues, rotating BLE tokens, or attendance behavior changes.

## Phase 1 — Backend Smart Hub foundation

### Goal

Create a new backend abstraction named Smart Attendance Hub. A hub represents a trusted physical entrance broadcaster such as a dedicated Android device or future ESP32. It has its own identity, secret, status, branch assignment, heartbeat, and basic config API.

### Data model

Create `smart_attendance_hubs` with:

- `id`
- `uuid`: private API identity used in device URLs
- `public_id`: compact public hub ID that can later be advertised over BLE
- `gym_id`
- `branch_id` nullable if the gym-level hub is needed later
- `created_by_user_id` nullable
- `name`
- `platform`: `android` or `esp32`
- `device_secret_hash`: SHA-256 hash of a per-hub secret
- `status`: `pending`, `online`, `offline`, `disabled`
- `is_active`
- `last_seen_at`
- `firmware_version` nullable
- `metadata` JSON nullable
- timestamps

Add relationships:

- Hub belongs to Gym.
- Hub belongs to Branch.
- Hub belongs to creator User.
- Gym has many Smart Attendance Hubs.
- Branch has many Smart Attendance Hubs.
- User has many created Smart Attendance Hubs.

### Backend services

Create `App\Services\SmartAttendance\SmartAttendanceHubService`.

Responsibilities:

- Create a hub with unique UUID, unique public ID, and one-time raw secret.
- Hash the secret before storage.
- Update name, branch, platform, firmware, active state, and metadata.
- Rotate credentials and return the new one-time raw secret.
- Activate/provision a hub from a device with valid credentials.
- Accept heartbeat and update status/last-seen fields.
- Build basic device config for the hub app.
- Provide online/offline/effective status helper behavior.

### Gym Admin backend support

Add API CRUD/provisioning routes under existing gym admin API conventions using `attendance.manage`:

- `GET /api/gym/smart-attendance-hubs`
- `POST /api/gym/smart-attendance-hubs`
- `PUT /api/gym/smart-attendance-hubs/{hub}`
- `POST /api/gym/smart-attendance-hubs/{hub}/toggle`
- `POST /api/gym/smart-attendance-hubs/{hub}/rotate-secret`

Add web Gym Admin backend routes beside biometric devices using `attendance.manage`:

- `GET /gym/smart-attendance-hubs`
- `POST /gym/smart-attendance-hubs`
- `PUT /gym/smart-attendance-hubs/{hub}`
- `POST /gym/smart-attendance-hubs/{hub}/toggle`
- `POST /gym/smart-attendance-hubs/{hub}/rotate-secret`

The Phase 1 web surface can be a simple management page. Phase 2 can polish navigation placement and UI around the attendance/biometric area.

### Device-side API

Add public device endpoints that use per-hub credentials. Match the biometric pattern of URL identity plus `X-GymAtlas-Device-Token` header.

- `POST /api/smart-attendance/hubs/{hubUuid}/activate`
- `POST /api/smart-attendance/hubs/{hubUuid}/heartbeat`
- `GET /api/smart-attendance/hubs/{hubUuid}/config`

Security rules:

- Authenticate by UUID and raw secret header.
- Store only a hash of the secret.
- Reject inactive hubs.
- Never return the raw secret except immediately after create/rotation.
- Do not expose member or attendance data through hub config.

### Validation

Create request classes:

- `StoreSmartAttendanceHubRequest`
- `UpdateSmartAttendanceHubRequest`
- `SmartAttendanceHubHeartbeatRequest`

Validation details:

- Platform is `android` or `esp32`.
- Branch, when provided, must exist and belong to the selected gym/access scope.
- Name max 160.
- Firmware max 120.
- Metadata optional array.

### Tests

Add focused feature tests for:

- Gym admin can create a hub and receives one-time credentials.
- Gym admin can list/update/toggle/rotate a scoped hub.
- Admin cannot manage a hub from another gym.
- Device activation requires the per-hub secret.
- Heartbeat updates status, firmware and last seen.
- Config endpoint returns only non-sensitive gym/branch/hub config.

### Assumptions for Phase 1

- `attendance.manage` is the closest existing permission for hub management.
- Branch assignment is nullable at the schema level for future flexibility, but UI/API can require branch where current gym operations need it.
- Device `public_id` is separate from API `uuid` because the BLE protocol should eventually advertise only a compact public identifier.
- No attendance logs are created in Phase 1.

### Phase 1 completion checklist

- Migration added.
- Model and relationships added.
- Service added.
- Gym Admin API controller added.
- Gym Admin web controller and basic Blade view added.
- Device gateway controller added.
- Routes added.
- Tests passing.
- No Flutter or BLE changes.
- No `AttendanceService` behavior changes.

## Phase 2 — Gym Admin management UI

After Phase 1 is clean, improve Gym Admin UI placement and polish.

- Add Smart Attendance Hubs beside attendance/biometric management.
- Reuse branch selection and `attendance.manage` permission conventions.
- List name, public ID, branch, platform, active/inactive, online/offline, and last heartbeat.
- Allow create, branch assignment, activate/deactivate, activation flow viewing, and credential regeneration.
- Keep Blade styling consistent with the current Gym Admin panel.
- Do not redesign unrelated pages.

## Phase 3 — Temporary Android Hub app

Create a separate lightweight native Kotlin Android app named Atlas Smart Hub.

V1 responsibilities:

- Activate/provision with Laravel.
- Store credentials securely.
- Broadcast BLE.
- Send heartbeat.
- Show hub status.

Do not implement member identification or attendance logic.

Active screen must show gym name, branch name, hub public ID, BLE broadcasting status, backend connectivity, and last heartbeat.

BLE V1 advertises one global Atlas Service UUID, compact hub public ID, and protocol version. It must not advertise a secret, gym name, member info, or API tokens.

### Phase 3 implementation note

The temporary Android hub app lives in `atlas_smart_hub_android/`. It is a standalone Kotlin Android app named Atlas Smart Hub, separate from the Member and Trainer Flutter apps. It provisions against the Phase 1 hub endpoints, stores credentials with Android Keystore-backed AES/GCM storage, starts a foreground service, broadcasts the Atlas BLE service UUID with protocol v1 and public hub ID in scan-response service data, and sends heartbeat every 60 seconds.

The app intentionally does not perform member identification, member scanning, attendance writes, offline queues, rotating BLE proofs, or Member app integration. Those remain later phases.

## Phase 4 — Freeze BLE protocol

Document Atlas BLE V1 before touching the Member app.

Define service UUID, advertisement fields, public hub ID encoding, protocol version, size constraints, backward compatibility, and ESP32 compatibility. No PII, member ID, secret, gym name, or branch name in BLE.

### Phase 4 implementation note

The frozen V1 protocol is documented in `docs/smart-attendance-ble-v1.md`. Phase 5 Member foreground scanning must use that document as the source of truth. V1 uses the global Atlas BLE service UUID `8b0f9c60-4f6d-4b40-9e8d-2d5d3f73a1a1`, protocol byte `0x01`, and a max-20-byte public hub ID in scan-response service data.

## Phase 5 — Member Flutter foreground BLE detection

Add only foreground scanning to the Member app inside `lib/src/features/smart_attendance/`.

Scan for Atlas BLE Service UUID, parse protocol version and public hub ID, and expose detections through a clean service/controller API. Do not send attendance yet.

### Phase 5 implementation note

The Member app foreground BLE detection layer lives in `flutter_member_app/lib/src/features/smart_attendance/`. It includes a V1 BLE parser, platform-channel scanner, and controller with local duplicate suppression. Android and iOS foreground platform bridges emit raw BLE service data and RSSI into Dart; Dart owns protocol validation using `docs/smart-attendance-ble-v1.md`. Phase 5 does not call a backend attendance/check-in endpoint and does not write attendance.

## Phase 6 — Backend member validation endpoint

Add member-originated Smart Attendance check-in endpoint that accepts hub public ID and detection metadata, resolves selected gym from `X-Gym-Id`, validates hub/gym/membership on the backend, then calls `AttendanceService::recordCheckIn()`.

## Phase 7 — Member-side duplicate suppression

Cache successful check-ins by member/gym/hub/local attendance date to avoid repeated API calls. Keep backend duplicate protection unchanged.

### Phase 7 implementation note

The Member app now stores successful Smart Attendance check-ins in a secure local cache keyed by member, selected gym, hub public ID, and local attendance date. The cache is checked before submitting a repeated foreground BLE check-in and is cleared with local session storage on logout. Backend duplicate protection remains unchanged and still protects against cross-device, reinstall, and stale-cache cases.

## Phase 8 — Connect foreground BLE to attendance

Connect qualified foreground hub detection to the check-in endpoint with debounce, RSSI threshold, presence window, and parallel request suppression.

### Phase 8 implementation note

The Member app now starts foreground Smart Attendance scanning after authenticated required consent, stops it on logout or missing consent, and submits qualified BLE detections to `POST /api/member/attendance/smart-check-in`. The controller requires a minimum RSSI, a sustained presence window, per-hub request debounce, and in-flight suppression before posting through `MemberRepository`.

## Phase 9 — Welcome notification

Queue a member push notification only after a newly created Smart Attendance check-in. Do not notify for duplicates.

### Phase 9 implementation note

A successful Smart Attendance check-in now creates a member notification with type `smart_attendance_check_in`, app role `member`, the attendance log ID, hub ID, hub public ID, and gym/branch context. Duplicate backend check-ins fail before notification creation, so duplicates do not send welcome notifications.

## Phase 10 — Android member background BLE

Add Android background BLE detection with minimal permissions, battery-safe scanning, diagnostics, and no background location dependency where possible.

### Phase 10 implementation note

The Member app now switches Android Smart Attendance scanning to a low-power background BLE mode when the app leaves the foreground, then returns to low-latency foreground scanning on resume. Background detections use the same Dart parsing, RSSI, presence-window, debounce, same-day success cache, and backend endpoint as foreground detections. Android 12+ BLE scanning continues to use `BLUETOOTH_SCAN` with `neverForLocation`; pre-Android-12 devices still depend on Android's legacy BLE/location permission model.

## Phase 11 — iOS foreground then background

Implement iOS foreground CoreBluetooth first, then add background central mode/state restoration with documented limitations.

## Phase 12 — Diagnostics mode

Add developer-only local diagnostics for hub ID, RSSI, detection timing, app state, and attendance API result. Do not upload continuous RSSI data.

## Phase 13 — Security hardening

Upgrade BLE from static public hub ID to authenticated rotating proof. Hub secret stays only on hub/backend. Member app acts as transport.

## Phase 14 — Offline support

Persist pending smart attendance events when offline, retry with expiration, and validate historical proof on the backend.

## Phase 15 — ESP32 replacement

Implement ESP32-C3/C6 firmware using the same service UUID, protocol version, public hub ID format, rotating proof format, provisioning model, heartbeat/config contract, OTA, watchdog, and status LED behavior.
