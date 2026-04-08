<?php

use App\Models\User\User;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\getJson;
use function Pest\Laravel\withToken;

beforeEach(function () {
    if (!app('router')->has('test.admin-only')) {
        Route::middleware(['auth:sanctum', 'admin'])->get('/api/test-admin-only', function () {
            return response()->json([
                'message' => 'Admin access granted.',
            ]);
        })->name('test.admin-only');
    }
});

test('the admin user migration seeds an admin account', function () {
    assertDatabaseHas('users', [
        'username' => env('ADMIN_USER_USERNAME', 'admin'),
        'is_admin' => true,
    ]);
});

test('admin middleware returns unauthorized for guests', function () {
    getJson('/api/test-admin-only')->assertUnauthorized();
});

test('admin middleware forbids authenticated non-admin users', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    withToken($token);

    getJson('/api/test-admin-only')
        ->assertForbidden()
        ->assertJsonPath('message', 'Forbidden.');
});

test('admin middleware allows authenticated admin users', function () {
    $user = User::factory()->admin()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    withToken($token);

    getJson('/api/test-admin-only')
        ->assertOk()
        ->assertJsonPath('message', 'Admin access granted.');
});
