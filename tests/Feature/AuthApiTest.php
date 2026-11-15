<?php

use App\Models\User\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

test('a user can register and receive a token', function () {
    $response = postJson('/api/v1/auth/register', [
        'username' => 'adalovelace',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response
        ->assertCreated()
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

    $response->assertNoContent();

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

test('a zero sanctum expiration issues tokens that do not expire', function () {
    config()->set('sanctum.expiration', 0);
    app('auth')->forgetGuards();

    User::factory()->create([
        'username' => 'adalovelace',
        'password' => 'password123',
    ]);

    $token = postJson('/api/v1/auth/login', [
        'username' => 'adalovelace',
        'password' => 'password123',
    ])->assertOk()->json('token');

    expect(PersonalAccessToken::first()?->expires_at)->toBeNull();

    withToken($token);

    getJson('/api/v1/auth/me')->assertOk();
});

test('login ignores the case of the username', function () {
    config(['auth.login_wait_duration_ms' => 0]);
    User::factory()->create(['username' => 'AdaLovelace', 'password' => 'password123']);

    postJson('/api/v1/auth/login', ['username' => 'adaLOVELACE', 'password' => 'password123'])
        ->assertOk()
        ->assertJsonPath('user.username', 'AdaLovelace');
});

test('login prefers the exact username when older accounts differ only by case', function () {
    config(['auth.login_wait_duration_ms' => 0]);
    User::factory()->create(['username' => 'ada', 'password' => 'first-password']);
    User::factory()->create(['username' => 'Ada', 'password' => 'second-password']);

    postJson('/api/v1/auth/login', ['username' => 'Ada', 'password' => 'second-password'])
        ->assertOk()
        ->assertJsonPath('user.username', 'Ada');
});

test('registration rejects a username that differs only by case', function () {
    User::factory()->create(['username' => 'admin2']);

    postJson('/api/v1/auth/register', ['username' => 'AdMIn2', 'password' => 'secret', 'password_confirmation' => 'secret'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.username.0.code', 'unique_ignoring_case');
});
