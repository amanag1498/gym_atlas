# TailAdmin Migration Checklist

## Scope

- `backend_laravel`
- external source expected: `tailadmin-laravel-main`

## Current status

`tailadmin-laravel-main` is present in the workspace and its full frontend/view asset surface has been mirrored into `backend_laravel`.

## TailAdmin mirror now present in backend_laravel

The full TailAdmin source was mirrored locally before continuing admin changes:

- `public/tailadmin/**`
- `resources/tailadmin/css/app.css`
- `resources/tailadmin/js/**`
- `resources/views/tailadmin/**`

This mirror is now the local reference base for all admin-panel UI work.

## Current backend shell

These files currently define the shared legacy panel shell and should be replaced first:

- `resources/views/layouts/panel.blade.php`
- `resources/views/components/admin/app-layout.blade.php`
- `resources/views/components/admin/sidebar.blade.php`
- `resources/views/components/admin/topbar.blade.php`
- `resources/views/components/admin/alert.blade.php`
- `resources/views/components/admin/confirmation-modal.blade.php`
- `resources/js/app.js`
- `resources/js/admin.js`
- `resources/css/app.css`
- `resources/css/admin.css`

## Confirmed legacy shell coupling points

These are the concrete places that must be removed or rewritten during the shell swap:

- `resources/views/components/admin/app-layout.blade.php`
  - legacy favicon, fonts, CSS, JS, and old shell structure
  - inline panel-behavior bootstrapping that must stay TailAdmin-compatible
- `resources/views/web/partials/sidebar.blade.php`
  - legacy sidebar class structure and navigation wrappers
- `resources/views/web/partials/topbar.blade.php`
  - legacy mobile header classes and gym/admin toolbar styling
- `resources/css/app.css`
  - legacy toolbar and scope-control compatibility classes
- removed legacy asset bundle
  - old theme CSS
  - old icon fonts
  - old shell JS
  - old theme toggling JS

## Shared admin components

These should be migrated immediately after the shell so page templates can reuse TailAdmin primitives:

- `resources/views/components/admin/action-button.blade.php`
- `resources/views/components/admin/alert.blade.php`
- `resources/views/components/admin/confirmation-modal.blade.php`
- `resources/views/components/admin/empty-state.blade.php`
- `resources/views/components/admin/form-input.blade.php`
- `resources/views/components/admin/form-select.blade.php`
- `resources/views/components/admin/premium-card.blade.php`
- `resources/views/components/admin/stat-card.blade.php`
- `resources/views/components/admin/status-badge.blade.php`
- `resources/views/components/admin/table-wrapper.blade.php`

## View migration batches

### Batch 1: Dashboard surfaces

- `resources/views/web/admin/dashboard.blade.php`
- `resources/views/web/gym/dashboard.blade.php`

### Batch 2: Admin index/listing pages

- `resources/views/web/admin/announcements/index.blade.php`
- `resources/views/web/admin/audit-logs/index.blade.php`
- `resources/views/web/admin/banners/index.blade.php`
- `resources/views/web/admin/catalog/cities.blade.php`
- `resources/views/web/admin/exercises/index.blade.php`
- `resources/views/web/admin/facilities/index.blade.php`
- `resources/views/web/admin/fitness-goals/index.blade.php`
- `resources/views/web/admin/gym-owners/index.blade.php`
- `resources/views/web/admin/gyms/index.blade.php`
- `resources/views/web/admin/listings/index.blade.php`
- `resources/views/web/admin/listings/featured.blade.php`
- `resources/views/web/admin/listings/promoted.blade.php`
- `resources/views/web/admin/notifications/index.blade.php`
- `resources/views/web/admin/reports/index.blade.php`
- `resources/views/web/admin/settings/index.blade.php`
- `resources/views/web/admin/trainer-specializations/index.blade.php`
- `resources/views/web/admin/users/index.blade.php`
- `resources/views/web/admin/workout-books/index.blade.php`

### Batch 3: Gym index/listing pages

- `resources/views/web/gym/announcements/index.blade.php`
- `resources/views/web/gym/attendance/index.blade.php`
- `resources/views/web/gym/audit-logs/index.blade.php`
- `resources/views/web/gym/branches/index.blade.php`
- `resources/views/web/gym/custom-fees/index.blade.php`
- `resources/views/web/gym/custom-fees/audit-logs.blade.php`
- `resources/views/web/gym/members/index.blade.php`
- `resources/views/web/gym/membership-plans/index.blade.php`
- `resources/views/web/gym/memberships/index.blade.php`
- `resources/views/web/gym/notifications/index.blade.php`
- `resources/views/web/gym/payments/index.blade.php`
- `resources/views/web/gym/reminders/index.blade.php`
- `resources/views/web/gym/reports/index.blade.php`
- `resources/views/web/gym/settings/index.blade.php`
- `resources/views/web/gym/staff/index.blade.php`
- `resources/views/web/gym/trainers/index.blade.php`
- `resources/views/web/gym/trial-requests/index.blade.php`

### Batch 4: Form partials and wrappers

- `resources/views/web/admin/banners/_form.blade.php`
- `resources/views/web/admin/exercises/_form.blade.php`
- `resources/views/web/admin/facilities/_form.blade.php`
- `resources/views/web/admin/fitness-goals/_form.blade.php`
- `resources/views/web/admin/gym-owners/_form.blade.php`
- `resources/views/web/admin/gyms/_form.blade.php`
- `resources/views/web/admin/trainer-specializations/_form.blade.php`
- `resources/views/web/admin/workout-books/_form.blade.php`
- `resources/views/web/gym/branches/_form.blade.php`
- `resources/views/web/gym/members/_form.blade.php`
- `resources/views/web/gym/membership-plans/_form.blade.php`
- `resources/views/web/gym/staff/_form.blade.php`
- `resources/views/web/gym/trainers/_form.blade.php`

Then migrate all related `create.blade.php` and `edit.blade.php` wrappers.

### Batch 5: Detail pages

- `resources/views/web/admin/gym-owners/show.blade.php`
- `resources/views/web/admin/gyms/show.blade.php`
- `resources/views/web/admin/users/show.blade.php`
- `resources/views/web/gym/announcements/show.blade.php`
- `resources/views/web/gym/branches/show.blade.php`
- `resources/views/web/gym/members/show.blade.php`
- `resources/views/web/gym/membership-plans/show.blade.php`
- `resources/views/web/gym/payments/show.blade.php`
- `resources/views/web/gym/staff/show.blade.php`
- `resources/views/web/gym/trainers/show.blade.php`
- `resources/views/web/gym/trial-requests/show.blade.php`

### Batch 6: Auth and settings-adjacent pages

- `resources/views/web/auth/login.blade.php`
- `resources/views/web/gym/profile/edit.blade.php`
- `resources/views/web/gym/public-listing/edit.blade.php`
- `resources/views/web/gym/memberships/assign.blade.php`
- `resources/views/web/gym/memberships/custom-fee.blade.php`
- `resources/views/web/gym/payments/create.blade.php`
- `resources/views/web/gym/attendance/manual.blade.php`

## Execution order once TailAdmin source is available

1. Copy or reference `tailadmin-laravel-main` inside the workspace.
2. Inspect its main layout, sidebar, header, modal, form, table, auth, and asset entry points.
3. Replace the shared panel shell in `resources/views/components/admin`.
4. Adapt `resources/js/app.js`, `resources/js/admin.js`, `resources/css/app.css`, and `resources/css/admin.css` to TailAdmin assets and interactions.
5. Migrate shared admin components.
6. Migrate pages batch by batch in the order listed above.
7. Remove all legacy asset references only after all pages render correctly.

## Notes

- The current admin and gym panels share the same shell but have different navigation trees and topbar behavior.
- The gym panel includes scope switching for gym and branch selection, which must survive the TailAdmin layout swap.
- The admin panel includes an impersonation banner state that must remain visible after the shell migration.
