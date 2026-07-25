# Realtime Server

Socket.IO realtime service for the gym ecosystem.

## Features

- Sanctum or shared-JWT socket authentication
- Trainer-member 1:1 chat authorization
- Typing indicator
- Read receipts
- Presence tracking
- User notification rooms
- Gym and branch announcement rooms
- Internal publish endpoints for Laravel-triggered notification and announcement events
- Durable Laravel persistence with idempotent client message IDs
- Canonical trainer-member rooms shared with Laravel
- Liveness and Laravel dependency readiness endpoints

## Environment

Copy `.env.example` to `.env` and adjust:

- `PORT`
- `CLIENT_ORIGIN`
- `LARAVEL_API_BASE_URL`
- `SOCKET_INTERNAL_API_KEY`
- `TOKEN_VERIFICATION_STRATEGY=laravel|jwt`
- `JWT_SHARED_SECRET` when using shared JWT
- `USE_REDIS_ADAPTER=false` until a Redis adapter is installed and configured

## Run

```bash
npm run dev
```

## Internal publish endpoints

Protected with `x-internal-api-key: <SOCKET_INTERNAL_API_KEY>`.

- `POST /internal/notifications`
- `POST /internal/announcements`

These are intended for Laravel-side integration after a notification or announcement is created.

## Health

- `GET /health` checks the Node process.
- `GET /ready` checks that the realtime server can reach Laravel, which is required before accepting production chat traffic.
