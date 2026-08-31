# Exercise Catalog and Training Features — End-to-End Implementation Plan

## Status

Implementation started. The exercise-catalog foundation, importer, initial API extensions, and initial Member/Trainer catalog consumption described below are implemented locally. Remaining phases are not complete merely because this document exists.

This plan incorporates the usable exercise-library, workout-execution, coaching, analytics, scheduling, localization, and portability ideas identified during the `openGym-main` comparison while preserving the existing Gym Atlas Laravel, Member App, Trainer App, shared Flutter, gym-admin, and platform-admin architecture.

Audit basis: local `openGym-main` commit `220b7bdb6683765623a172a3f4ecc55d68b7c6f8` dated 2026-08-26. The inventory was cross-checked against its README, changelog through v1.2.4, frontend views/components, state model, and backend routes. Re-audit this section if the openGym snapshot is updated.

### Implemented first slice

- Added additive exercise semantics, provenance, translations, aliases, optional media, and import-batch persistence.
- Added `exercise-catalog:import`, which is dry-run by default and requires `--apply` to write.
- Added an optional strict source-count gate and explicit staged `--publish` behavior.
- Imported rows remain inactive/pending review unless publication is explicitly requested.
- Matching uses `source_key + source_external_id` first. A unique source name can also update the oldest exact normalized-name match among existing global exercises, preserving its ID and all plan/session/history references; collisions are reported.
- Source `image`, `gif_url`, `media_id`, attribution, and other media fields are counted and ignored.
- Added localized instructions, instruction steps, catalog semantics, and nullable `preview_media` to the API while retaining legacy fields.
- Added equipment, target-muscle, difficulty, tracking-mode, bodyweight, alias, and approved translated-name search/filter foundations.
- Member exercise search now queries the paginated server catalog and displays localized instructions/steps and optional future previews.
- Trainer catalog consumption supports localization, larger pagination, new filters, and richer exercise metadata.
- Trainer-created exercises now retain body part, target muscle, inferred tracking mode, and bodyweight semantics.

Real-source validation against the current upstream JSON found 1,324 accepted rows, zero rejected rows, 13,240 instruction translations, six duplicate normalized names requiring review, and 5,296 media-field references intentionally ignored. An isolated apply created zero media rows and left all 1,324 exercises inactive/pending review; the next dry run reported all 1,324 unchanged.

## Confirmed product decisions

1. Use the large structured exercise dataset for exercise metadata and translations.
2. Do not import or display the dataset's before/after images.
3. Do not import, copy, bundle, cache, proxy, or display the Gym visual GIFs until Gym Atlas obtains its own applicable media license.
4. Keep exercise previews optional. An exercise must remain fully usable with text instructions and no media.
5. Build the media contract now so a future preview can point to either:
   - a remote URL; or
   - a locally stored file served by Gym Atlas.
6. Keep current exercises, trainer-created exercises, member-owned plans, Workout Books, completed sessions, records, measurements, photos, and daily steps intact.
7. Implement ideas in Laravel and Dart without copying AGPL-covered `openGym-main` source code.

## Source-count verification

The requested target is a 1,326-exercise catalog. The currently inspected upstream repository advertises 1,324 exercises. The implementation must not silently assume either count.

The importer must print and persist:

- source commit or release identifier;
- total source rows;
- rows accepted;
- rows rejected with reasons;
- rows merged into existing Gym Atlas exercises;
- new exercises inserted;
- existing source-owned exercises updated;
- translations inserted or updated;
- duplicate source IDs;
- duplicate normalized names;
- missing required fields; and
- media references intentionally ignored.

The public UI should say `1,300+ exercises` unless an exact, reviewed, published count is read dynamically from the Gym Atlas database.

## Licensing and provenance boundary

### Allowed now

Subject to retaining the applicable MIT notice, import only the upstream fields covered by the dataset's MIT grant:

- exercise names;
- categories and body parts;
- target and secondary muscles;
- equipment;
- structured instructions;
- instruction steps;
- available translations;
- identifiers and non-media catalog metadata; and
- dataset structure or import tooling that is independently reimplemented for Gym Atlas.

### Explicitly excluded now

- `image` files and image URLs;
- `gif_url` files and GIF URLs;
- files under upstream `images/` or `videos/` directories;
- media copied from `openGym-main/media`;
- public-CDN links that indirectly display unlicensed media;
- AI-generated derivatives of Gym visual media; and
- any upstream media ID treated as proof of a Gym Atlas license.

### Required provenance

Every imported exercise must retain enough provenance to audit its origin:

- `source_key` such as `hasaneyldrm_exercises_dataset`;
- `source_external_id`;
- pinned source commit;
- source URL;
- source license code;
- import batch ID;
- imported timestamp;
- last synchronized timestamp; and
- normalized content checksum.

Keep a snapshot of the applicable upstream license and notice in the deployment/legal records. Do not rely only on a live URL whose contents can change.

## Non-negotiable architecture rules

1. Existing Gym Atlas exercise IDs remain canonical. Upstream IDs are external identifiers, never replacements for database primary keys.
2. Imports are additive and idempotent. Re-running the same pinned import must not duplicate exercises, aliases, translations, or media rows.
3. Imported data never name-matches trainer-created or gym-created scoped content. Exact normalized-name matching is limited to global exercises and never uses fuzzy similarity.
4. If several global exercises share the exact normalized name, the lowest existing ID remains canonical and all candidates are reported for later duplicate review; no historical exercise is deleted.
5. Exercise media is a separate optional concern. Workout plans and completed sessions reference `exercises.id`, never a URL or file path.
6. Completed workout history remains immutable except through an existing explicitly authorized correction path.
7. Trainer, gym, branch, independent-coaching, and member-personal ownership scopes remain unchanged.
8. Member and Trainer apps receive the same canonical exercise semantics from Laravel.
9. All new contracts are additive and backward compatible until old app versions are outside the supported release window.
10. Safety-sensitive guidance must be reviewed before an imported exercise is published.

## Existing capabilities to preserve

Gym Atlas already supports these foundations and must extend rather than rebuild them:

- global and scoped exercise records;
- primary and secondary muscle fields;
- equipment, difficulty, instructions, image URL, and video URL fields;
- paginated exercise discovery;
- trainer-created exercises;
- Workout Books, templates, assigned plans, and member-created plans;
- selected plan-day workout sessions;
- guided set logging;
- per-exercise rest settings and a basic rest timer;
- recent exercise history;
- personal records for best weight, repetitions, and volume;
- completed workout history and logbooks;
- member weight, measurements, progress photos, and daily steps;
- gym and independent trainer-member coaching scopes; and
- notifications, chat, events, tasks, and trainer follow-up surfaces.

## Complete openGym feature disposition

This is the controlling inventory for the comparison. It accounts for every feature advertised in the inspected `openGym-main/README.md`, plus every item in its roadmap. A feature appearing here does not mean openGym source code or media will be copied. Gym Atlas will independently implement the selected product behavior in its existing Laravel and Flutter architecture.

### Status definitions

- **Extend now**: Gym Atlas has the foundation; add the missing behavior in the main delivery phases.
- **Implement now**: useful and feasible without restricted media; include it in the main delivery phases.
- **Implement later**: valuable, but dependent on earlier schema, UX, safety, or product work.
- **License-gated**: build only the nullable media plumbing now; activate content after Gym Atlas obtains and records its own applicable license.
- **Keep existing approach**: openGym's implementation would conflict with Gym Atlas architecture or product boundaries; retain the Atlas equivalent.
- **Do not adopt**: not suitable for the Gym Atlas product.

### Training, planning, and progress

| openGym feature | Decision for Gym Atlas | Member App | Trainer App / admin | Required adaptation |
|---|---|---|---|---|
| Body-weight chart with a user-set goal line and direction-aware gain/loss colors | **Extend now** | Set, edit, or clear a target; see trend and whether change moves toward the target | Trainer sees it only in an authorized coaching scope | Extend existing weight records; never label healthy/unsafe targets automatically |
| Weekly routine by weekday | **Extend now** | Today card and weekly calendar | Build and assign recurring schedules | Reuse Workout Books, plans, plan days, and `weekly_schedule` |
| Large searchable exercise library | **Implement now** | Search/filter 1,300+ approved exercises | Search, review, and add to templates | Use metadata/translations only; reconcile the requested 1,326 count against the inspected 1,324-row claim |
| Animated exercise demonstrations | **License-gated** | Optional preview when licensed; text-only until then | Media review and activation | Support `remote_url` and `local_storage`; do not ingest or display Gym visual media now |
| Move one workout to another date without altering the recurring plan | **Implement now** | Reschedule a single occurrence | Trainer may reschedule within scope | Add audited date-specific overrides and collision rules |
| Start today's guided workout automatically | **Extend now** | Clear "Start today's workout" entry point | Trainer sees assigned-session status | Preserve explicit plan-day identity and allow user confirmation |
| Ask for body weight before a workout | **Implement now, optional** | Dismissible pre-workout weigh-in using the member's preferred unit | Trainer cannot require it unless product policy later permits | Never block workout start; store as a normal timestamped weight record and avoid duplicates |
| Pre-fill working values from the last comparable workout | **Extend now** | Previous load/reps/time appears as an editable starting value | Trainer sees source history and recommendation | Compare only compatible tracking modes, exercise identity, and units |
| Rest timer and background completion alert | **Extend now** | Start/adjust/skip, sound/vibration, background notification | Configure plan rest defaults | Extend existing timer and notification infrastructure |
| Personal-record detection | **Extend now** | Show best weight, reps, volume, and eligible estimated 1RM records | Review member records in scope | Define each PR type server-side; do not compare incompatible set modes |
| Per-exercise performance history | **Extend now** | Exercise-specific trend and recent sets | Coaching review | Use completed server history, not local-only calculations |
| Keep screen awake during an active workout | **Implement now** | Preference-controlled wake lock | None | Acquire only during an active session and always release on exit/completion |
| Supersets | **Implement now** | Back-to-back grouped logging with rest at the correct boundary | Create, reorder, preview, and assign groups | Store structural group IDs and ordering; do not encode grouping in names |
| Circuits | **Implement now** | Round-aware grouped execution | Configure rounds, order, transition, and rest | Gym Atlas extension beyond the advertised superset feature |
| Timed exercises and separate work timer | **Implement now** | Record actual hold/work duration, with optional load | Prescribe duration and optional load | Work and rest timers remain distinct and lifecycle-safe |
| Linear progression | **Implement later** | See next target and explanation | Configure/override/approve policy | Launch after mode-aware workout history is reliable |
| Greyskull LP, including AMRAP behavior, jump rules, stalls, and reset | **Implement later** | Follow explained targets | Advanced trainer/member-plan configuration | Version the algorithm and validate simpler policies first |
| Double progression through a repetition range | **Implement later** | See rep/load target and reason | Configure ranges and increments | No increase after missed targets |
| Add-time progression | **Implement later** | Duration targets for eligible movements | Configure time increment and ceiling | Applies only to compatible timed exercises |
| Routine-level progression with per-exercise override | **Implement later** | Read-only target explanation during execution | Configure default plus overrides | Persist policy snapshots so old prescriptions remain auditable |
| Stall detection and deload | **Implement later** | Explain why a target was reduced | Configure thresholds and approve/disable | Must be deterministic and based on completed history |
| Estimated one-repetition maximum curve and calculator | **Implement later** | Trend, contributing set, and what-if calculator | Review eligible strength trends | Use a documented formula and refuse estimates above the configured rep threshold, initially 12 |
| Optional effort per set using RIR or RPE | **Implement now** | Choose a scale or leave it disabled; each set retains its scale | View effort trends within coaching scope | Do not feed effort into first-release progression or e1RM calculations |
| Native bodyweight logging with no meaningless load field | **Implement now** | Log reps directly | Mark exercise semantics in builder/catalog | Preserve optional added external load separately |
| Bodyweight progression by reps, then sets up to a ceiling | **Implement later** | See explained rep/set targets | Configure ceilings and variation/load advice | Treat advice as coaching guidance, not an automatic safety claim |
| Added-load bodyweight work, such as weighted dips | **Implement now** | Log bodyweight movement plus external load | Prescribe additional load | Do not store total body weight as lifted barbell load |
| Repetitions per side | **Implement now** | Show per-side interpretation and step valid totals | Mark per-side exercises and prescriptions | Store unambiguous totals/side mode; target increments must remain divisible as configured |
| Cardio logged by duration and speed | **Implement now** | Enter time and speed, with optional distance/pace | Prescribe compatible cardio targets | Normalize units server-side and avoid weight-times-reps volume |
| Plan sharing as a small merge-safe file | **Adapt and implement later** | Review and adopt a shared plan | Share template/plan through a scoped link or file | Create new owned IDs and never overwrite an existing plan |
| Clean plan PDF | **Implement later** | Download eligible personal plans | Generate/share assigned plan PDFs | Exclude private history, billing, and unauthorized coaching notes |
| Adaptive equipment filters whose visible combinations have results | **Implement now** | Equipment profile and result-backed filters | Filter catalog while building plans | Calculate facets from the filtered server query, not hard-coded choices |
| User-created exercise with optional description | **Extend now** | Create where policy permits and use everywhere appropriate | Trainer-created/scoped exercises remain supported | Add description, tracking semantics, duplicate warnings, and review/ownership boundaries |
| Activity heatmap shaded by training time | **Implement now** | Year view and adherence drill-down | Member-level coaching view | Use a documented duration/score and treat reschedules/rest days correctly |
| Front/back muscle map, period filters, missing-muscle list, builder preview, and completion summary | **Implement later** | Completion and week/month/all-time coverage | Plan-builder preview and coaching summary | Start with accurate taxonomy and charts/chips; add anatomical artwork only from an owned or properly licensed source |

### Notifications, personalization, localization, and ownership

| openGym feature | Decision for Gym Atlas | Member App | Trainer App / admin | Required adaptation |
|---|---|---|---|---|
| Planned-workout reminders when no workout has been logged | **Extend now** | Opt in, set time/quiet hours, deep-link to workout | Trainer may receive a separate follow-up signal after repeated misses | Reuse FCM and existing notification preferences; make sends idempotent |
| Rest-timer alert while the app is backgrounded or closed | **Extend now** | Local notification with correct session context | None | Cancel stale alerts on set/session changes |
| Passkey login and per-profile sync | **Keep existing approach** | Continue current Gym Atlas authentication and Laravel sync | Continue current role/access model | Do not replace Firebase/account authentication merely to match openGym |
| Admin view of who is training now and per-user workout history | **Adapt and implement later** | No new member surface | Authorized gym/trainer live-session status and history | Enforce gym, branch, trainer, and independent-coaching scopes; no global surveillance view |
| Disable accounts and invite-only signup | **Keep existing approach** | Existing membership/account lifecycle | Existing platform/gym controls | Extend only if an audited product gap is found; do not import openGym's single-instance admin model |
| Light/dark theme | **Implement later** | Follow system plus explicit light/dark preference | Same shared theme behavior | Persist per-user preference and meet contrast/accessibility requirements |
| Eight accent colors | **Do not adopt as specified** | Retain a coherent Gym Atlas brand/theme | Same | Limited approved brand variants may be considered; arbitrary accents would weaken Member/Trainer parity |
| Hand-drawn custom icon set | **Do not copy; optional redesign later** | Continue the shared Atlas design system | Same | Any new icons must be original or properly licensed and accessible |
| UI localization in 12 languages | **Implement incrementally** | Localized navigation, workout, validation, dates, units, and notifications | Shared terminology plus trainer-specific strings | Start with launch-priority locales; openGym translations cannot automatically be copied under the dataset license |
| Exercise instructions in available dataset languages, loaded on demand | **Implement now, review-gated** | Field-level locale fallback | Translation review tools | Import only licensed text, retain provenance, review safety-sensitive wording, and paginate/load on demand |
| Import FitNotes, Strong, Hevy, and Apple Health body-weight exports | **Implement later** | Preview, match, resolve, then confirm import | Optional assisted review, never silent mutation | Preserve unmatched rows as custom-exercise candidates and make re-import idempotent |
| One-tap JSON data export | **Implement later** | Export eligible member workout/progress data | Export only data authorized for the acting role | Version the schema and exclude billing, private staff notes, secrets, and unrelated scopes |
| JSON restore/import | **Implement later, constrained** | Preview a supported export before import | Admin/support diagnostics when authorized | Never overwrite the live account wholesale; validate version, ownership, duplicates, and conflicts |
| Guest mode without an account | **Do not adopt** | Account remains required for protected Gym Atlas features | None | Guest/local-only data conflicts with membership, trainer assignment, server truth, and recovery expectations |
| No telemetry | **Adopt the privacy principle, not a blanket technical ban** | Clear consent and privacy controls | Auditable operational access | Collect only necessary, disclosed operational/product data; never add hidden tracking |
| Standalone Android APK with no server/account and phone-only data | **Do not adopt** | Continue supported Member/Trainer store builds connected to Laravel | None | It would create a parallel unsynced product and bypass coaching/membership rules |
| Installable offline PWA and self-hosted personal deployment | **Do not adopt as a parallel product** | Add targeted resilient caching/offline workout recovery where safe | None | Laravel remains authoritative; never allow offline sync to duplicate completed sessions or ledger-like records |

### Additional implemented details found in the openGym code and changelog

These details are easy to miss if only the README feature bullets are reviewed. They are included so the comparison covers the actual project behavior as well as the headline list.

| openGym detail | Decision for Gym Atlas | Required adaptation |
|---|---|---|
| Effort analytics over 30 days, 90 days, one year, or all time | **Implement later** | Show average effort together with rated-set coverage; an average must never imply that unrated sets were rated |
| Weekly effort trend with rated-set count | **Implement later** | Suppress statistically empty points and expose the underlying count |
| RIR/RPE distribution histogram | **Implement later** | Normalize only for aggregation; preserve the original scale on every set |
| Near-failure or hard-set muscle coverage mode | **Implement later** | Define the threshold transparently and avoid presenting it as proof of training quality or safety |
| Effort overlay and effort curve on exercise progress | **Implement later** | Plot only compatible sessions and show rated-data coverage |
| Exercise progress metric switches between top load, timed duration, e1RM, and effort | **Implement later** | Select metrics by tracking mode and never combine incompatible values on one curve |
| Configurable default rest duration | **Extend now** | Retain per-exercise/plan overrides and member runtime adjustment |
| Workout sound and vibration preferences | **Extend now** | Separate work-timer, rest-timer, and completion cues; respect platform permissions and silent modes |
| Weight-unit preference | **Extend now** | Store canonical values and convert display/input correctly; changing units must not relabel unchanged numbers |
| Locale-aware decimal input, dates, weekdays, months, and number formatting | **Implement now** | Accept comma and dot decimals safely, preserve partial input, and normalize on validation |
| Settings organized by General, During workout, Notifications, Appearance, and Data | **Adapt now** | Apply the same information architecture in Member App; expose only relevant subsets in Trainer App |
| Male/female body-diagram preference | **Implement with muscle map later** | Use inclusive product language and owned/licensed anatomical artwork |
| Starter Push/Pull/Legs plan | **Adapt later** | Publish as a reviewed Atlas Workout Book/template with audience, level, equipment, and disclaimer metadata |
| Routine icons | **Optional later** | Reuse the existing Atlas icon system; do not copy openGym artwork |
| Raised Start action that becomes Resume during an active workout | **Adapt now** | Make active-session recovery obvious while preserving Atlas navigation and visual language |
| Home dashboard with week strip, today's workout, weight, and streak | **Extend now** | Compose existing server data; define streak rules and do not punish planned rest or approved reschedules |
| Persistent animation minimize/expand preference | **License-gated UI, later** | Apply only after licensed previews exist; remember the preference and always keep text instructions accessible |
| Pause/play animated preview | **License-gated UI, later** | Respect reduced-motion/accessibility preferences and provide a static or text fallback |
| Edit/delete custom exercises while preserving historical workout labels | **Extend now** | Prefer archive/soft-delete when history references the exercise; never erase completed-set meaning |
| Search custom exercises by description/cues | **Extend now** | Scope private/trainer-owned content correctly and index approved searchable fields |
| Resume safely after an invalid or missing exercise reference | **Implement now** | Render a recoverable placeholder, preserve valid session data, and send diagnostics without exposing member content |
| Workout reminders calculated in the member's timezone | **Extend now** | Persist IANA timezone, handle travel/DST, and enforce quiet hours and idempotency |
| Immediate persistence of changed notification settings | **Extend now** | Save server-side explicitly and show failure; do not depend on app shutdown hooks |
| Test-notification action | **Implement now** | Provide a scoped test that does not create a workout/reminder record |
| Sign out all devices | **Keep/extend existing security approach** | Revoke applicable server sessions/tokens with an audit event; preserve the chosen authentication stack |
| Reset/delete all personal workout data | **Implement only as a dedicated privacy flow** | Require re-authentication, preview the scope, use retention/legal rules, and never hide it inside ordinary settings |
| Native share sheet for exports | **Implement later** | Use platform share APIs only after a privacy-safe export artifact is generated |
| Error boundary/recoverable screen failure | **Implement now** | Flutter screens need explicit failure states, retry paths, and crash reporting that excludes sensitive payloads |
| Responsive workout controls and numeric steppers | **Implement now** | Validate narrow phones, large text, keyboard visibility, tap targets, and overflow in both apps |
| Accessibility-aware contrast for themes and accents | **Implement now** | Meet contrast requirements in the Atlas design system even though eight user-selected accents are not adopted |
| Local cache survives process eviction in standalone mode | **Adapt only for active-workout recovery** | Persist a draft safely, reconcile with server truth, and prevent duplicate set/session completion |

Pure openGym deployment mechanics—Docker images, nginx proxying, JSON-file storage, VAPID auto-generation, GitHub Pages demo deployment, and Capacitor sideload packaging—are not Gym Atlas product features. They are intentionally not ported because Gym Atlas already has Laravel deployment, Flutter store apps, Firebase/cloud services, and relational persistence. Relevant behavior, such as background reminders and recovery, is implemented through those existing systems.

### openGym roadmap ideas

These were not completed openGym features in the inspected source description, but they are still useful candidates to assess.

| openGym roadmap item | Decision for Gym Atlas | Rationale |
|---|---|---|
| Percentage/training-max programming such as 5/3/1 | **Implement later** | Valuable after the progression engine, e1RM semantics, rounding, deloads, and plan versioning are proven |
| More starter plans: upper/lower, full-body, and 5x5 | **Implement later** | Add reviewed templates through existing Workout Books; avoid presenting generic plans as individually safe |
| Body measurements alongside weight | **Already present; improve integration** | Gym Atlas already records measurements; connect them to progress views and permissions rather than rebuilding storage |
| Per-exercise notes | **Extend now** | Separate member session notes, trainer coaching notes, and template instructions with correct privacy scopes |
| Plate calculator | **Implement later** | Useful for barbell exercises after unit, bar-weight, available-plate, and rounding preferences are modeled |
| German and Portuguese exercise instructions | **Implement when licensed source text exists and is reviewed** | Locale availability comes from the chosen dataset version; missing safety instructions must fall back to reviewed English |

### Coverage conclusion

All advertised openGym features and all roadmap entries are now accounted for. The features deliberately not carried over are architectural/product choices rather than omissions: passkeys replacing Atlas authentication, guest/local-only accounts, a standalone unsynced tracker, a separately self-hosted PWA product, arbitrary accent colors, and copying openGym/Gym visual artwork. Everything else is either already present, selected for implementation, adapted to Atlas access controls, or explicitly sequenced for later delivery.

## Target domain model

### Exercise catalog extensions

Extend `exercises` only with fields that describe the canonical exercise:

- `movement_pattern`: squat, hinge, push, pull, carry, locomotion, rotation, anti-rotation, mobility, or other;
- `default_tracking_mode`: `reps`, `timed`, `cardio`, or `distance`;
- `is_bodyweight`: boolean;
- `supports_external_load`: boolean;
- `is_per_side`: boolean;
- `review_status`: `imported`, `in_review`, `approved`, `rejected`, or `archived`;
- `reviewed_by_user_id` and `reviewed_at`; and
- nullable provenance shortcut fields if query performance requires them.

Do not force all imported exercises to become visible immediately. Imported rows begin in `imported` or `in_review` state and require validation before publication.

### Exercise provenance

Add `exercise_sources` or equivalent source-attribution records:

- `exercise_id`;
- `source_key`;
- `source_external_id`;
- `source_url`;
- `source_commit`;
- `license_code`;
- `import_batch_id`;
- `content_checksum`;
- `imported_at`; and
- `last_synced_at`.

Unique key: `source_key + source_external_id`.

### Exercise translations

Add `exercise_translations`:

- `exercise_id`;
- normalized BCP-47 locale;
- translated name when supplied and reviewed;
- translated instructions;
- translated instruction steps as JSON;
- translation source;
- review status; and
- reviewer metadata.

Unique key: `exercise_id + locale`.

English remains the fallback. Missing fields fall back individually rather than causing an entire localized exercise to disappear.

### Exercise aliases

Add `exercise_aliases` for search and import matching:

- `exercise_id`;
- alias;
- normalized alias;
- locale;
- source; and
- review status.

Aliases must not change the canonical display name or create ambiguous automatic merges.

### Optional exercise media

Add an `exercise_media` table instead of expanding URL columns repeatedly:

- `exercise_id`;
- `kind`: `image`, `gif`, or `video`;
- `source_type`: `remote_url` or `local_storage`;
- nullable `remote_url`;
- nullable `storage_disk`;
- nullable `storage_path`;
- MIME type;
- width, height, and duration when known;
- checksum;
- `license_code`;
- attribution text;
- license evidence reference;
- `status`: `pending`, `active`, `disabled`, or `expired`;
- sort order; and
- audit timestamps.

Validation invariant: exactly one of `remote_url` or `storage_path` is populated according to `source_type`.

The existing `image_url` and `video_url` fields remain available during migration. A compatibility resolver may expose them as legacy media without moving or deleting anything.

### Preview resource contract

Exercise resources should eventually expose:

```json
{
  "preview_media": null
}
```

or:

```json
{
  "preview_media": {
    "kind": "gif",
    "source_type": "remote_url",
    "url": "https://licensed.example/exercises/bench-press.gif",
    "mime_type": "image/gif",
    "attribution": "Required attribution, if any"
  }
}
```

For local storage, Laravel resolves the authorized storage path into a URL. Flutter never needs to understand server filesystem paths.

Member and Trainer UI rules:

- `preview_media == null`: show the exercise name, taxonomy, and instructions with no placeholder claiming media exists;
- media loading: show a bounded skeleton;
- media failure: retain the complete text experience and expose retry only when useful;
- disabled or expired license: return `null` rather than a broken or unauthorized URL; and
- never block workout logging because preview media is unavailable.

### Member exercise preferences

Add member-scoped preferences where justified:

- favourite exercises;
- recently used exercises derived from history where possible;
- available equipment profile;
- explicitly hidden or unsuitable exercises; and
- trainer-approved substitutions.

Avoid copying the full catalog into member-owned tables.

## Dataset ingestion workflow

### Import command

Use the implemented dry-run-first command:

```text
php artisan exercise-catalog:import /absolute/path/to/exercises.json \
  --source-commit=<pinned-commit> \
  --expected-count=1324 \
  --strict-count
```

Required behavior:

1. Require an explicit local source file and pinned source identifier.
2. Validate JSON shape before opening a transaction.
3. Ignore and report all upstream media fields.
4. Normalize names, locales, equipment, body parts, targets, and muscle groups through explicit mappings.
5. Match first by source identity.
6. If no source identity exists and the source name is unique in that import, update the oldest exact normalized-name global match in place.
7. Report every multiple-existing-name collision and never delete the remaining historical rows automatically.
8. Do not name-match duplicated names within the source file; import those distinct source identities independently.
9. Upsert only source-owned fields on previously imported source rows.
10. Preserve Gym Atlas reviewer corrections unless a reviewer explicitly accepts an upstream replacement.
11. Print affected IDs and a complete summary.
12. Support dry run by default, explicit `--apply`, optional `--publish`, and deterministic retry.

### Production seed runbook

The dataset is intentionally not committed to Gym Atlas because it is a large independently versioned source file. Pin and download only `data/exercises.json`; do not download or deploy its `images/` or `videos/` directories.

```bash
cd /var/www/gym_ecosystem/backend_laravel
mkdir -p storage/app/imports/exercise-catalog
curl -fsSL \
  https://raw.githubusercontent.com/hasaneyldrm/exercises-dataset/7455efae41b330c265e7cd4b78dfa848e7ce5ebd/data/exercises.json \
  -o storage/app/imports/exercise-catalog/exercises-7455efae.json

php artisan exercise-catalog:import storage/app/imports/exercise-catalog/exercises-7455efae.json \
  --source-commit=7455efae41b330c265e7cd4b78dfa848e7ce5ebd \
  --expected-count=1324 --strict-count
```

After the dry-run report shows 1,324 accepted, zero rejected, and expected name-collision/media-ignore counts, configure the absolute dataset path and pinned commit. Keep `EXERCISE_CATALOG_PUBLISH=false` for review-first staging, or set it to `true` only when the platform team has approved immediate publication. Then clear cached configuration, migrate, and run the idempotent seeder:

```bash
php artisan down
php artisan migrate --force
php artisan config:clear
php artisan db:seed --class=ExerciseCatalogSeeder --force
php artisan optimize
php artisan up
```

Back up the database before migration, keep the downloaded JSON and its SHA-256 with deployment records, and verify catalog counts plus Member/Trainer search before considering the server update complete.

### Review workflow

Platform Admin needs a catalog-import review surface:

- new exercises;
- likely duplicates;
- rejected or malformed records;
- unresolved taxonomy values;
- translation coverage;
- exercises missing meaningful instructions;
- unsafe or unclear instructions;
- publish, merge, reject, or archive actions; and
- batch-level audit history.

Bulk approval must be limited to rows that passed deterministic validation and have no merge ambiguity.

### Publication rules

An imported exercise becomes selectable only when:

- its canonical name is present;
- target/body-part mapping is valid;
- equipment is normalized or explicitly classified as other;
- English instructions are non-empty and reviewed according to the chosen policy;
- tracking mode is set;
- safety review has no blocker; and
- it is active and approved.

Media is not a publication requirement.

## Feature workstreams

## A. Exercise discovery and reference

### A1. Large searchable catalog

Member and Trainer apps should support:

- paginated search by canonical name and aliases;
- body part;
- target muscle;
- secondary muscle;
- equipment;
- difficulty;
- movement pattern;
- tracking mode;
- bodyweight-only filter;
- available-equipment filter; and
- active/approved status.

Search and filters run on Laravel. Do not download the entire 1,300+ catalog to filter locally.

### A2. Exercise detail screen

Provide:

- localized name and instructions;
- numbered instruction steps;
- primary and secondary muscles;
- equipment;
- difficulty;
- movement pattern;
- tracking mode;
- bodyweight/per-side indicators;
- optional preview media;
- member exercise history and personal record when authorized;
- Trainer coaching notes in the correct coaching scope;
- favourite action for members; and
- add-to-workout or replace-exercise actions in the relevant context.

### A3. Favourites and recent exercises

- Members can favourite/unfavourite exercises.
- Recent exercises derive from completed history and active sessions.
- Trainer builder shows recently assigned exercises separately from the global catalog.
- Stable pagination and ID deduplication remain mandatory.

### A4. Equipment profiles

Support presets and custom selection:

- commercial gym;
- bodyweight only;
- dumbbells and bench;
- resistance bands;
- home gym; and
- custom equipment list.

Equipment profiles affect discovery and recommendations, never access to historical workouts.

### A5. Exercise substitutions

Candidate substitutions must consider:

- movement pattern;
- primary target muscle;
- secondary muscles;
- available equipment;
- tracking mode;
- difficulty;
- bodyweight/external-load semantics;
- member injury or restriction fields only when consent and product safety rules permit; and
- trainer approval requirements.

Start with curated substitutions and transparent rules. Do not present an opaque AI replacement as medically safe.

## B. Workout-plan semantics

### B1. Exercise tracking modes

Extend workout template and plan exercises with:

- `tracking_mode`;
- planned repetitions or repetition range;
- planned duration seconds;
- planned distance;
- planned speed or pace where applicable;
- target external load;
- per-side behavior;
- bodyweight behavior; and
- rest seconds.

Supported modes:

1. `reps`: repetitions with optional load.
2. `timed`: holds or work intervals with optional load.
3. `cardio`: duration plus optional distance, speed, pace, resistance, or machine level.
4. `distance`: carries, sled work, walking, or locomotion with optional duration and load.

Existing plans default to `reps` and retain their current values.

### B2. Completed-set extensions

Add nullable fields without invalidating historical sets:

- `duration_seconds`;
- `distance_meters`;
- `speed_kph`;
- `pace_seconds_per_km` where needed;
- `external_load` or keep the existing weight field with clarified semantics;
- `effort_scale`: `rir` or `rpe`;
- `effort_value`;
- `side_mode` and recorded-side detail only if separate-side logging is supported; and
- completion timestamp if accurate set timing is required.

Validation depends on tracking mode. Do not require meaningless repetitions for a timed hold or weight for an unweighted push-up.

### B3. Supersets and circuits

Add structured grouping to template and plan exercises:

- group identifier;
- group type: `superset` or `circuit`;
- order within group;
- planned rounds for circuits;
- transition seconds; and
- rest after exercise versus rest after group.

Trainer App:

- group/ungroup exercises;
- reorder within a group;
- preview A1/A2 or circuit numbering; and
- validate that a group contains enough exercises.

Member App:

- present grouped exercises together;
- move through the group in the planned order;
- start rest at the correct boundary;
- preserve partial completion if the app resumes; and
- allow an explicit safe skip without corrupting remaining order.

## C. Active workout experience

### C1. Rest timer improvements

Extend the existing timer with:

- minus 15 seconds;
- plus 15 seconds;
- skip;
- automatic start after completing a set when enabled;
- sound and vibration preferences;
- local notification when backgrounded;
- correct restoration after app resume; and
- per-exercise planned defaults.

Timer state is presentation/session state. Do not rewrite completed-set rest history merely because the live timer was adjusted.

### C2. Work timer

Timed exercises need a work timer separate from the rest timer:

- the two timers cannot run simultaneously;
- finishing early records actual elapsed duration;
- cancel does not falsely complete the set;
- app lifecycle restoration is deterministic; and
- notification/sound behavior is independently identifiable as work versus rest.

### C3. Keep screen awake

- Hold a wake lock only while a workout is active and the user has enabled it.
- Release it on completion, cancellation, logout, and unrecoverable session loss.
- Reacquire safely when the app resumes if the workout remains active.
- Document platform limitations and battery impact.

### C4. Previous-performance assistance

The active workout should show:

- previous comparable completed set;
- recent exercise history;
- current personal record;
- planned target;
- progression recommendation when enabled; and
- the explanation for that recommendation.

Never compare incompatible modes as though they were equivalent.

### C5. Planned-versus-performed review

Workout completion should summarize:

- planned versus completed sets;
- planned versus completed repetitions, duration, distance, or pace;
- planned versus actual load;
- skipped or substituted exercises;
- session duration;
- total compatible volume metrics;
- new personal records;
- estimated one-repetition-max changes where eligible;
- muscle coverage; and
- member notes.

Trainer review receives the same server truth within the correct coaching scope.

## D. Coaching intelligence

### D1. Progression policies

Start with transparent, named policies:

- off/manual;
- linear load progression;
- double progression through a repetition range;
- timed progression;
- bodyweight repetition progression; and
- a later advanced policy such as Greyskull only after the simpler rules are validated.

Store policy configuration on the plan with per-exercise overrides. Derive the next prescription from immutable completed history.

Required behavior:

- incomplete sets do not count as success;
- missed targets do not increase load;
- repeated misses may trigger a configurable deload;
- recommendations explain their inputs and decision;
- Trainer can accept, edit, or disable recommendations;
- Member-created plans can use member-controlled policies; and
- algorithm version is recorded so behavior remains auditable.

### D2. Estimated one-repetition maximum

For eligible weighted repetition sets:

- use a documented formula;
- do not estimate beyond a configured high-repetition threshold;
- identify the contributing set and date;
- expose a trend curve;
- report a new estimated record separately from best-weight PR; and
- allow Trainer review.

Do not generate estimated 1RM for timed, cardio, distance, or unsuitable bodyweight sets.

### D3. Optional RIR/RPE

- Off by default.
- Member selects RIR or RPE where permitted.
- Each logged set retains the scale used at logging time.
- Blank and zero remain distinct.
- Initial progression rules do not consume effort values automatically.
- Trainer can view effort trends with member consent and correct scope.

### D4. Muscle coverage

First release can use charts/chips; an anatomical body map is optional later.

Trainer App:

- coverage preview while building a day or plan;
- primary versus secondary weighting;
- underrepresented-region warnings framed as coaching information, not medical advice; and
- weekly-plan coverage summary.

Member App:

- muscles trained after completion;
- weekly/monthly distribution;
- filters by plan, date range, and coaching scope; and
- transparent treatment of secondary muscles.

Normalize taxonomy before calculating coverage. Unknown muscles must remain visible in an `other/unmapped` audit bucket rather than disappearing.

### D5. Effort analytics

After optional RIR/RPE collection has enough real usage, add:

- average effort plus the percentage and count of eligible sets that were actually rated;
- week-by-week effort with rated-set counts;
- distribution across the selected scale;
- near-failure-set coverage as an optional muscle-coverage mode;
- effort points on compatible exercise-performance charts; and
- 30-day, 90-day, one-year, and all-time filters.

Convert RPE/RIR only for aggregate comparison. Every source set retains its originally recorded scale and value. Do not issue fatigue, injury, readiness, or medical conclusions from effort charts.

## E. Scheduling, adherence, and reminders

### E1. Weekly plan calendar

Build on existing `weekly_schedule`, plan days, and selected workout day:

- show today's planned session;
- display completed, missed, rest, and rescheduled days;
- allow one-date overrides without changing the recurring schedule;
- preserve the selected plan-day identity in the resulting session; and
- support member-personal, gym, and independent-trainer plans without mixing scopes.

### E2. Date-specific rescheduling

Add a schedule-override record containing:

- member;
- plan and plan day;
- original date;
- replacement date or rest-day override;
- creator and coaching scope;
- reason/notes when supplied;
- status; and
- audit timestamps.

Conflict rules must prevent two active assignments from silently occupying the same date unless the product explicitly supports multiple workouts.

### E3. Activity heatmap and adherence

- Heatmap intensity should use duration or an explicitly documented compatible score.
- Adherence is scheduled sessions completed divided by eligible scheduled sessions.
- Rescheduled sessions count against the final agreed date, not twice.
- Rest days do not reduce adherence.
- Archived/cancelled plans and membership changes require deterministic date boundaries.
- Trainer dashboards show member-level signals, not misleading cross-member aggregate judgments.

### E4. Reminders

Support configurable:

- scheduled-workout reminder;
- missed-workout follow-up;
- rest-timer completion;
- workout streak encouragement;
- trainer follow-up signal after repeated misses; and
- quiet hours and notification preferences.

Do not duplicate existing FCM or notification-preference infrastructure. Extend it with stable notification types and deep links into existing workout screens.

## F. Localization

### F1. Exercise-content localization

Initial dataset locales may include English, Spanish, Italian, Turkish, Russian, Chinese, Hindi, Polish, Korean, and French. Actual availability must be read from each source row.

Rules:

- normalize locale identifiers;
- fall back field-by-field to English;
- never machine-translate and immediately publish safety instructions without review;
- preserve the original source text and translation provenance;
- allow Platform Admin correction without losing the next import diff; and
- test right-to-left readiness separately if an RTL language is introduced later.

### F2. App-interface localization

Exercise translations do not automatically localize Member and Trainer UI. Add Flutter localization infrastructure separately:

- common navigation and action strings;
- workout-mode terminology;
- timer and notification strings;
- validation and error messages;
- units, dates, durations, pace, and number formatting; and
- translated push-notification payload strategy.

Start with English and Hindi product UI if that matches launch priorities, then expand based on actual usage.

## G. Plan portability and history import

### G1. Plan PDF

Trainer and eligible members can export a readable plan containing:

- plan identity and owner/source;
- weekly schedule;
- days and exercise order;
- supersets/circuits;
- mode-appropriate targets;
- rest settings;
- coaching notes according to permissions; and
- optional media only when licensed and explicitly enabled.

The PDF must not expose private progress history, payments, memberships, or unrelated coaching scopes.

### G2. Internal plan sharing

Prefer authenticated Atlas sharing over arbitrary raw-file imports:

- share a plan/template through a scoped token or recipient selection;
- recipient reviews before adoption;
- adoption creates new plan ownership and IDs;
- existing plans are never overwritten;
- source attribution remains visible; and
- links expire or can be revoked.

### G3. External workout-history import

Later support FitNotes, Strong, Hevy, and health-export body weight through a staged preview:

- detect source format;
- normalize units per row;
- match exercise aliases conservatively;
- create unresolved custom-exercise candidates rather than dropping rows;
- preview matched, unmatched, skipped, and invalid rows;
- require confirmation before persistence;
- maintain import batch provenance; and
- prevent duplicate sessions when re-importing the same file.

Do not start history import until tracking modes can represent the imported data honestly.

### G4. Member data export

Provide a member-owned export of eligible workout and progress data with:

- documented schema version;
- exercise source IDs and canonical Gym Atlas IDs;
- units and timezone;
- plan/session/set relationships;
- progress records according to privacy rules; and
- no gym-private billing, staff-note, or audit data.

## H. Member progress, preferences, and resilience

### H1. Weight goal and optional workout check-in

- Extend existing weight tracking with a nullable member-owned target weight and target-updated timestamp.
- The chart shows the goal line and direction-aware change without judging whether the goal is healthy.
- Let the member edit or clear the goal and choose whether it appears on home/progress surfaces.
- Before starting today's workout, optionally offer a quick weight check-in using the preferred display unit.
- Dismissal never blocks the workout and does not create a record.
- Saving the same check-in twice must not create duplicates; use an idempotency key or deterministic active-session/date rule.
- Trainer visibility follows the existing authorized coaching and progress-sharing scope.

### H2. Workout entry and recovery

- Home shows a concise week strip, today's eligible session, and a clear start action.
- While a session is active, the start action becomes resume and returns to the exact server/draft-backed position.
- A local recovery draft may retain uncommitted active-workout input across app eviction.
- Reconciliation must never duplicate completed sets or sessions and must show the member any genuine conflict.
- Missing or archived exercise references render a safe placeholder so the rest of the workout remains recoverable.

### H3. Settings and accessibility

Group Member settings by product meaning:

- General: language, units, locale, and timezone;
- During workout: timer defaults, wake lock, sounds/vibration, and effort scale;
- Notifications: permissions, reminder time, quiet hours, and test notification;
- Appearance: system/light/dark choice and future body-diagram preference; and
- Data and privacy: import, export, device/session security, and carefully separated deletion controls.

Preferences that alter server-delivered behavior must be saved explicitly to Laravel. Flutter-only presentation preferences may remain local when cross-device sync has no user benefit. Numeric inputs must support locale decimal separators, large text, narrow screens, and accessible tap targets.

### H4. Per-exercise notes

Do not collapse different note audiences into one field:

- template instructions authored by a Trainer;
- private member notes for an exercise or completed session;
- Trainer coaching notes visible under the applicable coaching relationship; and
- custom-exercise description/cues.

Each note type needs explicit ownership, visibility, edit history where appropriate, length limits, safe rendering, and retention behavior. Archiving a custom exercise does not erase its historical name or notes attached to completed sessions.

## API evolution

### Member endpoints

Extend existing endpoints where compatible and add focused endpoints only when necessary:

- paginated exercise search/filter;
- localized exercise detail;
- favourites;
- equipment preferences;
- substitution candidates;
- active-session timer-independent state;
- workout calendar and overrides;
- adherence/heatmap summaries;
- estimated 1RM and muscle-coverage summaries;
- weight-goal, optional weigh-in, preference, and active-workout recovery contracts; and
- import/export preview and confirmation.

### Trainer endpoints

- expanded exercise search/filter;
- localized exercise detail;
- plan coverage preview;
- structured superset/circuit builder payload;
- progression policy configuration;
- member adherence and progression review;
- trainer-approved substitutions; and
- plan PDF/share controls.

### Platform Admin endpoints and web panels

- import batches and dry-run reports;
- duplicate-resolution queue;
- taxonomy mappings;
- exercise and translation review;
- publication/archive actions;
- media-license evidence and activation controls;
- source health and synchronization history; and
- catalog counts by review status, locale, equipment, tracking mode, and media availability.

All list endpoints remain paginated and filter-safe.

## Flutter implementation boundaries

### Shared Flutter core

Place genuinely shared contracts/widgets in `gym_flutter_core`:

- exercise media model and renderer;
- localized exercise summary/detail primitives;
- tracking-mode formatters;
- set-target and completed-set codecs;
- superset/circuit summary widgets;
- 1RM and effort display models where calculation remains server-owned; and
- muscle-coverage charts if visual behavior is identical.

Do not force Member and Trainer workflow screens into a single shared screen when their actions and permissions differ.

### Member App

- discovery and detail;
- favourites/equipment;
- substitutions;
- active workout modes;
- timers and wake lock;
- planned-versus-performed summary;
- calendar, heatmap, and adherence;
- weight goal, optional check-in, start/resume, and active-workout recovery;
- grouped settings, units, themes, notification testing, and accessibility states;
- localized exercise instructions; and
- member-controlled imports/exports.

### Trainer App

- catalog search and exercise creation;
- workout builder modes;
- superset/circuit builder;
- progression configuration;
- muscle coverage preview;
- member adherence and performance review;
- substitutions and coaching notes; and
- PDF/internal sharing.

## Delivery phases

### Phase 0 — Contracts and safety foundation

- Finalize taxonomy mappings and tracking modes.
- Add provenance, translation, alias, and optional media structures.
- Add compatibility resource fields with `preview_media = null` by default.
- Add import batch/audit structures.
- Add license snapshot and review procedure.

Exit criteria: existing apps and APIs behave identically when no new catalog is published.

### Phase 1 — Dataset ingestion

- Implement dry-run importer.
- Pin source revision.
- Explicitly discard media fields.
- Generate duplicate/taxonomy/translation reports.
- Add Platform Admin review and staged publishing.
- Publish a reviewed first batch.

Exit criteria: exact counts reconcile and a second identical import produces no duplicates or unintended updates.

### Phase 2 — Catalog experience

- Member and Trainer paginated search/filter.
- Exercise detail and localized instructions.
- Favourites and recent exercises.
- Equipment profiles.
- Curated substitution foundation.

Exit criteria: exercises remain useful with `preview_media = null`, and older app versions continue to function.

### Phase 3 — Workout execution modes

- Plan/template tracking modes.
- Completed-set mode fields.
- Member logging UI per mode.
- Rest timer improvements.
- Work timer and keep-screen-awake lifecycle.
- Optional pre-workout weight check-in.
- Start/resume navigation and conflict-safe active-workout recovery.
- Planned-versus-performed completion summary.

Exit criteria: legacy rep/weight workouts remain unchanged and each new mode round-trips accurately through Laravel and Flutter.

### Phase 4 — Supersets and coaching progression

- Superset/circuit schema and builder.
- Member grouped execution flow.
- Simple progression policies.
- Trainer approval/override.
- Estimated 1RM.
- Optional RIR/RPE.

Exit criteria: prescriptions are explainable, versioned, and derived from server-trusted completed history.

### Phase 5 — Analytics and adherence

- Weight-goal chart integration.
- Muscle coverage.
- Effort analytics with rated-data coverage.
- Weekly calendar and date overrides.
- Heatmap and adherence calculations.
- Reminder integration.
- Trainer coaching signals.

Exit criteria: date, timezone, plan lifecycle, rescheduling, and coaching-scope cases have focused regression coverage.

### Phase 6 — Portability

- Plan PDF.
- Internal plan sharing.
- External history-import preview and confirmation.
- Member data export.

Exit criteria: repeated imports are idempotent and sharing never overwrites recipient data.

### Phase 7 — Licensed media activation

Only after Gym Atlas obtains and records appropriate rights:

- register media license evidence;
- choose remote URL or local-storage delivery per asset;
- import licensed GIF/video mappings;
- validate checksums and missing files;
- activate media through `exercise_media`;
- add caching and bandwidth controls;
- show attribution where required; and
- provide immediate disablement if rights expire or an asset is disputed.

No workout-plan or completed-session migration should be required for this phase.

## Testing matrix

### Dataset and licensing

- Source count and checksum are reported.
- Media fields are ignored and never persisted as active preview media.
- Re-import is idempotent.
- Ambiguous matches require review.
- Trainer-created exercises are unchanged.
- License/source provenance is returned to admins.
- Disabled media resolves to `null`.
- Remote and local media resolution are both tested without requiring real licensed assets.

### Localization

- Locale-specific content is returned when available.
- Missing translated fields fall back to English.
- Unsupported locales fall back safely.
- Search matches canonical names and approved localized aliases.
- Unicode search and normalization work for supported scripts.

### Workout modes

- Legacy repetitions/weight payloads remain valid.
- Timed sets record actual duration.
- Cardio/distance validation rejects incompatible values.
- Bodyweight exercises do not require a weight.
- Per-side targets use deterministic display and storage semantics.
- Mode changes cannot reinterpret completed history.
- Optional weigh-in dismissal does not block workout start or write data.
- Retried pre-workout weigh-in submission does not duplicate a weight record.

### Supersets and timers

- Group order persists through template assignment and plan duplication.
- Rest starts at the configured group boundary.
- App resume restores the correct next exercise and timer state.
- Work and rest timers cannot run simultaneously.
- Wake lock releases on every terminal path.

### Progression and analytics

- Missed or incomplete targets do not advance load.
- Deload conditions are deterministic.
- Formula/version changes do not mutate completed sessions.
- 1RM excludes ineligible sets.
- Muscle coverage includes documented secondary weighting.
- Unknown muscles remain auditable.
- RIR zero is distinct from missing effort.
- Effort averages always expose rated-set coverage and never treat missing as zero.
- Mixed RIR/RPE history preserves the original per-set scale.

### Scheduling

- Rescheduling does not modify the recurring weekly plan.
- A rescheduled workout counts once in adherence.
- Rest days and cancelled plans do not create false misses.
- Asia/Kolkata and UTC boundaries are tested.
- Gym, independent, and personal plan scopes do not merge incorrectly.

### Authorization and privacy

- Trainers access only authorized member/coaching scopes.
- Revoked independent relationships lose new access immediately.
- Former gyms cannot access member-owned retained history through new analytics.
- Imports and exports are member-owned and auditable.
- Plan sharing excludes payments, memberships, staff notes, and unrelated progress.
- Member, trainer, template, and completed-session note visibility remains distinct.
- Active-workout recovery cannot duplicate completed sessions or sets.

## Operational considerations

- Self-host catalog JSON and future licensed media rather than depending on GitHub raw URLs in production.
- Use background jobs for large import, checksum, and media-validation work.
- Index normalized names, aliases, source identity, taxonomy fields, review status, and active state.
- Keep API pagination bounded.
- Cache stable catalog responses with explicit invalidation after publication.
- Monitor storage, CDN bandwidth, media error rates, search latency, import failures, and translation fallback rate.
- Back up source snapshots and import reports alongside normal database backups.

## Explicit non-goals

- No before/after demonstration images.
- No unlicensed GIFs, videos, thumbnails, or indirect CDN embedding.
- No direct port of `openGym-main` React or JavaScript code.
- No replacement of Firebase/account authentication with passkeys.
- No local-only or guest data architecture replacing Laravel sync.
- No replacement of the existing Gym Atlas admin system.
- No removal of current exercises, plans, sessions, records, or progress data.
- No claim that imported instructions are medical advice.
- No AI generation from third-party copyrighted exercise media.

## Definition of complete

This initiative is complete only when:

1. The actual source count is reconciled and every imported row is accounted for.
2. Dataset provenance and license evidence are retained.
3. No unlicensed media is shipped or referenced.
4. The catalog is reviewed, searchable, localized, paginated, and usable without media.
5. Member and Trainer apps share the same canonical exercise semantics.
6. New workout modes, supersets, timers, progression, analytics, scheduling, and portability features preserve historical behavior.
7. Gym, branch, independent-trainer, and member-personal authorization remains correct.
8. Backend, shared Flutter, Member App, Trainer App, admin, migration, and focused regression validation passes.
9. Production deployment and mobile store releases are reported separately from source-code completion.
10. Future licensed GIF activation requires configuration/data changes, not a redesign of exercise or workout contracts.
