<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

test('a user can register and receive a token', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('message', 'Registered successfully.')
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.email', 'ada@example.com');

    $this->assertDatabaseHas('users', [
        'email' => 'ada@example.com',
    ]);

    expect(PersonalAccessToken::count())->toBe(1);
});

test('registration rejects duplicate emails', function () {
    User::factory()->create([
        'email' => 'ada@example.com',
    ]);

    $response = $this->postJson('/api/auth/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('a user can log in and receive a token', function () {
    User::factory()->create([
        'email' => 'ada@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'ada@example.com',
        'password' => 'password123',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('message', 'Authenticated successfully.')
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.email', 'ada@example.com');

    expect(PersonalAccessToken::count())->toBe(1);
});

test('login rejects invalid credentials', function () {
    User::factory()->create([
        'email' => 'ada@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'ada@example.com',
        'password' => 'wrong-password',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('an authenticated user can fetch their profile', function () {
    $user = User::factory()->create([
        'email' => 'ada@example.com',
    ]);

    $token = $user->createToken('test-token')->plainTextToken;

    $response = $this
        ->withToken($token)
        ->getJson('/api/auth/me');

    $response
        ->assertOk()
        ->assertJsonPath('user.email', 'ada@example.com');
});

test('protected auth endpoints require authentication', function () {
    $this->getJson('/api/auth/me')->assertUnauthorized();
    $this->postJson('/api/auth/logout')->assertUnauthorized();
});

test('logout revokes the current token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    expect(PersonalAccessToken::count())->toBe(1);

    $response = $this
        ->withToken($token)
        ->postJson('/api/auth/logout');

    $response
        ->assertOk()
        ->assertJsonPath('message', 'Logged out successfully.');

    expect(PersonalAccessToken::count())->toBe(0);
});
