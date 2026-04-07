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

## Test

```bash
composer test
```
