# Multi-gym member context: audit and implementation

## Outcome

A member can keep active relationships with multiple gyms. Each gym relationship has its own branch, membership and assigned gym trainer. The Member app shows a selected gym workspace and lets the member switch workspaces without mixing gym-owned data. Independent trainer relationships remain separate and continue to coexist with every gym relationship.

No database migration is required for this change. `member_profiles` already enforces one profile per `(user_id, gym_id)`, which is the correct boundary for separate trainer assignments.

## Rules

1. A gym may create or update only its own member profile.
2. Gym A and Gym B may assign different trainers to the same user.
3. Replacing Gym A's trainer must not change Gym B's assignment.
4. The selected gym is sent as `X-Gym-Id` on Member app requests.
5. The backend accepts that gym only while the member has operational access; stale or forged values fall back to an accessible gym.
6. Gym-owned profile, membership, trainer, attendance, diet, exercise and workout data resolve against the selected gym.
7. Independent coaching plans and conversations remain available outside the gym selector and never replace a gym trainer.
8. Leaving one gym cancels only that gym profile and gym-owned active plans/sessions. The account becomes independent only after its final gym relationship ends.
9. Cancelled or expired gym relationships are not offered by the selector and cannot be used to access current gym data.

## API contract

`GET /api/member/context` now returns:

- `selected_gym_id`
- `has_multiple_gyms`
- `gym_relationships[]`

Each relationship contains its gym, branch, current operational membership, assigned trainer, profile ID and selected state.

Clients persist the chosen gym ID and send it using `X-Gym-Id`. The optional `gym_id` query parameter is supported as a compatibility fallback, but the header is the canonical app contract.

## Member app presentation

1. The dashboard displays a premium active-workspace card with gym, branch, plan and assigned trainer.
2. When more than one gym is active, the app bar and workspace card expose `Switch Gym`.
3. The centered selector lists every active gym relationship with branch, membership state and that gym's assigned trainer.
4. Selecting a gym stores the choice securely, updates the API header and reloads the dashboard modules in that scope.
5. On a later login or launch, the saved selection is restored. If it is no longer accessible, the context response supplies a valid fallback and the app replaces the stale selection.

## Implemented backend boundaries

- Member context and relationship resource
- Member profile and membership resolution
- Assigned gym trainer and gym chat resolution
- Attendance status/history
- Diet and workout plan listing/access
- Exercise availability and recommendations
- Workout session start, direct access and history
- Progress and step attribution
- Leave-gym lifecycle and remaining-gym status
- Per-gym trainer replacement isolation

## Verification matrix

| Scenario | Expected result |
| --- | --- |
| Member belongs to Gym A and Gym B | Both appear in the switcher |
| Select Gym A | Gym A profile, membership and trainer are returned |
| Select Gym B | Gym B profile, membership and trainer are returned |
| Gym A replaces its trainer | Gym B trainer remains unchanged |
| Gym A selected, request Gym B workout by ID | Request is rejected |
| Leave Gym B while Gym A remains | Gym B is cancelled; status remains `gym_member` |
| Leave final gym | Status becomes `independent_user` |
| Independent trainer also assigned | Independent scope remains separate and available |

## Deployment sequence

1. Deploy the Laravel changes.
2. Run `php artisan optimize:clear` and the normal production cache/build procedure.
3. Release the updated Member app.
4. Smoke-test one user with two active gym profiles and two different trainers.
5. Switch in both directions and verify trainer, plans, attendance and membership.
6. Cancel one relationship and confirm the other remains usable.

## Automated validation

- Full Laravel suite after the cross-surface hard audit: 276 tests, 2,685 assertions passed.
- Final high-risk integration suite: 48 tests, 489 assertions passed.
- Full Member app analysis: no issues found; 3 widget tests passed.
- Full Trainer app analysis: no issues found; 1 widget test passed.
- Laravel Vite production build passed.
- Realtime TypeScript production build passed.

## Hard-audit additions

- Member notification deep links switch to the notification's gym before opening gym chat, while independent chat preserves the selected gym.
- Member Workout, Progress and Trainer tabs are recreated when gym context changes, preventing stale state from the previous gym.
- Trainer app assignments use relationship identity instead of member ID alone, so gym and independent plans for the same person are not collapsed.
- Gym trainer progress, memberships, attendance, logbooks, plans and personal records are constrained to the trainer's exact gym and branch.
- Personal records use `coaching_scope_key`, separating the same exercise across gyms, branches, independent trainer relationships and member-self activity.
- Gym admin filters, exports, serialization and audit snapshots bind the selected gym profile rather than the legacy arbitrary `hasOne` profile.
- Platform admin API and user detail display every member relationship with gym, branch, trainer and activity state.
- Deactivating one gym relationship does not disable the shared user account while another relationship remains active.
- Member-role gym and branch middleware now requires an accessible profile, not only a historical pivot.
- Context discovery remains outside gym-scope middleware so a stale saved gym can recover; destructive actions using that stale scope remain forbidden.
- Invitation accept/reject is locked and revalidated to prevent concurrent duplicate enrollment.
