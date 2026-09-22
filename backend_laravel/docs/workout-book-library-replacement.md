# Replacing the workout-book library

`ReplaceWorkoutBookLibrarySeeder` is intentionally excluded from `DatabaseSeeder`. Running ordinary `db:seed` must not remove a member's workout history. The legacy five-book and advanced-book seeders are likewise no longer part of ordinary seeding.

The replacement requires at least 1,355 active, approved global exercises and a platform admin. It validates and builds 45 books (15 split styles at beginner, intermediate, and advanced levels) before deleting anything. Every eligible catalog exercise is placed in at least one book. If the catalog is incomplete, it stops without changing books or history.

After checking the target database, making a verified backup, and scheduling a maintenance window, run from `backend_laravel`:

```bash
CONFIRM_REPLACE_WORKOUT_BOOKS=DELETE_BOOK_LINKED_HISTORY php artisan db:seed --class=ReplaceWorkoutBookLibrarySeeder --force
```

This **permanently removes all existing workout books**, their templates, member plans adopted from them, shared copies of those plans, associated workout sessions and sets, linked personal records, and linked import rows. Unrelated member-created plans and their history remain. The database work is transactional: an error during creation rolls it back. No production data is changed merely by committing or deploying this code; the command must be run explicitly.

After execution, verify 45 books, 45 public templates, coverage of all active, approved global exercises, and the expected member-plan/history counts against the backup. Do not run the legacy `WorkoutBookSeeder` or `AdvancedWorkoutPlanSeeder` afterward; they create the old sample books.
