<?php

use App\Models\User\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

test('user management endpoints require authentication', function () {
    getJson('/api/v1/users')->assertUnauthorized();
    getJson('/api/v1/users/1')->assertUnauthorized();
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

test('an admin can list users', function () {
    actingAsAdmin();
    $user = User::factory()->create(['username' => 'adalovelace']);

    getJson('/api/v1/users?sort=-id')
        ->assertOk()
        ->assertJsonCount(User::count(), 'data')
        ->assertJsonPath('data.0.id', $user->id)
        ->assertJsonPath('data.0.username', 'adalovelace')
        ->assertJsonPath('data.0.is_admin', false)
        ->assertJsonMissingPath('data.0.password');
});

test('an admin can filter users by username and admin access', function () {
    actingAsAdmin();
    $ada = User::factory()->create(['username' => 'adalovelace']);
    User::factory()->create(['username' => 'gracehopper']);
    $adminAda = User::factory()->admin()->create(['username' => 'ada-admin']);

    getJson('/api/v1/users?filter[username]=ada&sort=id')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $ada->id)
        ->assertJsonPath('data.1.id', $adminAda->id);

    getJson('/api/v1/users?filter[username]=ada&filter[is_admin]=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $adminAda->id);
});

test('an admin can sort and paginate users', function () {
    actingAsAdmin();
    User::factory()->create(['username' => 'sorted-zed']);
    User::factory()->create(['username' => 'sorted-alice']);
    User::factory()->create(['username' => 'sorted-mallory']);

    getJson('/api/v1/users?filter[username]=sorted-&sort=username')
        ->assertOk()
        ->assertJsonPath('data.0.username', 'sorted-alice')
        ->assertJsonPath('data.1.username', 'sorted-mallory')
        ->assertJsonPath('data.2.username', 'sorted-zed');

    getJson('/api/v1/users?filter[username]=sorted-&sort=-username&page[size]=1&page[number]=2')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.username', 'sorted-mallory')
        ->assertJsonPath('meta.total', 3);

    getJson('/api/v1/users?filter[username]=sorted-&page[size]=0')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('an admin can view a user', function () {
    actingAsAdmin();
    $user = User::factory()->create(['username' => 'adalovelace']);

    getJson("/api/v1/users/{$user->id}")
        ->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('username', 'adalovelace')
        ->assertJsonPath('is_admin', false);
});

test('a non admin cannot list or view users', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    withToken($token);

    getJson('/api/v1/users')->assertForbidden();
    getJson("/api/v1/users/{$user->id}")->assertForbidden();
});
