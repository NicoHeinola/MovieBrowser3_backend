<?php

use App\Models\Setting\Setting;
use App\Models\User\User;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\withToken;

test('any user can list settings', function () {
    Setting::factory()->count(2)->create();

    $response = getJson('/api/v1/settings')
        ->assertOk()
        ->assertJsonCount(2 + 2, 'data'); // 2 seeded + 2 factory

    $data = $response->json('data');
    $keys = collect($data)->pluck('key')->all();
    $sortedKeys = $keys;

    sort($sortedKeys);

    expect($data[0])->toHaveKey('key');
    expect($keys)->toBe($sortedKeys);
    expect($keys)->toContain('banner_default_backgrounds', 'banner_default_videos');
});

test('setting update requires authentication', function () {
    patchJson('/api/v1/settings/any-key', ['value' => 'foo'])->assertUnauthorized();
});

test('setting update forbids non-admin users', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    withToken($token);

    patchJson('/api/v1/settings/any-key', ['value' => 'foo'])->assertForbidden();
});

test('an admin can update a string setting', function () {
    actingAsAdmin();
    $setting = Setting::factory()->create(['type' => 'string', 'value' => 'old']);

    patchJson("/api/v1/settings/{$setting->key}", ['value' => 'new'])
        ->assertOk()
        ->assertJsonPath('value', 'new');

    assertDatabaseHas('settings', ['key' => $setting->key, 'value' => 'new']);
});

test('an admin can update a number setting', function () {
    actingAsAdmin();
    $setting = Setting::factory()->create(['type' => 'number', 'value' => 123]);

    patchJson("/api/v1/settings/{$setting->key}", ['value' => 456])
        ->assertOk()
        ->assertJsonPath('value', 456);

    assertDatabaseHas('settings', ['key' => $setting->key, 'value' => '456']);
});

test('an admin can update a json setting', function () {
    actingAsAdmin();
    $setting = Setting::factory()->create(['type' => 'json', 'value' => ['a' => 1]]);

    patchJson("/api/v1/settings/{$setting->key}", ['value' => ['b' => 2]])
        ->assertOk()
        ->assertJsonPath('value', ['b' => 2]);

    assertDatabaseHas('settings', ['key' => $setting->key, 'value' => json_encode(['b' => 2])]);
});

test('validation fails for mismatched types', function () {
    actingAsAdmin();
    $numberSetting = Setting::factory()->create(['type' => 'number', 'value' => 123]);
    $jsonSetting = Setting::factory()->create(['type' => 'json', 'value' => []]);

    patchJson("/api/v1/settings/{$numberSetting->key}", ['value' => 'not-a-number'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('value');

    patchJson("/api/v1/settings/{$jsonSetting->key}", ['value' => 'not-an-array'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('value');
});

test('default settings are seeded', function () {
    // This assumes migrations have run
    assertDatabaseHas('settings', ['key' => 'banner_default_videos', 'type' => 'json']);
    assertDatabaseHas('settings', ['key' => 'banner_default_backgrounds', 'type' => 'json']);
});
