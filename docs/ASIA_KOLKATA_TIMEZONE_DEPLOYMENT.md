# Asia/Kolkata Timezone Deployment

## Result

- Laravel and PHP date handling use `Asia/Kolkata`.
- Every Laravel MySQL/MariaDB connection sets its session timezone to `+05:30`.
- Existing MySQL/MariaDB `DATETIME` values are shifted once from UTC to IST by migration `2026_08_24_200000_convert_database_datetimes_to_asia_kolkata`.
- MySQL `TIMESTAMP` values are not rewritten because MySQL converts them automatically using the connection timezone.
- New events default to `Asia/Kolkata`.

## Production deployment

This migration updates existing date-time columns. Take a database backup and use a maintenance window.

```bash
cd /var/www/gym-atlas
git pull --ff-only origin main

cd backend_laravel
php artisan down

mysqldump --single-transaction --routines --triggers gymatlas > /root/gymatlas-before-ist.sql
```

Add or update these values in `backend_laravel/.env`:

```dotenv
APP_TIMEZONE=Asia/Kolkata
DB_TIMEZONE=+05:30
```

Then apply the coordinated configuration and data migration:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan queue:restart
systemctl reload php8.3-fpm
php artisan up
```

If the queue is managed by a named service, restart it after `queue:restart` as well:

```bash
systemctl restart gymatlas-queue
```

## Verification

```bash
php artisan tinker --execute="
dump([
    'app_timezone' => config('app.timezone'),
    'php_timezone' => date_default_timezone_get(),
    'database_config_timezone' => config('database.connections.'.config('database.default').'.timezone'),
    'laravel_now' => now()->toIso8601String(),
    'database_session' => Illuminate\Support\Facades\DB::selectOne('SELECT @@session.time_zone AS session_timezone, NOW() AS database_now'),
]);
"
```

Expected values:

- `app_timezone`: `Asia/Kolkata`
- `php_timezone`: `Asia/Kolkata`
- `database_config_timezone`: `+05:30`
- `database_session.session_timezone`: `+05:30`
- Laravel and database current times should represent the same IST wall-clock time.

Finally, create a draft event at a known time such as `16:00`, reload it in the Laravel panel, and confirm it still displays as `16:00`.

## Rollback

Do not manually subtract hours from selected tables. Roll back the migration while the application is in maintenance mode, restore the previous UTC configuration, clear/cache configuration, and restart workers. For a production incident, restoring the pre-deployment database backup is the safest rollback.
