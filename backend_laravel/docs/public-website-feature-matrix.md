# Atlas Public Website: Verified Product Feature Matrix

Last audited: 2026-08-01  
Scope: `backend_laravel`, `flutter_member_app`, `flutter_trainer_app`, and existing Play Store/App Store artwork in this checkout.

## Purpose

This document is the product-truth source for the Laravel public website rebuild. Website copy, screenshots, diagrams, calls to action, pricing claims, and structured data must not describe a capability as available unless it is supported by the current checkout and classified below as **Current**.

This is a source audit, not a production-environment certification. **Current** means the feature has an implemented route/controller and, where applicable, a corresponding app screen or repository integration in this checkout. Before launch, the implementation agent must still verify the feature against the deployed environment and recapture any outdated screenshots.

## Status definitions

| Status | Meaning | Website usage |
|---|---|---|
| **Current** | Implemented in the current codebase with a real user workflow. | May be described as available now, using precise wording from this matrix. |
| **Limited** | Implemented, but constrained by role, gym configuration, permissions, data availability, platform support, or an incomplete presentation. | May be described only with the stated qualification. |
| **Planned** | Explicitly presented as a future direction, but no complete current workflow was verified. | Keep in a clearly labelled roadmap/future section, never in the main feature promise. |
| **Unsupported** | No reliable current implementation was found, or the current UI uses a placeholder/static value. | Do not claim or visually imply the feature. |
| **Verify live** | Code exists, but a deployment, provider, price, store URL, or operational dependency must be checked before publishing the claim. | Use neutral copy or omit until live verification passes. |

## Evidence hierarchy

Use evidence in this order:

1. Laravel routes and their authenticated role/permission middleware.
2. Laravel controllers/services and request validation.
3. Flutter screens plus repository calls to the Laravel API.
4. Existing real-UI store screenshots.
5. Promotional store compositions, only as marketing artwork and never as proof of an exact interface.

Primary route sources:

- `backend_laravel/routes/web.php`
- `backend_laravel/routes/api.php`

Primary app sources:

- `flutter_member_app/lib/src/features/member/`
- `flutter_trainer_app/lib/src/features/trainer/`

## Product positioning that is safe now

Atlas is a connected gym ecosystem with four verified surfaces:

1. A public gym discovery website and authenticated Member App.
2. A Trainer App for gym-connected coaching workflows.
3. A Laravel Gym Management panel for operational administration.
4. A Laravel Platform Admin panel for governance, catalogues, subscriptions, reporting, and auditing.

Safe top-level promise:

> Discover gyms, manage memberships and operations, deliver connected coaching, and follow member progress in one Atlas ecosystem.

Do not use “all-in-one fitness marketplace,” “online coaching platform,” “book any class,” “pay in app,” or “verified member reviews” until the unsupported boundaries later in this document are implemented and re-audited.

---

## Public discovery website

| Capability | Status | Verified behavior | Safe website wording | Evidence |
|---|---|---|---|---|
| Public homepage | **Current** | Public landing route loads discovery data through `GymDiscoveryService`. | “Explore the Atlas gym network.” | `routes/web.php` `public.home` |
| Search and browse public gyms | **Current** | Public gym index supports searchable/filterable discovery. | “Find gyms by name, locality, facilities, and available plan information.” | `routes/web.php` `public.gyms.index`; public discovery API routes |
| Nearby gym discovery | **Current** | Nearby endpoint and Member App location flow support distance-based discovery. | “Use your location to explore nearby gyms.” | `GET api/public/discovery/gyms/nearby`; `member_gym_discovery_screen.dart` |
| City-based discovery | **Current** | Public API exposes gyms by city. | “Browse gyms across available Atlas cities.” | `GET api/public/discovery/cities/{city}/gyms` |
| Gym profile pages | **Current** | Public profiles expose configured gym details such as gallery, facilities, visible trainers, branches, and plans. | “Review each gym’s published profile before you enquire.” | `public.gyms.show`; `GymDiscoveryService`; Member discovery detail screen |
| Verified/featured/promoted listing states | **Current** | Platform Admin can verify, feature, promote, hide, or show listings. | “Look for Atlas verification and featured listing indicators.” | Admin gym/listing routes |
| Gym-controlled pricing visibility | **Current** | A gym can control whether its membership pricing is displayed publicly. | “Plan pricing is shown only when the gym chooses to publish it.” | `gym.public-listing` routes; discovery UI `pricing_visible` handling |
| Public trial request | **Current** | Visitors and authenticated members can submit trial requests; gym staff can review, assign, accept/reject, complete, and convert them. | “Request a trial and let the gym guide the next step.” | `public.gyms.trial-request`; public/member trial APIs; gym trial routes |
| Saved/favourite gyms | **Current** | Authenticated members can add and remove favourite gyms. | “Save gyms and return to them later in the Member App.” | Member favourite-gym API routes; Member repository |
| Contact, gym onboarding, and trainer access enquiries | **Current** | Public forms submit to the contact/enquiry workflow; Platform Admin can review and update enquiry status. | “Contact Atlas, onboard a gym, or request trainer access.” | Public contact routes; `Admin\EnquiryController` |
| Public trainer profiles in listings | **Limited** | Trainer cards render only when a gym has published visible trainer data; empty state says profiles are coming soon for that listing. | “See participating trainers when their gym has published their profiles.” | Member discovery detail screen; public gym detail payload |
| Maps | **Limited** | Coordinates/current location and external map opening exist, but the in-app detail page contains a map preview placeholder. | “Open a gym’s location in your maps app.” Do not claim an interactive embedded map. | `member_gym_discovery_screen.dart`; placeholder in `member_home_screen.dart` |
| Reviews and ratings | **Unsupported** | No verified review submission/moderation workflow was found; one Member UI card uses a static `4.8 rating`, and the detail UI labels reviews as a placeholder. | Do not show ratings, review counts, testimonials presented as member reviews, or “top rated” sorting. | Static rating and “Reviews coming soon” in `member_home_screen.dart` |
| Instant trial confirmation | **Unsupported** | Trial requests enter a gym-managed status workflow rather than an automatic confirmed booking. | Use “Request a trial,” not “Book instantly” or “Confirmed instantly.” | Trial request routes and status actions |

---

## Member App

### Discovery, identity, and membership

| Capability | Status | Verified behavior | Safe website wording | Evidence |
|---|---|---|---|---|
| Firebase/Google authentication and active role | **Current** | Public auth APIs support Firebase and Google login plus active-role selection. | “Sign in securely and continue in your Atlas member role.” | `api/public/auth/*`; Member auth implementation |
| Member onboarding and profile | **Current** | Members can complete/update personal and fitness profile information and upload a profile photo. | “Build a member profile that keeps fitness context together.” | Member profile API routes; `member_onboarding_flow.dart`; `member_profile_screen.dart` |
| Gym invitations | **Current** | Existing app users can accept or reject gym invitations in app. | “Review and respond to gym invitations from the Member App.” | `api/member/gym-invitations/*`; Member notifications screen |
| Email invitation path | **Limited** | Signed email review/respond routes exist for people not yet using the app; actual delivery depends on production mail configuration. | “Gyms can invite new members by email.” Add only after live email-delivery verification. | Signed `member-email-invitations` web routes |
| Current membership details | **Current** | Member screen shows status, amount paid, expiry, branch, plan, custom/joining/PT fees, due amount/date, and payment status when data exists. | “See your active plan, access status, fees, due information, and expiry in one place.” | `api/member/membership`; `member_membership_screen.dart` |
| Leave current gym | **Current** | Member can initiate leaving the current gym with confirmation. | “Manage your current gym connection from your profile.” | `POST api/member/membership/leave`; Member profile screen |
| Attendance history/status | **Current** | Member can see visit history, latest check-ins, and whether checked in today. | “Review attendance and recent gym visits.” | Member attendance APIs; Member membership/attendance screen |
| Biometric attendance profile | **Limited** | Biometric profile API exists; actual scan/check-in operation is gym-side and depends on configured hardware/workflow. | “Connected gyms can use biometric-assisted attendance.” Never imply every gym supports it. | Member biometric-profile API; gym biometric-scan route |
| Member payment visibility | **Current** | Membership view presents recorded paid/due information. | “See payment status and dues recorded by your gym.” | Membership API and screen |
| Online payment by member | **Unsupported** | Gym/admin payment recording, ledger, invoice, and reversal workflows exist, but no member checkout/payment route was verified. | Do not say “Pay membership fees in the app,” “autopay,” or “secure online checkout.” | Gym payment routes; absence of member payment mutation route |

### Training and progress

| Capability | Status | Verified behavior | Safe website wording | Evidence |
|---|---|---|---|---|
| Trainer-assigned workout plans | **Current** | Members can view assigned workout plans, days, exercises, sets/reps/weights, and start the assigned workout. | “Follow workouts assigned by your trainer.” | Member workout APIs; `member_assigned_workout_screen.dart` |
| Personal workout plans | **Current** | Members can create, view, update, delete, and duplicate personal plans. | “Build and reuse personal workout plans.” | Member workout-plan API routes; Member repository |
| Workout book/library | **Current** | Members can browse available and recommended workout books and adopt a template. | “Start from the Atlas workout library or a recommended plan.” | Member workout-book APIs; `member_workout_book_screen.dart` |
| Guided workout logging | **Current** | Members can start a session, add exercise performance, duplicate recent sets in UI, and complete the session. | “Log exercises, sets, reps, weight, volume, and completed sessions.” | Workout-session APIs; Member training UI |
| Workout history and logbook | **Current** | History, summaries, exercise history, volume, and personal-record views are implemented. | “Review workout history, volume, exercise trends, and personal records.” | Member history/logbook APIs; `member_logbook_screen.dart` |
| Weight tracking | **Current** | Members can view and add weight logs. | “Track weight changes over time.” | Member progress weight-log APIs |
| Body measurements | **Current** | Members can view and add body measurements. | “Record body measurements as progress changes.” | Member body-measurement APIs |
| Progress photos | **Current** | Members can view and upload progress photos. | “Keep progress photos alongside your fitness history.” | Member progress-photo APIs; photo upload flow |
| Progress summary | **Current** | App loads an aggregated progress summary. | “See a connected view of training and body progress.” | `GET api/member/progress/summary`; `member_progress_screen.dart` |
| Step tracking | **Limited** | Today/summary/sync APIs and Android health/sensor integration are present. Availability and accuracy depend on device permissions and health-service support. | “Connect supported Android step data and review daily trends.” Do not promise iOS HealthKit parity without separate verification. | Step APIs; `step_health_service.dart`; step dashboard widget |

### Diet, coaching, and communication

| Capability | Status | Verified behavior | Safe website wording | Evidence |
|---|---|---|---|---|
| Trainer/gym diet plans | **Current** | Members can view assigned plans and their meals. | “Follow meal-based diet plans shared through Atlas.” | Member diet APIs; `member_diet_plan_screen.dart` |
| Member-created diet plans | **Current** | Members can create, edit, and delete personal diet plans. | “Create a personal meal plan with the meal names that fit your routine.” | Member diet-plan mutation routes and screen |
| Diet templates | **Current** | Members can browse and adopt templates. | “Start from a diet template, then make it your own.” | Member diet-template routes |
| Meal completion logging | **Current** | Members can update an individual meal log/status. | “Mark meals as you follow the plan.” | `POST api/member/diet-plans/{dietPlan}/meals/{meal}/log` |
| Assigned trainer profile | **Current** | Member can view the currently assigned trainer and coaching context. | “Keep your assigned trainer and plan context connected.” | `GET api/member/trainer`; `member_assigned_trainer_screen.dart` |
| Member–trainer chat | **Current** | Persistent chat supports conversation/message retrieval, REST and socket sending, read receipts, pagination, and realtime updates. | “Message your assigned trainer with realtime updates and read status.” | Chat API routes; realtime context; Member/Trainer chat screens |
| Chat safety | **Current** | Terms acceptance, reporting, block, and unblock controls are implemented. | “Use built-in conversation safety controls, including report and block.” | `api/chat/safety/*`; Member/Trainer chat screens |
| Notifications | **Current** | Notification list, read/unread, mark-all-read, gym invitations, and app navigation are implemented. | “Receive gym, coaching, membership, and activity updates in one inbox.” | Public notification APIs; Member notifications screen |
| Notification preferences | **Current** | Members can load and update category-level preferences. | “Choose which optional reminders and updates you receive.” | Public notification-preference APIs; preference sheet |
| Live video/voice coaching | **Unsupported** | No complete video/voice coaching or session route/screen was verified. | Do not claim video coaching, virtual PT sessions, live classes, or calls. | No supporting Member/Trainer route or screen |
| AI-generated workouts/diets | **Unsupported** | No verified AI planning workflow exists. | Do not use “AI coach,” “AI workout,” or “AI meal plan.” | No supporting route/controller/screen |

---

## Trainer App

| Capability | Status | Verified behavior | Safe website wording | Evidence |
|---|---|---|---|---|
| Gym-connected trainer context | **Current** | Trainer context includes assigned gym/branch and role-scoped data. | “Work inside the gym and branch context assigned to you.” | `GET api/trainer/context`; Trainer home screen |
| Trainer profile | **Current** | Trainer can view/update profile and upload photo/certification files. | “Maintain your coaching profile, experience, languages, and certifications.” | Trainer profile routes and screen |
| Trainer gym invitations | **Current** | Authenticated trainers can respond to gym invitations. | “Review and respond to trainer invitations.” | `api/trainer-invitations/{invitation}/respond`; Trainer alerts UI |
| Email trainer invitation | **Limited** | Signed email review/respond workflow exists; delivery must be verified in production. | “Invite trainers by email” only after live mail verification. | Signed `trainer-email-invitations` routes |
| Assigned member roster | **Current** | Trainer can list assigned members and open member detail. | “See the members currently assigned to you.” | Trainer assigned-member routes; Trainer clients UI |
| Member fitness context | **Current** | Trainer detail exposes member progress, attendance, workout plans, and workout logbook. | “Review attendance, progress, plans, and workout history before the next follow-up.” | Assigned-member detail APIs |
| Today’s client queue | **Current** | Dedicated task endpoint and dashboard surface today’s clients. | “Start each day with a focused client queue.” | `api/trainer/today-clients`; Trainer dashboard |
| Follow-up notes/tasks | **Current** | Trainer can add/update notes, mark notes complete, and review pending follow-ups/task summaries. | “Capture notes and keep pending follow-ups visible.” | Trainer notes/task APIs; tasks screen |
| Workout template library | **Current** | Trainer can create/update templates, preview them, and assign them to a member. | “Build reusable workout templates and assign them to clients.” | Trainer workout-template routes; workout builder UI |
| Member workout plan management | **Current** | Trainer can create, view, update, delete, and assign member workout plans. | “Create and manage structured member workout plans.” | Trainer workout-plan routes; Trainer workout builder |
| Exercise library contribution | **Current** | Trainer can search exercises and add trainer-created exercises. | “Use the exercise library and add coaching-specific exercises.” | Trainer exercise APIs; builder UI |
| Diet plan builder | **Current** | Permission-gated create/view/update/delete of diet plans is implemented. | “Create meal-based diet plans when your gym grants diet-management permission.” | Trainer diet-plan routes with permission middleware |
| Diet templates | **Current** | Permission-gated template create/update/delete and member assignment are implemented. | “Reuse diet templates across eligible members.” | Trainer diet-template routes |
| Trial lead follow-up | **Current** | Trainers can see assigned trial requests and update their status. | “Follow up on trial leads assigned by the gym.” | Trainer trial-request APIs; Trainer dashboard/alerts UI |
| Member invitations | **Current** | Trainer can initiate a member invitation workflow. | “Invite a prospective member into the gym’s approval workflow.” | `POST api/trainer/member-invitations`; invitation sheet |
| Trainer announcements | **Current** | Trainer can create announcements through the trainer API/UI. | “Share announcements with the intended gym audience.” | `POST api/trainer/announcements`; alert/announcement UI |
| Member messaging | **Current** | Trainer inbox/thread supports realtime/persistent chat, read state, history, and safety controls. | “Keep assigned-member conversations inside the coaching workspace.” | Shared chat APIs and Trainer chat implementation |
| Notifications and alerts | **Current** | Trainer alerts surface notifications, invitations, trial work, and announcements. | “Review coaching alerts and updates in one place.” | Trainer/public notification APIs; Trainer alerts UI |
| Independent trainer marketplace | **Unsupported** | Trainer workflows are gym-connected; no public trainer marketplace or direct consumer purchase flow was verified. | Do not claim “find and hire any trainer” or independent marketplace access. | Trainer role/context and invitation flows |
| Paid online coaching | **Planned** | Existing public page explicitly calls online coaching a future capability; no complete current workflow was verified. | If retained, label “Future direction: online coaching.” Prefer omitting from launch pages. | `public/pages/for-trainers.blade.php`; absence of session/payment implementation |

---

## Gym Management panel

| Capability | Status | Verified behavior | Safe website wording | Evidence |
|---|---|---|---|---|
| Operational dashboard | **Current** | Authenticated gym panel has a dedicated dashboard. | “Run daily gym operations from one dashboard.” | `web.gym.dashboard`; Gym DashboardController |
| Multi-branch management | **Current** | Create, view, edit, activate/deactivate, and delete branches. | “Organize multiple branches and their local operations.” | Gym branch routes/controller |
| Member management | **Current** | Search, create/invite, view, update, activate/deactivate, remove, import preview/import, and export workflows exist. | “Manage member records, onboarding, status, and bulk import/export.” | Gym member routes/controller |
| Trainer management | **Current** | Search/create, view/update, activate/deactivate, and assign members. | “Manage trainers and assign members to the right coach.” | Gym trainer routes/controller |
| Staff management | **Current** | Search/create, view/update, activate/deactivate, and remove staff. | “Control staff access and active status.” | Gym staff routes/controller |
| Role and permission scoping | **Current** | Gym and trainer APIs/panels use role, active-role, panel-access, and feature permission middleware. | “Keep actions scoped to the user’s role and granted permissions.” | Route middleware in `web.php` and `api.php` |
| Membership plans | **Current** | Create, view, edit, activate, and deactivate plans. | “Create membership plans and control when they are available.” | Gym membership-plan routes |
| Member membership lifecycle | **Current** | Assign, renew, freeze, reactivate, extend, and cancel memberships. | “Manage the full membership lifecycle, including renewals, pauses, extensions, and cancellations.” | Gym membership routes/controller |
| Custom member fees and audit history | **Current** | Set member/membership custom fees and review audit logs. | “Apply approved member-specific fees with an audit trail.” | Gym custom-fee routes; API custom-fee audits |
| Payments, dues, invoices, and ledger | **Current** | Record payments, mark paid/unpaid, create/reverse ledger entries, reverse payments, and view invoices/dues. | “Record collections, track dues, produce invoices, and keep reversible ledger history.” | Gym payment/dues routes and controller |
| Payment gateway collection | **Unsupported** | The verified flow is operational recording/accounting; no member-facing gateway checkout was found. | Say “record and track payments,” not “collect online payments.” | No member checkout route |
| Attendance | **Current** | Today/history views, manual attendance, biometric scan endpoint, exports, and correction approval/rejection exist. | “Track attendance with manual and supported biometric workflows, including correction review.” | Gym attendance routes/controller |
| Trainer assignment | **Current** | Gym staff can assign trainers to members and members to trainers. | “Connect each member with the appropriate trainer.” | Member/trainer assignment routes |
| Diet plans | **Current** | Gym panel can create/update plans and manage plan status. | “Create and oversee member diet plans.” | Gym diet-plan routes/controller |
| Trial lead pipeline | **Current** | View/export trials; update, accept, reject, complete, convert, and assign trainer. | “Turn public trial interest into an owned follow-up and conversion workflow.” | Gym trial-request routes/controller |
| Leads | **Current** | Dedicated leads view/report exists. | “Keep lead activity visible alongside trial conversion.” | `web.gym.leads`; Gym reports leads route |
| Announcements | **Current** | Create, view, list, and delete gym announcements. | “Send gym announcements through the connected Atlas experience.” | Gym announcement routes/controller |
| Scheduled reminders | **Current** | View scheduled reminders and manually run due reminders. | “Review scheduled reminders and due communication work.” | Gym reminder routes/controller |
| Reports and CSV export | **Current** | Revenue, payments, dues, memberships, attendance, trainers, custom fees, and leads reports/exports exist. | “Analyze revenue, dues, memberships, attendance, trainers, custom fees, and leads.” | Gym report routes/controller |
| Gym profile and public listing control | **Current** | Gym can update its profile and configure public listing visibility/content. | “Control how your gym appears in Atlas discovery.” | Gym profile/public-listing routes |
| Audit logs | **Current** | Gym-scoped audit log index exists. | “Review a traceable history of important operational actions.” | `web.gym.audit-logs` |
| Settings and notification centre | **Current** | Gym settings and notification pages exist. | “Manage gym settings and operational notifications.” | Gym settings/notification routes |
| Class timetable and class booking | **Unsupported** | No complete class catalogue, schedule, capacity, or member booking workflow was verified. | Do not claim classes, timetable booking, waitlists, or class check-in. | No supporting route/controller/app screen |
| Payroll/commissions | **Unsupported** | No trainer payroll or commission settlement workflow was verified. | Do not claim payroll automation or commission payouts. | No supporting route/controller |
| Inventory/POS | **Unsupported** | No retail inventory, product sale, or POS workflow was verified. | Do not claim inventory or POS. | No supporting route/controller |

---

## Platform Admin panel

| Capability | Status | Verified behavior | Safe website wording | Evidence |
|---|---|---|---|---|
| Platform dashboard | **Current** | Role-protected Platform Admin dashboard exists. | “See platform operations from a central admin workspace.” | `web.admin.dashboard` |
| Gym onboarding and governance | **Current** | Create/edit gyms; approve, reject, verify, activate/deactivate, feature, promote, hide/show listings. | “Review gym onboarding and govern listing status, verification, and visibility.” | Admin gym routes/controller |
| Gym owner management | **Current** | Create/view/edit owners, manage active status, inspect activity, and open owner/gym dashboard context. | “Manage gym owners and inspect their operational context.” | Admin gym-owner routes/controller |
| User governance | **Current** | Browse users/members/trainers, inspect profiles/activity, and change active status. | “Review platform users and manage account status.” | Admin user routes/controller |
| Catalogue management | **Current** | Manage exercises, facilities, fitness goals, trainer specializations, banners, and cities. | “Maintain the shared catalogues that power app and gym workflows.” | Admin catalogue routes/controller |
| Workout book management | **Current** | Create, edit, and delete reusable workout books. | “Curate reusable workout content for the ecosystem.” | Admin workout-book routes/controller |
| Diet plan oversight and templates | **Current** | Review plan status and manage diet templates. | “Oversee diet content and maintain reusable templates.” | Admin diet-plan/template routes |
| Public listing governance | **Current** | Dedicated listings, featured gyms, and promoted gyms views exist. | “Control which gyms appear publicly and how promoted visibility is applied.” | Admin listing routes |
| Platform subscription plans | **Current** | Create and update platform subscription plan definitions. | “Configure the subscription plans offered to gyms.” | Admin platform-subscription-plan routes |
| Gym platform subscriptions and billing ledger | **Current** | Assign/edit/renew subscriptions, review ledgers, and mark invoices paid. | “Manage gym subscriptions, renewals, invoices, and billing history.” | Admin gym-platform-subscription routes/controller |
| Final public pricing | **Verify live** | Subscription models exist, while the public Pricing page describes launch/free-onboarding positioning and future premium lanes. Exact commercial terms are not established by source alone. | Publish only approved, current commercial terms supplied by the business owner. | Admin subscription routes; public pricing page |
| Platform reports and export | **Current** | Gym, user, payment, attendance, custom-fee, and platform-billing reports with export route exist. | “Review cross-platform operational and billing reports.” | Admin report routes/controller |
| Audit logs and activity history | **Current** | Platform audit log and per-owner/user activity views exist. | “Investigate important changes with platform-wide activity and audit history.” | Admin audit/activity routes |
| Enquiry management | **Current** | Admin can list public enquiries and update their status. | “Route and track website enquiries from the admin panel.” | Admin enquiry routes/controller |
| Platform announcements | **Current** | Admin can create and view announcements. | “Publish platform announcements to the intended audiences.” | Admin announcement routes/controller |
| Platform settings | **Current** | Admin settings edit/update workflow exists. | “Manage centrally controlled platform settings.” | Admin setting routes/controller |
| Admin impersonation/context preview | **Current** | Platform Admin can open a gym owner/gym dashboard context and stop impersonation. | Internal/admin documentation may say “Inspect a gym dashboard in controlled admin context.” Avoid highlighting impersonation in consumer marketing. | Admin gym-owner dashboard and stop-impersonation routes |
| Automated fraud detection | **Unsupported** | No verified fraud scoring/detection workflow was found. | Do not claim AI fraud monitoring or automated risk scoring. | No supporting controller/workflow |
| Full accounting/tax compliance suite | **Unsupported** | Ledgers/invoices exist, but no full bookkeeping, GST filing, or tax-compliance workflow was verified. | Describe operational billing records only; do not claim accounting-software replacement. | Billing/payment routes only |

---

## Cross-product workflows safe to explain on the website

### 1. Discover to trial to membership

1. A visitor or member searches public gym listings.
2. They review gym-published facilities, branches, trainers, gallery, and visible plan information.
3. They submit a trial request.
4. The gym reviews it, assigns a trainer if appropriate, and updates its status.
5. The gym can complete the trial and convert the lead into a member workflow.
6. Membership, trainer, attendance, fees, and plan context become visible in the connected surfaces.

Qualification: a trial request is not an instantly confirmed booking.

### 2. Gym-to-member onboarding

1. Gym staff finds an eligible Atlas user or initiates an invitation.
2. Existing Member App users review the invitation in app.
3. Email-based review exists for users outside the app, subject to live mail delivery.
4. The member accepts or rejects before the gym relationship is established where the invitation path is used.

Qualification: do not imply every staff/trainer creation path is approval-only without a focused re-verification of the exact onboarding entry point.

### 3. Connected coaching

1. A gym assigns a member to a trainer.
2. The trainer reviews member context and creates or assigns a workout/diet plan.
3. The member follows and logs training/meals/progress.
4. The trainer reviews history, progress, attendance, and notes.
5. Member and trainer communicate in the shared chat with safety controls.
6. Follow-up notes and tasks keep next actions visible.

Qualification: this is asynchronous/realtime messaging and plan-based coaching, not live video coaching.

### 4. Membership operations

1. The gym creates membership plans and assigns a plan to a member.
2. Staff manages renewal, freeze/reactivation, extension, cancellation, custom fees, and trainer assignment.
3. Staff records payments and ledger adjustments, reviews dues, and generates invoice views.
4. The member sees the membership, recorded payment, due, and expiry information in the app.
5. Reports and audit trails support operational review.

Qualification: payment recording is verified; member self-checkout through a payment gateway is not.

### 5. Platform governance

1. Platform Admin reviews gym onboarding.
2. Admin controls approval, verification, activation, and public listing visibility.
3. Shared catalogues and reusable workout/diet content are maintained centrally.
4. Gym subscription, invoice, ledger, enquiry, announcement, and report workflows are reviewed centrally.
5. Audit and activity views provide traceability.

---

## Visual asset mapping for the public website

### Brand assets

| Asset | Intended website use | Rule |
|---|---|---|
| `play_store_assets/branding/atlas-master-icon.png` | Navbar/footer brand mark, ecosystem diagrams, social image source | Produce web-sized derivatives; preserve clear space and aspect ratio. |
| `play_store_assets/branding/atlas-play-store-icon.png` | Download/app CTA | Use only beside a real, verified store destination. Do not imply availability on an unverified store. |

### Member assets

| Asset group | Best website placement | Claim represented |
|---|---|---|
| `play_store_assets/member/screenshots-real-ui/01-dashboard.png` | Homepage hero or Member overview | Connected member dashboard |
| `play_store_assets/member/screenshots-real-ui/02-activity.png` | Attendance/progress section | Recorded member activity |
| `play_store_assets/member/screenshots-real-ui/03-workouts.png` | Workout feature section | Assigned/personal workouts |
| `play_store_assets/member/screenshots-real-ui/04-workout-history.png` | Logbook section | Workout history and progress |
| `play_store_assets/member/screenshots/01-fitness-connected.png` through `04-coaching-close.png` | Marketing story panels | Promotional compositions; verify all captions against this matrix |
| `play_store_assets/member/feature-graphic-1024x500.png` | Member page banner or download CTA | Promotional Member App overview |
| `app_store_assets/member/screenshots-6.5/` and `screenshots-6.9/` | High-resolution phone mockups | Use the viewport size matching the web composition |

### Trainer assets

| Asset group | Best website placement | Claim represented |
|---|---|---|
| `play_store_assets/trainer/screenshots-real-ui/01-dashboard.png` | Trainer page hero/dashboard | Daily coaching workspace |
| `play_store_assets/trainer/screenshots-real-ui/02-clients.png` | Assigned-member section | Client roster/context |
| `play_store_assets/trainer/screenshots-real-ui/03-workout-builder.png` | Plan builder section | Workout creation and assignment |
| `play_store_assets/trainer/screenshots-real-ui/04-notifications.png` | Alerts/follow-up section | Trainer notifications and task context |
| `play_store_assets/trainer/screenshots/01-coach-with-clarity.png` through `04-stay-connected.png` | Marketing story panels | Promotional compositions; verify all captions against this matrix |
| `play_store_assets/trainer/feature-graphic-1024x500.png` | Trainer page banner or download CTA | Promotional Trainer App overview |
| `app_store_assets/trainer/screenshots-6.5/` and `screenshots-6.9/` | High-resolution phone mockups | Use the viewport size matching the web composition |

### Admin images still required

No equivalent verified Gym Admin or Platform Admin screenshot set was found in the audited store-asset folders. Capture current authenticated screens after seeding or selecting safe demonstration data:

1. Gym dashboard.
2. Members and membership detail.
3. Attendance operations.
4. Payments/dues or reports.
5. Trainer assignment.
6. Trial lead pipeline.
7. Platform Admin dashboard.
8. Gym approval/listing governance.
9. Platform reports.
10. Platform subscription ledger or audit log.

Use synthetic or explicitly approved demonstration data. Blur or replace personal names, phone numbers, email addresses, financial identifiers, uploaded photos, and any production-only details.

### Image truth rules

- Real UI screenshots are product proof.
- Promotional store screenshots are editorial artwork and must not be presented as pixel-accurate current UI.
- Generated imagery may provide fitness/lifestyle atmosphere, backgrounds, abstract ecosystem graphics, and section transitions.
- Generated imagery must not invent app screens, admin analytics, customer metrics, gym partnerships, ratings, or testimonials.
- Never use the static `4.8 rating` shown in the current Member UI as a public product claim.
- Every screenshot caption must name what the viewer can actually do, not use vague claims such as “transform everything.”
- Produce responsive AVIF/WebP derivatives, retain original PNGs as sources, and supply useful alternative text.

---

## Claims requiring live verification before publication

| Claim | Why source audit is insufficient | Required verification |
|---|---|---|
| Android/Play Store availability | Assets exist, but store approval/publication can change. | Open the live Play Store listing and verify package, status, country availability, and URL. |
| iOS/App Store availability | Screenshots/build assets exist, but they do not prove public listing status. | Open the live App Store listing and verify bundle/app/version and URL. |
| “Realtime” chat availability | Source supports Socket.IO plus REST fallback, but production socket health is operational state. | Verify public realtime health/readiness and send/read receipt on current builds. |
| Email invitations | Signed routes exist, but delivery depends on configured mail transport. | Send a test invitation through production mail and complete the signed response flow. |
| Push notifications | FCM-token and preference APIs exist, but provider credentials and delivery are deployment concerns. | Verify FCM delivery on current Member and Trainer builds. |
| Biometric attendance | Code path exists, but hardware/configuration varies by gym. | Verify with the supported scanner/integration and state “at participating gyms.” |
| Step tracking | Device/platform permissions and health services vary. | Verify on supported Android versions/devices; do not claim unsupported iOS parity. |
| Exact prices/free period | Public copy and subscription configuration can drift. | Obtain written commercial approval and compare with live plan configuration. |
| Customer/gym counts, revenue, retention, or performance improvements | No marketing-grade metric evidence was audited. | Provide dated, attributable analytics and approval before publishing. |

---

## Unsupported or risky marketing claims

The following must not appear as current capabilities unless a later implementation changes the classification and this matrix is updated:

- Live video coaching, voice calls, virtual PT sessions, or streamed classes.
- Independent trainer marketplace or direct trainer hiring.
- Member self-checkout, payment gateway, subscriptions paid in app, or autopay.
- Class schedules, seat booking, waitlists, or group-class attendance booking.
- Public gym reviews, verified member ratings, rating averages, or “top-rated gym” claims.
- AI coach, AI-generated workouts, AI diet plans, automated form analysis, or predictive health advice.
- Nutritionist/doctor consultation or medical recommendations.
- Wearable support beyond the specifically verified Android step workflow.
- Full CRM automation, automated sales funnels, or WhatsApp automation.
- Payroll, trainer commissions, inventory, POS, GST filing, or full accounting replacement.
- Franchise management beyond the verified multi-branch structure.
- Guaranteed outcomes such as weight loss, revenue growth, retention improvement, or lead conversion.
- Any customer count, city count, gym count, download count, star rating, testimonial, or partner logo without current evidence and consent.
- “Free forever,” a specific launch price, or a paid-tier price without business approval and live configuration verification.

---

## Page-level content allocation

| Website page | Current capabilities to explain | Required proof visual | Primary CTA |
|---|---|---|---|
| Home | Four connected surfaces; discovery-to-membership; coaching loop; operational oversight | Member + Trainer real-UI montage and a real admin dashboard crop | Find Gyms / Onboard Your Gym |
| Product overview | The five cross-product workflows in this document | Ecosystem workflow diagram using real UI thumbnails | Explore by Role |
| Member App | Discovery, favourites, trials, membership, attendance, workouts, logbook, diet, progress, trainer, chat, notifications | Existing Member real-UI and high-resolution store screenshots | Get the Member App / Find Gyms |
| Trainer App | Roster, today queue, member context, notes/tasks, workout/diet builders, trial leads, chat, notifications | Existing Trainer real-UI and high-resolution store screenshots | Request Trainer Access |
| Gym Management | Branches, people, memberships, attendance, billing records, trials, announcements, reports, listing control, audit logs | Newly captured Gym Admin screens | Onboard Your Gym |
| Platform Administration | Governance, catalogues, subscriptions, reports, enquiries, announcements, audit logs | Newly captured Platform Admin screens | Contact Atlas |
| How Atlas Works | Discovery-to-trial, onboarding, coaching, membership operations, governance | Annotated end-to-end flow | Choose Your Atlas Role |
| Find Gyms | Search, filters, nearby discovery, published profile data, trial request | Real gym cards/profile imagery | View Gym / Request Trial |
| Gym Detail | Gallery, facilities, visible trainers, branches/plans, contact/trial | Gym-owned published media | Request Trial |
| Pricing | Only approved current commercial terms | No speculative price artwork | Talk to Atlas |

## Copy acceptance checklist

Before merging any public website page:

- [ ] Every product claim maps to a **Current** or correctly qualified **Limited** row.
- [ ] Any planned capability is explicitly labelled “planned” or “future,” not mixed into current features.
- [ ] No online-payment language is used for payment-recording workflows.
- [ ] No instant-booking language is used for trial requests.
- [ ] No public rating/review UI uses the static `4.8` placeholder.
- [ ] Trainer diet features mention gym permission where appropriate.
- [ ] Biometric and step features mention participation/device support.
- [ ] Store buttons use verified live URLs and current availability.
- [ ] Admin screenshots contain no real personal or sensitive data.
- [ ] Promotional artwork is not presented as an exact screenshot.
- [ ] Exact prices and quantitative claims have dated business evidence.
- [ ] The same feature name and status are used consistently across desktop and mobile versions.

## Re-audit triggers

Update this matrix before changing website claims when any of the following occurs:

- A new Laravel route, controller, permission, payment gateway, booking system, or marketplace flow ships.
- Member or Trainer navigation/screens change materially.
- A Play Store or App Store listing becomes public, changes URL, or is withdrawn.
- Realtime, mail, FCM, biometric, or health-platform support changes.
- Business pricing or subscription tiers are approved.
- Ratings/reviews, live coaching, classes, payroll, POS, or AI functionality is implemented.

