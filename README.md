# MovieBrowser3 Backend API

This repository is a Laravel 13 API backend. Web starter assets and Blade views have been removed so the application is focused on JSON endpoints only.

## Stack

- Laravel 13
- Laravel Sanctum for bearer-token authentication
- Laravel Actions for one-class-per-task application logic
- Pest for API tests

## Setup

```bash
composer setup
```

If you prefer to run the steps yourself:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

## Run

```bash
composer dev
```

The API is served by Laravel's built-in server.

## Authentication

Authentication uses Sanctum personal access tokens. These are bearer tokens, not JWTs.

Send the token on protected requests with the Authorization header:

```text
Authorization: Bearer <token>
```

## Endpoints

### Register

`POST /api/auth/register`

Request body:

```json
{
    "name": "Ada Lovelace",
    "email": "ada@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "device_name": "macbook"
}
```

### Login

`POST /api/auth/login`

Request body:

```json
{
    "email": "ada@example.com",
    "password": "password123",
    "device_name": "macbook"
}
```

### Current User

`GET /api/auth/me`

Requires a bearer token.

### Logout

`POST /api/auth/logout`

Requires a bearer token. This revokes the current access token.

## Actions

Auth endpoints are implemented as Laravel Actions under `app/Actions/Auth`.

This keeps each API task isolated:

- register user
- log in user
- return authenticated user
- revoke current token

## Tests

```bash
composer test
```
