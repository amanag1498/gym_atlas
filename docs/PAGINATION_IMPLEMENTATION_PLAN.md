# Pagination Implementation Plan

## Objective

Provide consistent, bounded, filter-safe pagination across Laravel APIs, the public and authenticated Laravel website, the gym and platform admin experiences, and the Member and Trainer Flutter apps.

Pagination must prevent unbounded database and network reads without hiding records, losing filters, duplicating mobile rows, or breaking gym, branch, trainer-relationship, and platform-admin scope.

## Shared contract

### Laravel JSON APIs

- Use length-aware pagination for user-facing collections.
- Keep endpoint-specific defaults, normally 15 or 20 records.
- Accept `page` and `per_page`; constrain `per_page` to `1..100`.
- Return collection rows in `data` and pagination state in `meta.pagination`.
- Preserve these metadata fields: `current_page`, `from`, `last_page`, `path`, `per_page`, `to`, and `total`.
- Never convert a paginated endpoint back into an unbounded `get()` in a client-facing controller.
- Keep deliberately small context payloads, dashboard previews, dropdown options, and static catalogs bounded without presenting them as paginated screens.

### Laravel web pages

- Use server-side paginators for full tables and grids.
- Preserve search, status, gym, branch, date, role, and sort filters in pagination links.
- Give every paginator a distinct page parameter when a page contains more than one paginated collection.
- Reset the affected page parameter when a filter changes.
- Keep compact dashboard previews deliberately limited and link them to the complete paginated screen.

### Flutter apps

- Use pull-to-refresh for page 1 and an explicit, guarded load-more action for subsequent pages.
- Read `meta.pagination`; do not infer completion only from row count.
- Block concurrent page requests.
- Append with stable-ID deduplication.
- Reset items, page, and completion state whenever search, filter, gym, branch, or coaching relationship changes.
- Preserve already-working cursor pagination for chat.
- Do not replace pagination with an arbitrarily large `per_page` request.

## Implementation phases

1. Inventory every list endpoint, Blade paginator, and Flutter collection consumer.
2. Classify each collection as a full user-facing list, compact preview, or bounded option/catalog list.
3. Standardize Laravel `per_page` bounds and response metadata.
4. Fix missing or conflicting pagination on gym-admin and platform-admin Blade screens.
5. Implement continuation state across Flutter admin gym and platform workspaces.
6. Implement continuation state across Member and Trainer app histories and operational lists.
7. Add backend contract tests and Flutter state/widget tests where practical.
8. Run PHP syntax, Pint, focused Laravel suites, Dart formatting and analysis, Flutter tests, and `git diff --check`.

## High-risk scenarios

- A filter changes after page 2 has been loaded.
- The same record moves between pages while new records arrive.
- A multi-gym user switches gym or branch during a request.
- A trainer switches between gym and independent coaching relationships.
- A platform administrator enters or leaves a replicated gym context.
- An API returns an empty page beyond `last_page`.
- Two paginators render on the same Blade page.
- Refresh and load-more are triggered concurrently.
- A collection has fewer rows than the default page size but more rows than a legacy hardcoded client limit.

## Acceptance criteria

- No full operational list silently stops at its first page.
- No full operational list relies on `per_page=100` as its only continuation strategy.
- Laravel rejects or safely caps abusive page sizes.
- Web pagination retains all active filters and does not move another table on the same page.
- Flutter refresh replaces data; load-more appends without duplicates.
- Loading, empty, error, retry, final-page, and scope-change states are visibly correct.
- Existing permissions and gym/branch/relationship scoping remain unchanged.
- Focused backend and Flutter validation passes.

## Implementation record

### Laravel APIs and web panels

- Added API-wide validation for `per_page`, `page`, and named `*_page` parameters.
- Paginated the platform Exercise Book API and web catalog at the database layer while preserving body-part filtering and stable ordering.
- Paginated Member diet plans, Trainer diet templates, and the Member exercise catalog to support their mobile continuation controls.
- Added distinct paginator names for announcements/notifications, payments/ledger, and subscriptions/ledger combinations.
- Preserved announcement filters and active Blade query strings.
- Added focused pagination contract coverage, including invalid limits, empty pages, populated later pages, grouped responses, ordering, and filter retention.

### Flutter gym and platform admin

- Confirmed the generic gym/platform collection state and payments/dues already append later pages.
- Added metadata-driven continuation to attendance history, trial requests, announcement history, notifications, gym audit logs, platform workout books, platform diet templates, and gym member diet plans.
- Reset paging on filter, search, gym, branch, and status changes.
- Kept form/dropdown option data deliberately bounded.

### Flutter Member app

- Added shared pagination metadata parsing and stable-ID merging.
- Added refresh/load-more behavior to attendance, gym discovery, saved gyms, notifications, gym invitations, independent-trainer invitations and relationships, diet plans, workout plans/catalog/exercises, assigned workouts, workout history/logbook, and progress weight/body/photo histories.
- Existing chat pagination remains unchanged.

### Flutter Trainer app

- Added the same shared metadata and merge behavior.
- Added continuation to assigned and independent members, notifications, trial requests, workout templates, workout plans, exercise lists, diet templates, and assigned-member attendance/notes/plans/logbook.
- Existing chat pagination remains unchanged.

### Intentional bounded exceptions

- Dashboard and context previews with explicit small limits.
- Form selectors and editor assignment pickers that are not presented as complete history screens.
- Static onboarding and notification-preference catalogs.
- Exports, which intentionally operate on the complete filtered query.
- Recommended-item previews with explicit product limits.
- Detail timelines whose UI explicitly describes a recent/capped snapshot.

## Second-pass audit closure

- Added continuation to the Trainer follow-up task screen; it previously
  requested a paginated endpoint but displayed only its first 20 records.
- Added Trainer member-detail diet-plan pagination and replaced the legacy
  `per_page=100` request with normal 20-record pages.
- Made the Member trial-request gym selector traverse every public-discovery
  page, so gyms after the first page remain selectable.
- Hardened legacy admin option/audit requests that use 100-record pages: the
  repository now follows pagination metadata to the final page and deduplicates
  stable IDs instead of silently dropping record 101 onward.
- Paginated Member and gym-admin global diet-template endpoints; their app
  selectors now traverse every page instead of relying on an unbounded query.
- Paginated the shared chat-conversation index and made both mobile apps merge
  all conversation pages while preserving cursor pagination for messages.
- Paginated the gym-admin Today Attendance feed, moved its filters into the
  database query, retained the full total, and added guarded load-more UI.
- Added continuation for Member exercise-specific workout history and changed
  personal-record collections from unbounded queries to paginated feeds.
- Added independent pagination metadata and continuation for personal records
  inside the Trainer assigned-member workout logbook.
- Replaced fixed 10/12/30-row Trainer progress snapshots with separately
  paginated weight, measurement, photo, and personal-record collections for
  both gym-assigned and independent coaching relationships.
- Kept the independent-coaching quick sheet as an explicit preview: it now
  requests only page one and uses pagination totals, instead of downloading
  every historical page merely to calculate its summary badges.
