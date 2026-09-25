# In-App User Guide Overlay Plan

## Objective

Build a reusable, first-time user guide for the Gym Atlas Member and Gym Atlas Coach apps. The guide should highlight important controls with a spotlight overlay, explain the action in plain language, and let users continue, go back, skip, finish, or replay the guide later.

The experience must educate users without blocking them from using the app or showing the same tour repeatedly.

## Product and UX direction

- Use the existing Gym Atlas visual language and blue accent palette.
- Keep the first-login tour short: approximately 6–8 steps.
- Use contextual guides for complex areas instead of putting every feature into one tour.
- Show user-facing language, not widget names, API terms, or implementation details.
- Show the guide only after the destination screen is loaded and its target control exists.
- Respect safe areas, bottom navigation, keyboard state, screen size, accessibility settings, and reduced motion.
- Always provide `Skip`, and provide `Replay guides` from Settings.

## Shared architecture

Implement the guide engine in `gym_flutter_core` so both apps use the same behavior and visual system.

Suggested components:

- `GuideOverlay`: renders the modal dim layer, spotlight, tooltip, and navigation controls.
- `GuideStep`: describes a target, title, explanation, placement, and optional action.
- `GuideController`: controls start, next, previous, skip, completion, and replay.
- `GuideTarget`: gives important widgets stable keys or registered target IDs.
- `GuideStateStore`: persists completion and skip state locally.

Each guide should have a versioned identifier:

```text
member_home_v1
member_workouts_v1
member_active_workout_v1
trainer_home_v1
trainer_builder_v1
```

## Persisted state

Store the following locally for the signed-in account:

- Guide ID
- Guide version
- Completed or skipped state
- Last shown timestamp
- Whether guides have been disabled

The state must be account-specific so one user’s completion does not suppress the guide for another account on the same device.

When a guide receives meaningful new steps, increment only that guide’s version. Do not reset every guide after every app update.

## Member app guides

### First-login guide

Recommended steps:

1. Home dashboard
2. Train tab
3. Workout Books
4. Starting a workout
5. Logging an exercise
6. Rest timer
7. Workout summary
8. Settings and privacy controls

### Contextual guides

- Body Metrics: measurements, steps, progress, and privacy controls.
- Active Workout: check-in, exercise logging, rest timer, and completion.
- Workout Book: pagination, recommended books, and selecting a workout.

## Trainer app guides

### First-login guide

Recommended steps:

1. Dashboard
2. Members
3. Member profile
4. Workout Builder
5. Exercise picker
6. Groups, rounds, and transition seconds
7. Assigning a workout
8. Messages and settings

### Contextual guides

- Workout Builder: exercise selection, ordering, group type, rounds, and transitions.
- Member Profile: activity, consent/permissions, privacy requests, and workout assignment.
- Notifications: member updates and communication preferences.

## Guide behavior

- Start only after authentication, required consent, and the destination screen’s initial loading state are complete.
- Automatically scroll to an off-screen target where possible.
- If a target is unavailable, skip that step safely and continue.
- Keep the underlying screen mounted so the user understands context.
- Prevent accidental interaction with unrelated controls while the overlay is active.
- Place the tooltip where it does not cover the highlighted target.
- Use a progress label such as `2 of 6`.
- Use subtle motion for spotlight and tooltip transitions.
- Render a static version when reduced motion is enabled.
- Announce the current step to screen readers.

## Settings and replay

Add a `Replay guides` action under Settings. It should allow users to choose:

- Replay all guides
- Replay Member/Trainer overview
- Replay a specific feature guide
- Turn guides off

Turning guides off must not disable help content, tooltips, or normal app functionality.

## Optional backend support

The first release can use local guide definitions and state. A later backend-controlled system may support:

- Enabling or disabling guides remotely
- Role-specific guide configuration
- Copy changes without an app release
- Feature-specific rollout percentages
- Guide expiration dates
- Release-specific announcements

Remote configuration must fail safely: if the endpoint is unavailable, the app should use the bundled guide definitions.

## Analytics

Track only guide interaction events:

- `guide_started`
- `guide_step_viewed`
- `guide_completed`
- `guide_skipped`
- `guide_replayed`

Do not include workout values, health data, body measurements, photos, message content, or other private user data in guide analytics.

## Accessibility and responsive requirements

- Minimum accessible tap target: 44×44 logical pixels.
- Sufficient contrast between overlay, tooltip, text, and controls.
- Semantic labels for each highlighted control and guide step.
- Full support for small Android screens and iPhone safe areas.
- No tooltip should be hidden behind system navigation or the app bottom navigation.
- Support keyboard dismissal and text-scale settings.
- Support reduced motion and accessible navigation settings.

## Testing plan

Test the following flows in both apps:

- First login and first guide launch
- Returning user after completion
- Returning user after skipping
- Replay from Settings
- Guide version upgrade
- Target widget not mounted
- Target widget off-screen
- Small-screen layout
- Bottom-navigation and safe-area overlap
- Keyboard open during a guide
- Reduced-motion mode
- Screen-reader semantics
- Logout and login with a different account
- Network/API failure when remote configuration is enabled

Add shared widget tests for the engine and focused integration tests for the Member and Trainer tour definitions.

## Delivery phases

### Phase 1: Shared engine

- Create overlay, step, controller, target registration, and persistence components.
- Add shared theme, tooltip, spotlight, navigation, and reduced-motion behavior.
- Add widget tests for all guide states.

Estimated effort: 1–2 days.

### Phase 2: Member and Trainer overview tours

- Add stable target keys/registrations.
- Add role-specific copy and step definitions.
- Trigger tours at the correct post-login point.

Estimated effort: 1–2 days.

### Phase 3: Contextual feature tours

- Add Workout Builder guide.
- Add Active Workout and Rest Timer guide.
- Add Body Metrics and privacy/settings guidance.

Estimated effort: 1–2 days.

### Phase 4: Quality and rollout

- Complete accessibility and responsive QA.
- Add analytics events if required.
- Test on physical Android and iOS devices.
- Release behind a local or remote feature flag.
- Review completion and skip rates before expanding the guides.

Estimated effort: 1–2 days.

## Acceptance criteria

- Member and Trainer use the same overlay engine and visual language.
- A new user can understand the main navigation and primary workflows.
- Users can skip, finish, or replay guides.
- Completed guides do not reappear unexpectedly.
- Guides are account-specific and versioned.
- Missing targets never crash or block the app.
- Guides do not cover system navigation, bottom navigation, or keyboard controls.
- Reduced-motion and accessibility behavior are supported.
- No private user data is sent through guide analytics.

## Estimated total

The complete first release is approximately **4–7 working days** across both apps. A focused first milestone containing the shared engine plus the two overview tours can be delivered in approximately **3 days**.
