<?php

use App\Models\User\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

test('user management endpoints require authentication', function () {
    postJson('/api/v1/users', [])->assertUnauthorized();
    patchJson('/api/v1/users/1', [])->assertUnauthorized();
    deleteJson('/api/v1/users/1')->assertUnauthorized();
});

test('an admin can create a user', function () {
    actingAsAdmin();

    $response = postJson('/api/v1/users', [
        'username' => 'gracehopper',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'is_admin' => true,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('username', 'gracehopper')
        ->assertJsonPath('is_admin', true);

    assertDatabaseHas('users', [
        'username' => 'gracehopper',
        'is_admin' => true,
    ]);
});

test('a non admin cannot create a user', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    withToken($token);

    postJson('/api/v1/users', [
        'username' => 'gracehopper',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertForbidden();
});

test('user creation requires password confirmation', function () {
    actingAsAdmin();

    postJson('/api/v1/users', [
        'username' => 'gracehopper',
        'password' => 'password123',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

test('an authenticated user can update their own username and password', function () {
    $user = User::factory()->create([
        'username' => 'adalovelace',
        'password' => 'old-password',
    ]);
    $token = $user->createToken('test-token')->plainTextToken;

    withToken($token);

    $response = patchJson("/api/v1/users/{$user->id}", [
        'username' => 'ada-updated',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('username', 'ada-updated')
        ->assertJsonPath('is_admin', false);

    $user->refresh();

    expect(Hash::check('new-password', $user->password))->toBeTrue();
});

test('user update requires password confirmation when changing password', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    withToken($token);

    patchJson("/api/v1/users/{$user->id}", [
        'password' => 'new-password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

test('a non admin cannot update another user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    withToken($token);

    patchJson("/api/v1/users/{$otherUser->id}", [
        'username' => 'blocked-update',
    ])->assertForbidden();
});

test('a non admin cannot change admin access on update', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    withToken($token);

    patchJson("/api/v1/users/{$user->id}", [
        'is_admin' => true,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['is_admin']);
});

test('an admin can update another user', function () {
    actingAsAdmin();
    $user = User::factory()->create([
        'username' => 'linus',
        'is_admin' => false,
    ]);

    $response = patchJson("/api/v1/users/{$user->id}", [
        'username' => 'linus-updated',
        'is_admin' => true,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('username', 'linus-updated')
        ->assertJsonPath('is_admin', true);

    assertDatabaseHas('users', [
        'id' => $user->id,
        'username' => 'linus-updated',
        'is_admin' => true,
    ]);
});

test('an admin can delete another user', function () {
    actingAsAdmin();
    $user = User::factory()->create();

    deleteJson("/api/v1/users/{$user->id}")
        ->assertNoContent();

    assertDatabaseMissing('users', [
        'id' => $user->id,
    ]);
});

test('an admin cannot delete themselves', function () {
    $admin = actingAsAdmin();

    deleteJson("/api/v1/users/{$admin->id}")
        ->assertForbidden();

    assertDatabaseHas('users', [
        'id' => $admin->id,
    ]);
});

test('a non admin cannot delete a user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    withToken($token);

    deleteJson("/api/v1/users/{$otherUser->id}")
        ->assertForbidden();

    assertDatabaseHas('users', [
        'id' => $otherUser->id,
    ]);
});

test('an admin can update another user without sending is_admin', function () {
    actingAsAdmin();
    $user = User::factory()->create([
        'username' => 'original',
        'is_admin' => false,
    ]);

    patchJson("/api/v1/users/{$user->id}", [
        'username' => 'renamed',
    ])
        ->assertOk()
        ->assertJsonPath('username', 'renamed')
        ->assertJsonPath('is_admin', false);
});

test('user creation rejects a duplicate username', function () {
    actingAsAdmin();
    User::factory()->create(['username' => 'taken']);

    postJson('/api/v1/users', [
        'username' => 'taken',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['username']);
});

test('user update rejects a duplicate username', function () {
    actingAsAdmin();
    User::factory()->create(['username' => 'taken']);
    $user = User::factory()->create(['username' => 'original']);

    patchJson("/api/v1/users/{$user->id}", [
        'username' => 'taken',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['username']);
});
