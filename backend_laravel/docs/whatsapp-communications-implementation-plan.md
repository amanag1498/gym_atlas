# WhatsApp and Unified Communications Implementation Plan

Status: repository production candidate — external Meta, staging, deployment, and store gates remain
Scope: Laravel backend, platform/gym web panels, Member App, Trainer App, and Admin App
Delivery boundary: repository implementation only; production deployment and store uploads are separate operations.

## Product contract

Atlas exposes two communication channels:

1. **In-App** — durable notification feed, realtime refresh, and Firebase push.
2. **WhatsApp** — Meta WhatsApp Business Platform messages, templates, campaigns, automation, delivery status, opt-in, opt-out, and replies.

Existing transactional email remains unchanged as a fallback during migration. It is not exposed as a campaign channel in this feature.

## Non-negotiable rules

- A gym can access only its own WhatsApp accounts, numbers, templates, audiences, campaigns, conversations, and delivery data.
- Platform Admin uses a separate Atlas-owned account. Sending from a gym-owned number requires an explicit gym sender selection and an audit record.
- Access tokens are encrypted at rest, never returned by APIs, never rendered in views, and redacted from logs.
- A stored phone number is not WhatsApp consent. Existing users start without WhatsApp opt-in.
- New gym enrollment includes utility-message permission in the required enrollment confirmation, so members do not face a separate setup step; marketing remains a separate optional choice.
- Utility and marketing consent are tracked separately, with source, wording version, timestamp, and revocation.
- Business-initiated WhatsApp messages use approved templates. Free-form replies are allowed only when the provider reports an open customer-service window.
- Sensitive health, injury, medical, biometric, progress-photo, or identity data is never placed in WhatsApp content.
- Every delivery is idempotent and auditable. External providers are called only after the database transaction commits.
- Queue, webhook, and scheduler failures must be retryable without duplicate member messages.

## Target architecture

```text
Domain event / campaign / automation
                |
                v
       Communication dispatcher
                |
       Transactional outbox
                |
        Recipient eligibility
                |
       +--------+---------+
       |                  |
       v                  v
    In-App             WhatsApp
  DB notification     Consent check
  Realtime event      Approved template
  Queued FCM          Queued Cloud API
       |                  |
       +--------+---------+
                v
      Delivery and engagement log
```

## Phase 1 — Unified notification foundation

- [x] Add channel and transport enums.
- [x] Add auditable notification delivery records.
- [x] Add a transactional, after-commit delivery job.
- [x] Route all `NotificationService::create()` calls through the same realtime and FCM path.
- [x] Remove duplicate event/trial FCM implementations.
- [x] Preserve existing notification preferences and critical-notification behavior.
- [x] Add idempotency, retries, failure recording, and focused tests.

Acceptance criteria:

- One logical notification produces one database feed record.
- Realtime and FCM delivery happen after commit.
- A queue retry cannot create a second notification or delivery record.
- Missing Firebase configuration or device tokens records a skipped/failed delivery without losing the in-app notification.
- Events and trials do not produce duplicate pushes.

## Phase 2 — Firebase parity and app routing

- [x] Add Firebase Messaging registration and lifecycle handling to Admin App.
- [x] Register Admin App tokens with `app_role=admin`.
- [ ] Add a shared notification route registry for Member, Trainer, and Admin apps.
- [x] Support foreground, background, and terminated notification taps in Admin App.
- [x] Refresh token timestamps and prune stale/invalid registrations.
- [ ] Add Firebase configuration, token-health, send-failure, and deep-link tests.

Acceptance criteria:

- Announcement, membership, payment, enrollment, invitation, assignment, trial, event, and supported operational notifications reach the correct app role.
- Notification taps open the correct feature or notification feed.
- Invalid tokens are removed and stale registrations are excluded.

## Phase 3 — WhatsApp accounts, templates, and webhooks

- [x] Add platform Meta configuration and health checks.
- [x] Add tenant-scoped WhatsApp business accounts and phone numbers.
- [x] Implement Meta Embedded Signup callback and secure token exchange API.
- [x] Encrypt credentials and add disconnect/reconnect operations.
- [x] Subscribe each WABA to the verified webhook endpoint.
- [x] Verify webhook challenges and signatures.
- [x] Store and process webhook events idempotently.
- [x] Synchronize template name, language, category, components, quality, and approval state.
- [x] Add platform and gym connection-health interfaces in Admin App, including secure browser handoff.

Acceptance criteria:

- A gym owner connects without copying a raw token.
- Cross-gym account access is impossible.
- A repeated webhook is processed once.
- Disconnected, expired, restricted, or unhealthy numbers cannot send.

## Phase 4 — Consent and manual campaigns

- [x] Normalize WhatsApp destinations to E.164.
- [x] Add utility and marketing consent with evidence.
- [x] Capture auditable utility consent inside public/authenticated gym enrollment, with optional marketing consent and STOP/app opt-out.
- [x] Add Member App utility/marketing consent and opt-out UI plus sender-scoped inbound keyword handling.
- [x] Add campaign drafts, selected channels, channel-specific content, audiences, and recipient snapshots.
- [ ] Add audience types: gym, branch, selected members, plan, expiring, overdue, inactive, trainer, and trial leads.
- [x] Add recipient eligibility preview and exclusion reasons.
- [ ] Add test-send; send-now, scheduling, cancellation, and queued per-recipient delivery are implemented.
- [x] Add per-number throughput control, marketing quiet hours, idempotent duplicate suppression, and delivery-state reconciliation.

Acceptance criteria:

- No WhatsApp send occurs without suitable consent and an approved template.
- Scheduling does not create recipients or notifications before the due time.
- Audience membership is frozen when a campaign starts.
- Delivery totals reconcile to eligible, skipped, sent, delivered, read, and failed recipients.

## Phase 5 — Automations, replies, and platform operations

- [x] Add configurable notification-type automation rules that can attach approved WhatsApp templates to existing lifecycle notifications.
- [x] Let Gym and Platform Admin select In-App, WhatsApp, or both for every canonical notification type; the selected route governs database, realtime, FCM, and WhatsApp delivery.
- [x] Let Gym and Platform Admin create and edit Meta templates, then bind approved utility templates to notification rules with validated safe variable fields.
- [ ] Replace the current daily reminder fan-out with idempotent queued orchestration.
- [x] Add WhatsApp conversations, inbound messages, service-window state, and Gym/Platform Admin inbox reply surfaces; staff assignment remains a later enhancement.
- [x] Allow template replies outside and free-form replies inside the provider service window.
- [ ] Add human escalation and an auditable staff inbox.
- [x] Add Platform Admin global campaigns, isolated platform sender, health, templates, automations, and inbox.
- [ ] Add configurable tenant quotas, emergency suspension controls, and richer analytics dashboards.
- [ ] Add data retention, webhook replay, failed-delivery retry, export, and operational runbooks.

Acceptance criteria:

- Automation rules can be previewed, enabled, paused, resumed, and audited.
- Reconciliation or scheduler reruns do not duplicate communication.
- Inbound replies and opt-outs are never dropped.
- Platform Admin cannot silently impersonate a gym sender.

## Data model

Planned domain tables:

- `notification_deliveries`
- `notification_channel_preferences`
- `communication_outbox`
- `whatsapp_business_accounts`
- `whatsapp_phone_numbers`
- `whatsapp_templates`
- `whatsapp_consents`
- `communication_campaigns`
- `communication_campaign_channels`
- `communication_recipients`
- `communication_automation_rules`
- `whatsapp_webhook_events`
- `whatsapp_conversations`
- `whatsapp_messages`

## Queue lanes

- `notifications`: communication outbox and recipient orchestration.
- `push`: FCM delivery.
- `whatsapp`: rate-limited WhatsApp sends.
- `webhooks`: verified Meta webhook processing.
- `realtime`: existing realtime publication.

Every job defines retry/backoff behavior, an idempotency key, and a terminal failure record. Campaign expansion and delivery use chunked IDs rather than serializing model collections.

## Permissions

- `communications.view`
- `communications.manage`
- `campaigns.send`
- `whatsapp.connect`
- `whatsapp.templates.manage`
- `whatsapp.inbox.reply`
- `platform.communications.manage`

Existing announcement and notification permissions remain compatible while these permissions are introduced and seeded.

## Rollout gates

1. Laravel migrations and focused/full test suites pass.
2. Member, Trainer, and Admin apps analyze and test cleanly.
3. Queue worker and scheduler health are verified in staging.
4. Meta App Review and required advanced permissions are approved.
5. A staging WABA passes connection, template, webhook, opt-in, opt-out, retry, and delivery-status tests.
6. Backend deploys before updated mobile apps.
7. Mobile releases pass closed/TestFlight testing before production rollout.
8. Store upload, production deployment, and campaign activation require separate explicit approval.

## Release note

The existing `1.0.2 (12)` Member artifacts were built before this communications work. They must not be uploaded as the final release for this feature. Fresh versions and build numbers will be created only after all relevant phases and release gates are complete.

## Implemented repository surfaces

- Unified notification delivery: database, realtime, Firebase, delivery audit, outbox retry, and stale-token pruning.
- Admin App Firebase: token lifecycle with `app_role=admin`, Android permission, iOS push entitlement/background mode, and tap routing into the relevant admin workspace.
- WhatsApp connection APIs for Gym and Platform Admin: Embedded Signup exchange, encrypted token storage, number validation, app subscription, health state, disconnect, and template sync.
- Verified `/api/webhooks/whatsapp` GET/POST endpoints with HMAC verification, replay protection, delivery/read state processing, inbound message retention, service-window tracking, and keyword opt-out.
- Member consent APIs at `/api/member/whatsapp-consents` with separate utility and marketing purposes.
- Gym and Platform Admin campaign APIs with previews, exclusion reasons, frozen recipients, scheduling, cancellation, in-app delivery, approved-template WhatsApp delivery, and analytics-ready statuses.
- Gym inbox APIs with in-window text replies and approved-template replies outside the service window.
- Gym and Platform Admin automation rule APIs with the complete canonical trigger catalog and independent In-App/WhatsApp routing. Rules can send through either channel or both.
- Gym and Platform Admin template creation/edit submission to Meta, approval-state sync, and validated runtime values for `{member_name}`, `{notification_title}`, `{notification_message}`, `{gym_name}`, and `{branch_name}`.
- Expiring, one-time Embedded Signup browser sessions for Gym and Platform Admin; raw access tokens never pass through the mobile app or user-entered fields.
- Admin App Communications workspace for connection health, template sync, campaign draft/preview/send, utility automations, and inbox replies.
- Member App utility and marketing WhatsApp consent controls scoped to the selected gym.
- Recovery dispatch for abandoned outbox locks, campaign recipients, campaigns, and webhook events.
- Sender-scoped STOP handling, monotonic sent/delivered/read/failed receipt updates, token-expiry checks, provider throughput limits, and marketing quiet hours.

## Remaining repository work before release

- Add equivalent Communications pages to the legacy Laravel web panels if those panels must remain a primary management surface; the Admin App and all APIs are implemented.
- Obtain legal/product approval for the final consent wording before production activation.
- Extract and adopt the shared notification-route registry in all three apps; expand routing tests for every notification type.
- Add test-send, tenant campaign quotas, emergency suspension, exports, operator-triggered webhook replay controls, and retention jobs.
- Add specialized audience builders for plan, expiring, overdue, inactive, trainer, and trial-lead segments; the current implementation supports gym, branch, selected members, and platform members.
- Complete Meta App Review, live WABA testing, provider error mapping, and production observability dashboards.

## Production steps after repository work is approved

1. Create/configure the Meta app, WhatsApp product, Embedded Signup configuration, approved redirect/origin domains, system-user access, and required permissions.
2. Configure production secrets: `META_APP_ID`, `META_APP_SECRET`, `META_WHATSAPP_EMBEDDED_SIGNUP_CONFIG_ID`, `META_WHATSAPP_WEBHOOK_VERIFY_TOKEN`, the reviewed `META_GRAPH_VERSION`, Firebase Admin credentials, and `WHATSAPP_DEFAULT_COUNTRY_CODE`.
3. Configure the Meta webhook callback as `https://<api-host>/api/webhooks/whatsapp`, subscribe the WABA app, and verify messages/template/status webhook fields.
4. Back up the database, deploy backend code, run `php artisan migrate --force`, clear/rebuild Laravel config and route caches, and run the permission seeder through the normal deployment procedure.
5. Run supervised queue workers for `notifications`, `whatsapp`, `webhooks`, `realtime`, and the existing default queues; use Redis (or another shared production cache) for sender rate limits and keep the Laravel scheduler running every minute.
6. Run `php artisan notifications:fcm-health`, verify Admin/Member/Trainer token counts, connect one staging WABA, sync templates, and test consent, opt-out, send, delivery, read, inbound reply, retry, and tenant isolation.
7. Finish and verify the mobile UI work, then create fresh Member, Trainer, and Admin builds. Release through closed testing/TestFlight before production. Do not reuse the pre-feature `1.0.2 (12)` Member artifacts.
8. Enable automations gradually per gym after delivery/error dashboards and emergency suspension are operational. Store upload and production activation require separate approval.

## Production-readiness audit — 2026-08-22

Closed during the revisit:

- Scheduled announcements no longer create member notifications before `send_at`.
- In-app and WhatsApp preferences are evaluated independently; WhatsApp-only lifecycle delivery stays out of the in-app feed and FCM/realtime.
- Admin automation routing now governs actual delivery instead of only being stored: In-App-off suppresses the feed, realtime event, and Firebase push, while WhatsApp can continue independently.
- The Admin App loads every canonical notification trigger from the backend instead of offering four hard-coded types, and supports template creation/editing plus safe per-rule and per-campaign variable values.
- Enrollment confirmation records utility WhatsApp permission as evidence with immediate STOP/app opt-out; marketing remains separately optional.
- Nullable tenant scopes now use non-null canonical scope keys, preventing duplicate global preference, consent, and automation rows.
- Abandoned processing locks are recovered after bounded stale windows for notification outbox, campaigns, recipients, and WhatsApp webhooks.
- Webhook receipts update campaign recipients, messages, and unified notification deliveries without allowing delayed receipts to downgrade a later status.
- STOP affects only consent for the WhatsApp sender that received it, rather than every gym using the member phone number.
- Connected accounts must be healthy and unexpired before campaign, automation, or inbox sends.
- The Graph API version is an explicit reviewed production setting rather than a silently stale application default.
- Admin App and Member App provide the missing operational and consent surfaces; Platform Admin has its own isolated account, campaigns, automations, and inbox.
- Notification audit found one intentional direct Firebase path for ephemeral chat alerts. Durable application notifications use `NotificationService`, transactional outbox, realtime, and FCM.

Validation evidence from this repository state:

- Laravel: 354 tests passed, 3,395 assertions.
- Admin App: `flutter analyze` clean; widget tests passed.
- Member App: `flutter analyze` clean; 15 tests passed.
- Trainer App notification regression: `flutter analyze` clean; 11 tests passed.
- `git diff --check` clean.

This evidence makes the code a production candidate, not a live production activation. Meta App Review, real WABA/template/webhook tests, production secrets, queue/scheduler supervision, deployment, closed mobile testing, and store releases remain external gates and require explicit approval.
