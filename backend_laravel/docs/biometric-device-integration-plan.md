# Gym Atlas Biometric Device Integration Plan

## Outcome

Gym owners can connect multiple fingerprint, face, palm, or card terminals and enroll a member without inventing or manually reconciling identifiers. Gym Atlas owns the device-user mapping and attendance decision. Devices retain biometric templates and perform biometric matching.

## Supported connection methods

| Method | Typical devices | Member configuration | Attendance delivery |
| --- | --- | --- | --- |
| `remote_provisioning` | Vendor server or SDK with user-management support | Atlas generates an external user ID and queues an `upsert_user` command. Staff capture the biometric on the terminal. | Connector or vendor server sends normalized events. |
| `direct_push` | ADMS/PUSH-capable eSSL/ZKTeco-style terminals | Atlas generates the user ID. Staff enters that displayed ID on the terminal when remote user creation is unavailable. | A certified vendor-protocol endpoint or ADMS bridge translates the terminal payload into the authenticated canonical gateway. |
| `edge_connector` | LAN-only, SDK, DLL, or pull-protocol terminals | Atlas queues commands when the adapter supports them; otherwise it shows guided capture instructions. | Gym Atlas Connector buffers and forwards events over outbound HTTPS. |
| `manual` | USB/export-only or unsupported legacy devices | Atlas generates the user ID and provides guided machine steps. Staff confirms with a test scan. | Desk entry or CSV import remains the fallback. |

Compatibility is certified by vendor, model, firmware, adapter, and capability set—not brand alone. eSSL devices are represented explicitly, with ADMS, ePushServer/connector, LAN connector, and manual fallback paths.

### eSSL eBioServer New production adapter

The recommended eSSL route is now implemented on the Atlas side as `essl_ebioserver`:

- Gym admins store the public HTTPS `Webservice.asmx` URL, API username/password, location code, terminal serial number, and optional 32-character webhook AES password in the encrypted device configuration.
- Atlas returns a one-time, per-device eBioServer webhook URL. The URL credential is stored only as a SHA-256 hash and can be rotated independently for each terminal.
- `POST /api/integrations/essl/ebioserver/{deviceUuid}/{webhookToken}` accepts the official JSON punch shape, rejects biometric images/templates, translates it into the canonical event inbox, and returns the literal `Success` response required by eBioServer. eBioServer has one server-level webhook, so the authenticated anchor URL safely routes every record to a registered device with the same gym, server URL, API identity, and reported serial number.
- Plain JSON, list batches, and eSSL's documented AES-256-CBC webhook payload are supported. The decoder is pinned to the format verified against eSSL's published encrypted fixture instead of accepting speculative cipher variants. Encryption is optional because it must match the eBioServer Utilities setting; HTTPS remains mandatory.
- `biometric:dispatch-ebioserver` translates queued provisioning, deletion, and capture commands into the official `UpdateEmployee`, `DeleteEmployee`, `DeviceCommand_EnrollFP`, and `DeviceCommand_EnrollFace` SOAP calls. It retries transient failures with backoff, recovers commands abandoned by an interrupted worker, and refuses to regress an enrollment that was confirmed or revoked while a vendor request was in flight.
- Laravel schedules command delivery every minute. `schedule:run`/`schedule:work` must therefore be active in production.
- The generic connector gateway is deliberately unavailable to eBioServer devices, preventing the vendor webhook credential from bypassing serial-number validation.
- Provisioning aggregates all active location codes for the member on the same eBioServer connection. Revoking one terminal resynchronizes the remaining locations instead of issuing a global employee deletion; `DeleteEmployee` is used only after the final location is revoked.
- `connected` means Atlas can reach eBioServer or has accepted its webhook. It does not falsely claim that a particular terminal is currently online; terminal-level last-ping status requires the exact licensed eBioServer `GetDeviceLastPing` response contract to be verified against the installed build.

Atlas does not implement or impersonate eSSL's proprietary ADMS server. eBioServer itself remains responsible for communicating with K90 Pro terminals and storing biometric templates. A compatible eBioServer license, K90 firmware configuration, and a publicly reachable eBioServer HTTPS endpoint are deployment prerequisites supplied by eSSL/the gym.

## Member enrollment flow

1. Staff opens the member and selects **Set up biometric**.
2. Atlas lists devices accessible to the member's branch.
3. Staff selects one or more devices and modalities.
4. Atlas generates a numeric, device-compatible external user ID per device.
5. For capable adapters Atlas queues remote creation; otherwise it displays exact guided steps.
6. Staff captures the face/fingerprint/palm on the terminal using the displayed ID.
7. The first verified event or an explicit confirmation changes the link to `enrolled`.
8. Every later event resolves through the device-specific mapping and the existing membership/branch/duplicate attendance rules.

One member can have different external IDs and enrollment states on different devices. Revocation disables attendance immediately and queues device removal where supported.

## Security and privacy boundary

- Never store fingerprint templates or face images in Gym Atlas by default.
- Store only an opaque external user ID, modality, device event metadata, and attendance result.
- Give every device/connector an independently revocable 64-character secret; store only its SHA-256 hash.
- Persist an event before processing it, deduplicate per device, and acknowledge after durable storage.
- Keep both device occurrence time and server receipt time; reject unreasonable event times, measure heartbeat clock drift, and show operators a warning above five minutes.
- Encrypt device configuration/credentials and never return secrets after initial creation/rotation.

## Core persistence

- `biometric_devices`: tenant/branch ownership, adapter, connection method, capabilities, encrypted configuration, credentials, health, and last-contact state.
- `biometric_member_links`: per-device Atlas-member to external-user mapping and enrollment lifecycle.
- `biometric_device_events`: durable idempotent inbox with normalized payload and processing result.
- `biometric_device_commands`: retryable provisioning and revocation work.
- `attendance_logs`: references the trusted device/event and preserves device and receipt timestamps.

## Adapter contract

Adapters expose capabilities and instructions. Vendor-specific transport stays outside attendance policy:

- connection instructions and health test
- remote user provisioning support
- user deletion support
- event push or pull mode
- supported modalities and external-ID constraints

The initial catalog includes generic manual/connector adapters and eSSL ADMS/connector profiles. Exact SDK transports are added only against sanitized fixtures from a certified device/firmware combination.

## Delivery phases

1. Core registry, automatic IDs, enrollment lifecycle, durable events, device authentication, web/API management, and compatibility fallback.
2. Gym Atlas Edge Connector with offline SQLite queue, heartbeat, cursor, and signed updates.
3. Certified eSSL ADMS and ePushServer adapters using real device fixtures.
4. Certified ZKTeco, Hikvision ISAPI, and Suprema BioStar/G-SDK adapters.
5. Optional remote user provisioning and deletion per supported adapter; biometric templates remain vendor-side.
6. Fleet monitoring, credential rotation alerts, and connector update management.

## Production hardening implemented

- Connector commands use a database-locked 30-second lease, are idempotently acknowledged, retry at most 10 times, and then move the member mapping to `sync_error`.
- Repeated enrollment, manual confirmation, and revocation cancel superseded device commands so a late acknowledgement cannot regress the current state.
- Heartbeats record connector version and clock skew without allowing a connector to silently replace a registered serial number.
- A scheduled minute-level reconciler marks devices offline after five minutes and fails exhausted commands even when the connector stops polling.
- Device traffic is throttled by device UUID, avoiding cross-gym contention when several terminals share one public IP.
- Accepted, rejected, unmatched, enrollment, and device-health changes publish after database commit to permission-scoped platform/gym/branch realtime rooms. Ordinary members never join those rooms.
- Unmatched events can be mapped to an in-scope member from the Laravel panel and are processed once through the normal membership, branch, and duplicate-attendance policy.
- Only device status transitions and clock-warning transitions emit heartbeat updates, preventing a healthy connector from flooding the realtime queue.

## Acceptance criteria

- A gym cannot view or mutate another gym's devices, member links, or events.
- A branch-scoped operator cannot configure an inaccessible branch.
- The same vendor event replay creates at most one attendance row.
- The same vendor event ID may exist on different devices.
- Unknown external users remain recoverable as `unmatched` and can be linked/replayed.
- Expired, inactive, or wrong-branch members are rejected by the existing attendance policy.
- Secrets and raw biometric material never appear in API resources, views, logs, or audit payloads.
- Unsupported devices receive a usable guided/manual path rather than a false success state.
