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
  - `gymatlas.in`
- Realtime subdomain:
  - `socket.gymatlas.in`

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
- `APP_URL=https://gymatlas.in`
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

Chat push notifications require Firebase Admin credentials, not only the
Firebase project/web values used by OAuth. Download a service-account JSON for
the same Firebase project used by both mobile apps, store it outside the public
web root, and configure one of:

```dotenv
FIREBASE_SERVICE_ACCOUNT_PATH=/var/www/gym-atlas/shared/firebase-admin.json
FIREBASE_SERVICE_ACCOUNT_JSON=
```

The JSON value may alternatively contain the raw or base64-encoded service
account document. Never commit the credential. After changing it, rebuild
Laravel configuration and verify both credentials and registered app tokens:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan notifications:fcm-health
```

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
  --dart-define=SOCKET_BASE_URL=https://socket.gymatlas.in
```

The member, trainer, admin, shared Flutter configuration, and App Store build
script default to `https://socket.gymatlas.in`. A `SOCKET_BASE_URL` define can
still override it for non-production builds.

Build both signed iOS App Store IPAs from the repository root:

```bash
./scripts/build_app_store_ios.sh
```

The script verifies the realtime `/health` and `/ready` endpoints before
building. It also puts the system tools first in `PATH` so Xcode export uses
Apple's compatible `/usr/bin/rsync`. When App Store Connect requires a newer
build number, override it without changing the marketing version:

```bash
BUILD_NUMBER=3 ./scripts/build_app_store_ios.sh
```

`SOCKET_RESOLVE_IP` is available only as a temporary DNS-propagation override
for build-time health checks. It still validates HTTPS for
`socket.gymatlas.in`, and the apps continue to embed the domain rather than the
IP address.

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
certbot --nginx -d gymatlas.in -d www.gymatlas.in
certbot --nginx -d socket.gymatlas.in
```

Create DNS records pointing `gymatlas.in`, `www.gymatlas.in`, and
`socket.gymatlas.in` to the VPS before running Certbot. Verify the public
realtime endpoint before building a release:

```bash
curl --fail --silent --show-error https://socket.gymatlas.in/health
curl --fail --silent --show-error https://socket.gymatlas.in/ready
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
