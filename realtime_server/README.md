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
- Redis adapter integration placeholder

## Environment

Copy `.env.example` to `.env` and adjust:

- `PORT`
- `CLIENT_ORIGIN`
- `LARAVEL_API_BASE_URL`
- `SOCKET_INTERNAL_API_KEY`
- `TOKEN_VERIFICATION_STRATEGY=laravel|jwt`
- `JWT_SHARED_SECRET` when using shared JWT
- `USE_REDIS_ADAPTER=true|false`

## Run

```bash
npm run dev
```

## Internal publish endpoints

Protected with `x-internal-api-key: <SOCKET_INTERNAL_API_KEY>`.

- `POST /internal/notifications`
- `POST /internal/announcements`

These are intended for Laravel-side integration after a notification or announcement is created.
