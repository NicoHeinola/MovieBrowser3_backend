<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

test('a user can register and receive a token', function () {
    $response = postJson('/api/v1/auth/register', [
        'name' => 'Ada Lovelace',
        'username' => 'adalovelace',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('message', 'Registered successfully.')
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.username', 'adalovelace');

    assertDatabaseHas('users', [
        'username' => 'adalovelace',
    ]);

    expect(PersonalAccessToken::count())->toBe(1);
});

test('registration rejects duplicate usernames', function () {
    User::factory()->create([
        'username' => 'adalovelace',
    ]);

    $response = postJson('/api/v1/auth/register', [
        'name' => 'Ada Lovelace',
        'username' => 'adalovelace',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['username']);
});

test('a user can log in and receive a token', function () {
    User::factory()->create([
        'username' => 'adalovelace',
        'password' => 'password123',
    ]);

    $response = postJson('/api/v1/auth/login', [
        'username' => 'adalovelace',
        'password' => 'password123',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('message', 'Authenticated successfully.')
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.username', 'adalovelace');

    expect(PersonalAccessToken::count())->toBe(1);
});

test('login rejects invalid credentials', function () {
    User::factory()->create([
        'username' => 'adalovelace',
        'password' => 'password123',
    ]);

    $response = postJson('/api/v1/auth/login', [
        'username' => 'adalovelace',
        'password' => 'wrong-password',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['username']);
});

test('an authenticated user can fetch their profile', function () {
    $user = User::factory()->create([
        'username' => 'adalovelace',
    ]);

    $token = $user->createToken('test-token')->plainTextToken;
    withToken($token);

    $response = getJson('/api/v1/auth/me');

    $response
        ->assertOk()
        ->assertJsonPath('user.username', 'adalovelace');
});

test('protected auth endpoints require authentication', function () {
    getJson('/api/v1/auth/me')->assertUnauthorized();
    postJson('/api/v1/auth/logout')->assertUnauthorized();
});

test('logout revokes the current token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    expect(PersonalAccessToken::count())->toBe(1);
    withToken($token);

    $response = postJson('/api/v1/auth/logout');

    $response
        ->assertOk()
        ->assertJsonPath('message', 'Logged out successfully.');

    expect(PersonalAccessToken::count())->toBe(0);
});

test('issued tokens expire based on sanctum configuration', function () {
    config()->set('sanctum.expiration', 5);
    app('auth')->forgetGuards();

    User::factory()->create([
        'username' => 'adalovelace',
        'password' => 'password123',
    ]);

    $loginResponse = postJson('/api/v1/auth/login', [
        'username' => 'adalovelace',
        'password' => 'password123',
    ]);

    $token = $loginResponse->json('token');

    expect($token)->not()->toBeEmpty();
    expect(PersonalAccessToken::first()?->expires_at)->not()->toBeNull();

    withToken($token);

    getJson('/api/v1/auth/me')->assertOk();

    Carbon::setTestNow(now()->addMinutes(6));
    app('auth')->forgetGuards();

    getJson('/api/v1/auth/me')->assertUnauthorized();

    Carbon::setTestNow();
});
