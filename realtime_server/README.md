# Realtime Server

Socket.IO realtime service for the gym ecosystem.

## Features

- Sanctum or shared-JWT socket authentication
- Trainer-member 1:1 chat authorization
- Read receipts
- Presence tracking
- User notification rooms
- Gym and branch announcement rooms
- Internal publish endpoints for Laravel-triggered chat, notification, and announcement events
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
- `POST /internal/chat/messages`
- `POST /internal/chat/read`

Laravel uses the chat endpoints to broadcast messages and read receipts created through REST fallback.

## Health

- `GET /health` checks the Node process.
- `GET /ready` verifies that the realtime server can reach Laravel and authenticate with the shared internal key.
