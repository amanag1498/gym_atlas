# In-app guides: implementation and review notes

Implemented the local first release described in `in-app-user-guide-overlay-plan.md` for Gym Atlas and Gym Atlas Coach. No backend migration or endpoint is required.

## Shared behavior

Import `package:gym_flutter_core/guides.dart` (also exported by the core barrel).

- `GuideScope` sits above each app's Navigator and exists only for an authenticated account with required consent. Changing accounts or withdrawing required consent removes the current guide.
- `GuideAvailability` gates home targets until loading and onboarding finish. Store-preview screens do not launch guides.
- `GuideTarget` registers stable IDs. The controller waits for layout/route transitions, checks the current route, scrolls to mounted targets, and skips missing/offstage targets.
- The overlay blocks unrelated interactions. The guide surface now uses a premium dark navy treatment with a soft blue spotlight ring, segmented progress rail and prominent blue primary action. Skip and Next/Back/Finish remain outside the scrolling explanation. System Back skips. Text is announced through a live semantic region; reduced motion and accessible navigation suppress animations.
- Tooltips reserve system insets, keyboard space and 88 logical pixels for app navigation. When the target leaves too little room, the guide uses an uncut dim layer and a scrollable explanation with fixed controls.
- Completion/skip and last-shown metadata are stored through existing secure storage, with separate account/role and versioned guide keys. Normal logout does not delete these keys. No health, workout, photo or message values are stored in guide state.
- Storage failures suppress automatic launch and leave normal app use available. Settings reports preference-save errors.

## Integrated guides

Member: six-step overview; starting a workout and the Workout Book entry; Workout Books; member workout builder; active workout logging/completion; rest timer; Body Metrics/preferences; notifications; diet plans; chats/coaching; gym discovery and gym details; trial requests; logbook; assigned workout details; events; profile; membership; activity history; Settings/privacy.

Coach: six-step overview; workout builder exercise selection/groups/save; the actual member assignment sheet; member profile/sharing/assignment; Alerts and notification preferences; Diet Studio; Events; Follow Ups; Trial Leads; profile overview and edit/verification; Settings/privacy. The existing notification preference sheet is now reachable from Alerts.

Builder and assignment are separate guides because they are separate workflows in the current app. Tours explain controls without submitting forms, starting sessions, adopting plans, or assigning workouts.

Both Settings screens offer Replay all, overview, individual feature guides, and Turn guides off. Replay clears only the selected guide versions and enables guides. The dialog tells the user where to open the feature; it replays when that screen's controls are ready. Replay all arms each feature for its next visit rather than navigating through every feature immediately.

## Rollout and analytics

Guides are enabled by default. To disable the bundled guide feature for a build:

```sh
flutter build <target> --dart-define=ATLAS_GUIDES_ENABLED=false
```

The per-account Settings switch only affects automatic guides; normal Help, tooltips and app functions remain available.

`GuideScope.onEvent` is an optional analytics sink for `guide_started`, `guide_step_viewed`, `guide_completed`, `guide_skipped`, and `guide_replayed`. It receives only event name, bundled guide ID and optional step number. No analytics service is connected in this release, and no guide data is sent to a server. Remote configuration and completion/skip dashboards remain optional future work.

For meaningful copy/step changes, increment the affected definition's `_vN` ID and its registered target prefixes. Other guides retain their state.

## Validation

Latest local guide checks: targeted Flutter analysis passed for the shared engine and touched Member/Coach screens; focused guide tests passed for the shared engine and Coach guide smoke; `git diff --check` passed.

Focused widget tests cover persistence, account/role/version isolation, completion, skip, replay, auth/readiness/disabled gates, missing targets, offscreen scrolling, keyboard dismissal, interaction blocking, system Back, large text, reduced motion, semantics and storage failure. App tests validate the real overview targets and Settings replay lists. The Member Workout Book integration test launches the actual contextual guide after its data loads.

Existing Member home/settings/workout-builder and Coach home/settings accessibility tests are included in the focused regression run. Rendered normal and 320x480, 2x text-scale engine previews were visually inspected with the bundled Outfit font; navigation remains visible while the explanation can scroll.

The additional Member and Coach contextual guides are deliberately anchored to existing visible controls or sections. Steps that depend on optional data, such as invitations, saved gyms, gym details, meals, workout rows, personal records, events or trial leads, safely skip when that data is not present.

Before release, manually verify on physical Android and iOS devices:

- Fresh login after consent/onboarding; skip/complete, restart, logout and a second account.
- Replay each feature from Settings, including the Coach builder and assignment sheet.
- Real active workout, set logging, timer expiry, completion and summary with guides enabled; guides must not change workout data or timer state.
- TalkBack/VoiceOver focus and announcements, large text, landscape, keyboard and safe areas.

No device build, store submission, deployment or remote analytics activation is represented by the automated checks. Existing uncommitted auth/consent work in this checkout was preserved and is outside this guide implementation.
