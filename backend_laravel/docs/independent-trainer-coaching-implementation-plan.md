# Independent Trainer Coaching — End-to-End Implementation Plan

## Implementation status — 1 August 2026

All ten delivery phases in this plan are implemented in the working tree:

- Database migrations now cover verification review metadata, invitations, independent relationships, workout/diet attribution, and relationship-specific trainer notes.
- Laravel now enforces verified-and-gym-less trainer eligibility, explicit member consent, coexistence with gym coaching, privacy-safe access, revocation, notifications, audit logs, and signed email review.
- Platform Admin has API and responsive panel workflows to review, approve, reject, and suspend personal-coaching verification for any trainer, including gym-assigned trainers.
- Trainer and Member Flutter apps now expose the separate invitation, roster, plan, and chat flows without replacing the existing gym trainer experience.
- The complete Laravel suite passes with 270 tests and 2,593 assertions. Blade compilation, route discovery, production asset compilation, migration up/down/up validation, Flutter analysis/tests, and realtime TypeScript validation also pass.

Deployment remains an operational step: back up the database, deploy Laravel and both app releases together, run migrations, confirm queues/mail/realtime workers, and smoke-test with one verified trainer plus a member who already has an active gym subscription.

### Second-pass audit closure

A second agent audit traced the actual Laravel, realtime, Trainer App, Member App, and Platform Admin journeys and closed the remaining integration gaps:

- Capability permissions are enforced consistently for workout, diet, progress, note, chat, conversation, and realtime discovery—not only for write endpoints.
- Revocation, suspension, credential re-review, and gym attachment pause protected access immediately while retaining a visible relationship-management path and preserving gym data.
- Revoke and reinvite cycles are append-only; historical relationship rows are retained and a database uniqueness guard permits only one current trainer/email cycle.
- Independent notes now support create, update, complete, list, and follow-up scoping without entering gym-visible note paths.
- Private trainer templates and historical notes/plans no longer leak through null-gym or generic trainer queries.
- The realtime server consumes plural trainer IDs and joins/authorizes every permitted independent trainer-member chat room while retaining the legacy singular gym-trainer field.
- Both apps now expose confirmed revocation, paused-access states, capability-aware actions, invitation history, relationship-scoped member detail, and clearly labeled gym/independent/personal plans.
- Admin verification now has strict state transitions, inactive-account checks, evidence links, material-change re-review, dashboard queues/counts, activity history, and active-relationship protection during gym assignment.
- Expired or otherwise non-actionable invitations are not presented as accept-ready in the Member App.

Final hard-audit validation passes across the entire Laravel suite with no baseline exclusions: 270 tests, 270 passing, and 2,593 assertions. Both Flutter apps analyze cleanly and their complete local test suites pass. Realtime TypeScript type checking, production web asset compilation, Blade compilation, route discovery, migration rollback/reapply testing, and whitespace validation also pass.

### Hard-audit closure

The final audit also repaired adjacent regressions that could have weakened the completed workflow:

- Attendance QR codes are encrypted, short-lived, gym/branch scoped, exposed on both supported Member API paths, and accepted by the scan aliases. Check-in now requires a real active membership; a stale active profile cannot restore attendance after cancellation.
- Gym Staff settings access now requires the scoped `view_reports` grant on both web and API paths instead of passing through role-level dashboard permission.
- Legacy gym-profile submissions normalize inherited fields and expand the existing all-days timing format before validation.
- Platform gym creation always writes a valid onboarding boolean, partial settings updates preserve omitted values, and the Platform Admin owner/listing/impersonation views again expose their operational context clearly.
- Scope-preserving attendance and custom-fee redirects remain intact and their regression expectations now include the selected gym and branch.
- Report tests use a deterministic in-month clock so the first day of a month cannot create false failures for fixtures recorded on the previous day.
- Independent invitation creation and acceptance recheck a locked trainer profile inside their transaction. Platform verification decisions likewise lock and revalidate the current profile before applying a state transition, preventing stale concurrent decisions.

## Objective

Allow a platform-verified trainer who is not assigned to a gym to invite and coach consenting members without changing, replacing, or inheriting any gym membership or gym-assigned trainer relationship.

## Non-negotiable invariants

1. `member_profiles.assigned_trainer_user_id` remains the gym-owned trainer assignment.
2. Independent coaching uses a separate relationship record and never writes to a gym member profile.
3. Gym assignment and platform verification are independent axes: the same trainer may serve gym-assigned members and separately invite personal members after verification.
4. A member must accept an invitation before an independent trainer can access or create member data.
5. Only active trainers with `verification_status = verified` and no gym/branch assignment can create independent invitations or relationships.
6. Independent trainers cannot see gym membership, dues, payments, attendance, internal notes, or other gym-operational records by default.
7. Gym membership lifecycle changes do not activate, suspend, revoke, or delete an independent relationship.
8. Independent relationship revocation does not change a gym membership, gym profile, or gym trainer assignment.
9. Every verification, invitation, acceptance, rejection, revocation, and coaching-data mutation is auditable.
10. Existing gym APIs and Flutter gym flows remain backward compatible.

## Domain model

### Trainer verification metadata

Extend `trainer_profiles` with:

- `verification_status`: strict `pending`, `verified`, `rejected`, or `suspended` state.
- `verification_reviewed_by_user_id`: Platform Admin reviewer.
- `verification_reviewed_at`: last decision time.
- `verification_verified_at`: most recent approval time.
- `verification_rejection_reason`: member-visible rejection/suspension reason where appropriate.
- `verification_review_notes`: internal Platform Admin notes.

Verification changes must be Platform Admin-only and recorded in the platform audit log.

### Independent trainer-member invitation

Create a dedicated invitation record containing:

- Inviting trainer user.
- Existing invited member user when available.
- Normalized invited name/email/phone.
- Status: `pending`, `accepted`, `rejected`, `expired`, or `superseded`.
- Secure token and expiry for email review.
- Response timestamp and invitation metadata.

Existing app accounts receive an in-app notification. Email-only invitees receive a signed review route and must be provisioned as a Member account only after acceptance.

### Independent trainer-member relationship

Create a separate relationship containing:

- Trainer user and member user.
- Type `independent`.
- Status: `active` or `revoked`.
- Source invitation.
- Accepted, revoked, and last-activity timestamps.
- Optional consent/sharing metadata for future granular permissions.

Prevent duplicate active trainer/member pairs. Preserve revoked history for audit.

## API contracts

### Trainer App

- `GET /api/trainer/independent-context`
  - Returns eligibility, independence state, verification status, active relationships, and invitations.
- `GET /api/trainer/independent-member-invitations`
- `POST /api/trainer/independent-member-invitations`
  - Existing Member account: in-app approval.
  - No account: signed email approval.
- `GET /api/trainer/independent-members`
- `POST /api/trainer/independent-members/{relationship}/revoke`

### Member App

- `GET /api/member/independent-trainer-invitations`
- `POST /api/member/independent-trainer-invitations/{invitation}/accept`
- `POST /api/member/independent-trainer-invitations/{invitation}/reject`
- `GET /api/member/independent-trainers`
- `POST /api/member/independent-trainers/{relationship}/revoke`

Existing `/api/member/trainer` and gym invitation contracts remain gym-only.

### Email review

- Signed GET review route.
- Signed POST accept/reject route.
- Expired, superseded, or already-responded invitations cannot be reused.

## Authorization and privacy boundaries

Centralize checks in an independent coaching access service:

- Trainer is active, verified, independent, and owns the relationship.
- Member is the relationship participant.
- Relationship is active for live access.
- Historical records remain readable only according to explicit retention rules.
- Gym-only data is never loaded into independent member resources.

Independent member summaries may expose only:

- Member identity and member-controlled profile fields needed for coaching.
- Relationship-specific notes.
- Independently assigned workouts/diets.
- Member-owned progress data that the product explicitly treats as shareable with an accepted trainer.

They must not expose gym payment, dues, membership, branch attendance, gym staff notes, or gym audit history.

## Workout integration

- Add nullable `independent_trainer_member_relationship_id` to workout plans and relevant sessions if session attribution needs it.
- Independent trainer plans use `gym_id = null`, `branch_id = null`, and the active relationship ID.
- Gym trainer plans retain current gym and branch scope.
- Member plan listings return both current-gym plans and plans attached to active independent relationships.
- Plan resources include `coaching_scope` (`gym`, `independent`, or `personal`) and `relationship_id`.
- Access checks authorize by relationship rather than treating `null` gym IDs as sufficient proof.

## Diet integration

- Make `diet_plans.gym_id` nullable with a null-on-delete foreign key.
- Add nullable `independent_trainer_member_relationship_id`.
- Independent plans require an active relationship and use no gym/branch scope.
- Member listings combine current-gym diets and active independent-relationship diets.
- Existing personal and gym diet behavior remains unchanged.

## Chat and realtime integration

- Existing trainer/member pair chat storage can remain, but authorization must accept either the current gym assignment or an active independent relationship.
- Conversation resources should identify `coaching_scope` and relationship ID where available.
- Member APIs must support multiple trainer conversations.
- Realtime context returns plural trainer IDs/relationship scopes while retaining the existing singular gym trainer field for compatibility.
- Revocation blocks new messages immediately without deleting retained conversation history.

## Notes, progress, and task integration

- Add relationship attribution to independent trainer notes/tasks where required.
- Independent resources must not load gym memberships, payments, dues, or attendance.
- Decide and test which member-owned measurements, weight logs, photos, workout logs, and meal logs are shareable after consent.
- If sharing is not explicitly enabled, default to the smallest safe data set.

## Platform Admin implementation

- Add navigation entry for Trainer Verification.
- List/filter pending, verified, rejected, and suspended trainers.
- Show identity, profile completion, certifications, uploaded proof, activity state, and gym independence state.
- Approve, reject, or suspend with strict validation and audit history.
- Prevent approving a trainer who is currently assigned to a gym for independent access without an explicit product rule.
- Surface counts on the appropriate admin dashboard if consistent with existing dashboard conventions.

## Trainer App implementation

- Display current verification state and actionable reason.
- Show independent coaching controls only for gym-less trainers.
- Disable member invitation until verification is approved.
- Show pending invitations separately from active independent members.
- Label independent members clearly so gym members cannot be confused with them.
- Use privacy-safe independent member detail and eligible plan/chat actions.

## Member App implementation

- Add an Independent Trainer Invitations section with accept/reject.
- Keep the gym-assigned trainer screen and relationship unchanged.
- Add a separate Independent Trainers section supporting multiple relationships.
- Let the member choose the trainer relationship for chat and attributed coaching plans.
- Show plan source clearly: gym trainer, independent trainer, or personal.
- Allow the member to revoke independent coaching without affecting gym membership.

## Membership coexistence, cancellation, and re-enrollment rules

### Gym and independent trainers can coexist

- A member may have one gym-assigned trainer and one or more accepted independent trainer relationships at the same time.
- The gym assignment remains scoped to the member's current gym profile. Independent relationships remain scoped to their accepted relationship records.
- Accepting an independent trainer does not replace the gym trainer and does not change membership, branch, billing, attendance, gym plans, or gym notes.
- A member does not need to remove the gym trainer before an independent trainer can invite them.

### Member-controlled gym trainer removal

- `DELETE /api/member/trainer-assignment` removes only the current gym trainer assignment.
- The operation requires a current active or frozen gym membership and rechecks that membership while the member profile is locked.
- Both the canonical trainer user ID and legacy trainer profile ID are cleared together.
- Gym membership, branch, payments, attendance, and independent relationships are unchanged.
- The removal is audited, the former gym trainer is notified, gym-scoped chat authorization is revoked immediately, and independent chat remains authorized.

### Cancellation and expiry access boundary

- A cancelled, expired, not-yet-started, or otherwise non-operational membership cannot authorize gym-owner, gym-staff, or gym-trainer access to the member profile or editing actions.
- Access checks correlate the membership gym with the member profile gym, so an active membership at another gym cannot unlock a cancelled profile.
- Cancellation clears the gym trainer assignment, marks the gym profile inactive/cancelled, detaches operational gym/branch access, cancels active gym workout sessions, and archives active gym workout and diet plans.
- Historical membership, billing, attendance, plans, and audit rows remain stored for accounting and audit purposes, but former-member operational drilldowns and mutations are blocked.
- Independent trainer relationships, independent plans, and independent chat remain unchanged.

### Same-account, same-gym re-enrollment

- A historical member profile does not block a new invitation after the prior membership is no longer operational.
- Accepting the new invitation creates a new membership row and reactivates the existing gym profile; the previous cancelled membership remains immutable history.
- The new invitation's trainer assignment is used. A former gym trainer is never silently restored.
- Archived plans and cancelled sessions from the old membership cycle remain historical and do not become current again.
- Current membership and realtime context are refreshed so the apps display the new cycle without stale trainer or plan data.

## Completion audit additions

- Member notifications now load independent coaching invitations alongside gym invitations and provide direct Accept and Reject actions with the requested sharing areas shown before consent.
- Trainers can withdraw a pending independent invitation. Cancellation locks the invitation and relationship, marks both cancelled, invalidates app and signed-email acceptance, updates the member's original notification, sends a withdrawal notification, and writes an audit event.
- Platform verification decisions now create critical trainer notifications for pending review, approval, rejection, and suspension. Rejection or suspension reasons are returned in the trainer profile API.
- The Trainer App displays the review reason on the independent status card and profile screen, and provides a confirmation flow for withdrawing pending invitations.
- Notification cards in both apps distinguish independent invitations, verification changes, coaching responses, and revoked coaching relationships.
- Both `/api/member/qr-code` and `/api/member/attendance/qr-code` return the same signed attendance QR contract. Members without an active gym membership receive a safe disabled state instead of a 404 or a usable payload.

## Testing matrix

### Enrollment and verification

- Pending/rejected/suspended/gym-connected trainers cannot invite independently.
- Verified independent trainer can invite existing Member accounts.
- Email-only acceptance provisions a Member without a gym membership.
- Other users cannot accept or reject an invitation.
- Duplicate and expired invitations are handled deterministically.

### Coexistence

- Member with active gym subscription accepts an independent trainer.
- Gym trainer assignment remains unchanged.
- Gym membership, dues, attendance, and gym plans remain unchanged.
- Independent trainer appears separately in Member App responses.
- Leaving the gym preserves the independent relationship.
- Revoking independent coaching preserves the gym relationship.

### Authorization

- Independent trainer cannot access unrelated members.
- Independent trainer cannot access gym billing/attendance data.
- Revoked relationship blocks new plan, diet, note, and chat mutations.
- Gym owners cannot manage independent relationships.
- Platform Admin verification actions are audited.

### Coaching data

- Independent workout and diet plans are visible to both participants.
- Gym and independent plans coexist in member listings.
- Plan edits/deletes stay restricted to the originating scope.
- Chat works for both gym and independent trainers without cross-room leakage.
- Realtime subscriptions include all active authorized relationships.

### Regression

- Existing gym assignment, member invitations, membership, attendance, payment, workout, diet, chat, and notification tests remain green.
- Targeted Flutter analysis/tests pass for both apps.
- Laravel route, migration, Blade, and focused feature validation passes.

## Delivery order

1. Add verification metadata and relationship/invitation schema.
2. Implement models, services, policies, notifications, and consent lifecycle.
3. Implement Platform Admin verification workflow.
4. Add independent Trainer and Member API surfaces.
5. Integrate workouts and diets with relationship attribution.
6. Integrate chat, realtime, notes, tasks, and privacy-safe resources.
7. Update Trainer App.
8. Update Member App.
9. Run focused and broad regression validation.
10. Update public website claims only after the complete release gate passes.

## Definition of done

The feature is complete only when a verified trainer can invite a personal member regardless of gym assignment, the member can explicitly accept, both apps display personal relationships separately from gym context, eligible coaching plans and chat work, gym subscriptions and gym trainer assignments remain untouched, personal coaching cannot expose gym-operational data, revocation works, Platform Admin decisions are auditable, and focused coexistence/security tests pass.
