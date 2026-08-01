# Mail and Notification Boundary Audit and Implementation

## Goal

Make gym communication tenant-safe while keeping platform and independent-coaching communication functional. Replace generic transactional email presentation with contextual member, gym, branch, plan, payment, and action details.

## Communication boundary

| Communication source | Independent member | Current gym member | Former or cancelled member | Notes |
| --- | --- | --- | --- | --- |
| Platform-wide announcement | Allowed | Allowed | Allowed | Platform communication is not a gym membership privilege. |
| Gym or branch announcement | Blocked | Allowed | Blocked | Recipient must have a currently accessible profile for the exact gym and branch. |
| Selected-member gym announcement | Blocked | Allowed | Blocked | Raw user IDs are revalidated before the announcement is created. |
| Membership or payment reminder | Not applicable | Allowed while membership is operational | Cancel queued delivery | Membership, user, gym, dates, and status are rechecked at delivery time. |
| Attendance inactivity reminder | Blocked without a gym | Allowed | Blocked | Global scans ignore independent profiles and use the current-access query. |
| Trial request communication | Allowed | Allowed | Allowed | This is an explicit pre-membership interaction with a gym. |
| Gym enrollment invitation | Allowed | Allowed for another gym | Allowed for re-enrollment | Approval is required before a profile or membership is created. |
| Independent trainer communication | Allowed | Allowed | Allowed | It remains gym-free and does not modify gym memberships or assignments. |
| Membership cancellation outcome | Allowed for the affected member | Allowed | Delivered once as terminal event | The cancellation result is sent in-app and by email even though access has just ended. |

## Audit findings

1. Gym-wide and branch announcements selected users through any matching member profile. They did not require `is_active`, an operational membership status, a valid date range, or current access.
2. Selected-member announcements accepted user IDs after actor scope checks. Gym owners and platform admins could therefore target an independent or former member with a gym-scoped notification.
3. The global attendance-inactivity scan included active independent profiles with no gym and scheduled a misleading gym reminder.
4. Scheduled reminders validated their state when created but not when delivered. A queued reminder could be sent after cancellation or profile deactivation.
5. Database notification payloads were inconsistent: many had IDs but no gym or branch display names or source classification.
6. Transactional emails used a minimal generic template ending with the application name, even when the gym, branch, member, category, and plan were available.
7. Trainer email invitations placed the signed approval URL in a plain text detail line instead of a dedicated, contextual invitation template.
8. Gym and platform announcement member selectors displayed records that were not eligible for gym communication.
9. A pending gym invitation was rendered both as an actionable invitation and as a generic notification in the member app. Accepting an invitation with a trainer then created another member-side trainer assignment notification, while re-sending a pending invitation could create more notification rows.

## Implemented phases

### Phase 1 — Recipient isolation

- Reused `GymMemberAccessService` as the single current-access definition.
- Applied that scope to gym-wide, offer, branch, selected-member, and trainer-assignment audiences.
- Added exact gym/branch consistency validation.
- Reject selected IDs that are independent, inactive, cancelled, expired, future-dated, or outside the selected branch.
- Filtered gym-panel and platform-panel member selectors to operational gym profiles.

### Phase 2 — Delivery-time reminder safety

- Excluded gym-less independent profiles from inactivity scans.
- Applied current-access rules before inactivity reminders are scheduled.
- Revalidated queued membership reminders against user, gym, status, start date, and expiry date immediately before delivery.
- Revalidated non-membership gym reminders against the current member profile immediately before delivery.
- Marked stale reminders `cancelled` without creating an in-app notification or sending email.

### Phase 3 — Context-rich payloads

- Added `source`, `gym_name`, and `branch_name` to scoped database notification data by default.
- Preserved explicit `source: independent` payloads so independent coaching remains distinguishable.
- Expanded reminder payloads with member, gym, branch, plan, membership status, due amount, and important dates.
- Made reminder titles and bodies identify the originating gym.

### Phase 4 — Premium email templates

- Rebuilt the shared transactional template as a responsive email card with:
  - recipient greeting;
  - gym or platform brand name;
  - branch context;
  - category label;
  - structured detail panel;
  - optional action button and support note;
  - clear gym-via-platform footer.
- Added automatic context resolution in `TransactionalEmailService`.
- Replaced generic app-name subjects for payment, pause, and resume messages with the actual gym name.
- Added a gym-branded cancellation email that explains gym access ends while separate independent coaching remains unchanged.
- Expanded member enrollment invitations with gym, branch, plan, assigned trainer, start date, approval expiry, and isolation guidance.
- Added a dedicated trainer enrollment mailable with gym, branch, specialization, approval expiry, and trainer-assignment implications.
- Expanded independent coaching invitations while clearly stating that gym membership and gym trainer assignment remain unchanged.

### Phase 5 — Regression coverage

- Gym announcements reach active members but not independent or former members.
- Selected-member gym announcements reject independent IDs.
- Queued payment reminders are cancelled when membership ends before delivery.
- Global inactivity scans do not create reminders for independent profiles.
- Rendered transactional email contains recipient, gym, branch, plan, and category context.
- Existing notification preferences and independent coaching invitation behavior remain covered.

### Phase 6 — Invitation notification lifecycle deduplication

- A pending gym invitation now creates or refreshes one member notification instead of inserting another record on every send.
- Accepting or rejecting transitions that same notification from `pending` to its final state and marks it read.
- Acceptance updates the notification with gym, branch, and assigned-trainer details.
- Trainer assignment through invitation acceptance suppresses only the redundant member-side assignment notification; the assigned trainer still receives the new-member notification.
- The member app hides a pending invitation notification from the generic Updates list while the same invitation is already displayed in its actionable invitation section.
- The same presentation deduplication is applied to pending independent-trainer invitations.

## Files and surfaces covered

- API and web gym announcement flows
- Platform admin announcement composer
- Scheduled reminder command and gym reminder controller
- Database/app notification payloads consumed by Flutter
- Payment receipt, membership pause/resume, trial, member enrollment, trainer enrollment, and independent coaching emails
- Email delivery audit records remain unchanged and continue recording gym, category, subject, status, and errors

## Deployment checklist

1. Run the focused communication and invitation tests.
2. Run the complete Laravel test suite.
3. Render representative emails in the configured staging mail provider.
4. Confirm `MAIL_MAILER` is a delivery transport in staging/production; `log` does not send real mail.
5. Deploy Laravel code and views; no migration is required for this phase.
6. Run `php artisan optimize:clear` after deployment so updated Blade templates are active.
7. Trigger one gym announcement, one payment receipt, and one invitation in staging.
8. Confirm database notification data contains `source`, `gym_name`, and `branch_name` and remains backward compatible with current apps.

## Future optional enhancements

- Add gym-configurable email accent colors, support email, reply-to address, and verified sending domain.
- Add deep links/action URLs for receipts, membership details, trial details, and notification screens.
- Add per-category email preferences separate from in-app notification preferences.
- Add queued mail delivery with retry/backoff and provider webhook reconciliation.
- Add preview/send-test controls to gym settings and the platform admin panel.
- Add locale-aware templates and currency/date formatting.
