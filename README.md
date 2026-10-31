# MovieBrowser3 Backend API

Laravel 13 JSON API backend for MovieBrowser3.

## Setup

```bash
composer setup
```

## Run

```bash
composer dev
```

## Auth

Uses Sanctum bearer tokens.

```text
Authorization: Bearer <token>
```

## Endpoints

- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/logout`
- `GET /api/v1/watch-progress/stream` (server-sent `watch-progress` events with the user's changed `ShowWatchProgress`)

## Test

```bash
composer test
```
