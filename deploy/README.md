# Gym Atlas VPS Deployment

This workspace is intended to be deployed alongside the existing Talkee VPS, but as a separate app stack.

## Recommended Layout

- Repo root on server:
  - `/var/www/gym-atlas`
- Laravel app:
  - `/var/www/gym-atlas/backend_laravel`
- Realtime server:
  - `/var/www/gym-atlas/realtime_server`
- Public domain:
  - `gymatlas.example.com`
- Realtime subdomain:
  - `socket.gymatlas.example.com`

Do not mix Gym Atlas code into the Talkee app directory. Reuse the same VPS, but keep separate Nginx vhosts, separate process units, and separate `.env` files.

## Server Requirements

- PHP `8.3`
- Composer `2`
- Node.js `20+`
- npm
- MySQL or MariaDB
- Nginx
- `systemd`
- Redis recommended if you later move sessions/cache/queues from `database` to `redis`

## Laravel Deployment

From `/var/www/gym-atlas/backend_laravel`:

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan storage:link
npm install
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Set ownership and permissions:

```bash
chown -R www-data:www-data /var/www/gym-atlas/backend_laravel
find /var/www/gym-atlas/backend_laravel -type f -exec chmod 644 {} \;
find /var/www/gym-atlas/backend_laravel -type d -exec chmod 755 {} \;
chmod -R 775 /var/www/gym-atlas/backend_laravel/storage
chmod -R 775 /var/www/gym-atlas/backend_laravel/bootstrap/cache
```

## Laravel `.env` Notes

Minimum production values to review:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://gymatlas.example.com`
- `DB_CONNECTION=mysql`
- `DB_HOST=127.0.0.1`
- `DB_PORT=3306`
- `DB_DATABASE=...`
- `DB_USERNAME=...`
- `DB_PASSWORD=...`
- `QUEUE_CONNECTION=database`
- `CACHE_STORE=database`
- `SESSION_DRIVER=database`
- `GOOGLE_CLIENT_IDS=...`
- `FIREBASE_*` values

If Talkee already has Redis on the VPS, moving these to Redis is better:

- `QUEUE_CONNECTION=redis`
- `CACHE_STORE=redis`
- `SESSION_DRIVER=redis`

## Realtime Server Deployment

From `/var/www/gym-atlas/realtime_server`:

```bash
npm install
npm run build
```

Create `.env`:

```dotenv
NODE_ENV=production
PORT=4010
CLIENT_ORIGIN=https://gymatlas.in
LARAVEL_API_BASE_URL=https://gymatlas.in/api
SOCKET_INTERNAL_API_KEY=replace-with-strong-random-secret
TOKEN_VERIFICATION_STRATEGY=laravel
JWT_SHARED_SECRET=
USE_REDIS_ADAPTER=false
```

Set these matching values in Laravel:

```dotenv
REALTIME_SERVER_URL=http://127.0.0.1:4010
SOCKET_INTERNAL_API_KEY=replace-with-the-same-strong-random-secret
```

The realtime readiness check verifies both Laravel reachability and the shared internal key. Laravel queues REST fallback chat messages and read receipts for realtime publishing, so keep the queue worker running.

Build the member and trainer apps with the deployed realtime URL explicitly:

```bash
flutter build apk \
  --dart-define=API_BASE_URL=https://gymatlas.in/api \
  --dart-define=SOCKET_BASE_URL=https://your-realtime-domain.example.com
```

When `SOCKET_BASE_URL` is omitted, chat remains available through the durable Laravel API without realtime updates.

## Process Management

Install the provided `systemd` units:

- `deploy/systemd/gymatlas-queue.service`
- `deploy/systemd/gymatlas-realtime.service`

Laravel scheduler:

```bash
* * * * * cd /var/www/gym-atlas/backend_laravel && php artisan schedule:run >> /dev/null 2>&1
```

## Nginx

Use the templates in:

- `deploy/nginx/gymatlas.conf.example`
- `deploy/nginx/gymatlas-socket.conf.example`

Then:

```bash
ln -s /etc/nginx/sites-available/gymatlas.conf /etc/nginx/sites-enabled/gymatlas.conf
ln -s /etc/nginx/sites-available/gymatlas-socket.conf /etc/nginx/sites-enabled/gymatlas-socket.conf
nginx -t
systemctl reload nginx
```

## Release Update Flow

From repo root on server:

```bash
git pull origin main
cd backend_laravel
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
cd ../realtime_server
npm ci
npm run build
systemctl restart gymatlas-queue
systemctl restart gymatlas-realtime
systemctl reload nginx
```

Verify both application layers after the restart:

```bash
systemctl --no-pager --full status gymatlas-queue gymatlas-realtime
curl --fail --silent --show-error http://127.0.0.1:4010/health
curl --fail --silent --show-error http://127.0.0.1:4010/ready
php ../backend_laravel/artisan queue:monitor default --max=1000
```

`/ready` confirms that realtime can reach Laravel with the configured
`SOCKET_INTERNAL_API_KEY`. If it returns `503`, confirm that
`LARAVEL_API_BASE_URL`, `REALTIME_SERVER_URL`, and the matching internal key
were preserved in the two production `.env` files before restarting again.

## Notes About This Monorepo

These folders are source-only references and do not need to run on the VPS:

- `flutter_admin_app`
- `flutter_member_app`
- `flutter_trainer_app`
- `gym_flutter_core`
- `tailadmin-laravel-main`
- `yogalax-master`

Deploy only:

- `backend_laravel`
- `realtime_server`
