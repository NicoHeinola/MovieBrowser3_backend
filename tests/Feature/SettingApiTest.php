<?php

use App\Models\Setting\Setting;
use App\Models\User\User;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\withToken;

test('listing settings requires authentication', function () {
    getJson('/api/v1/settings')->assertUnauthorized();
});

test('any signed-in user can list settings', function () {
    Setting::factory()->count(2)->create();
    withToken(User::factory()->create()->createToken('test-token')->plainTextToken);

    $response = getJson('/api/v1/settings')
        ->assertOk()
        ->assertJsonCount(5 + 2, 'data'); // 5 seeded + 2 factory

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
    $setting = Setting::factory()->create(['type' => 'string', 'value' => 'old']);
    $token = $user->createToken('test-token')->plainTextToken;

    withToken($token);

    patchJson("/api/v1/settings/{$setting->key}", ['value' => 'foo'])->assertForbidden();
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

test('windows paths are rejected', function (string $key, string $path) {
    actingAsAdmin();

    patchJson("/api/v1/settings/{$key}", ['value' => $path])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['value']);
})->with([
    'vlc' => ['vlc_media_player_path', 'C:\\VLC\\vlc.exe'],
]);

test('the VLC path can be a Linux path, empty or the vlc that setup detects on the PATH', function (string $path) {
    actingAsAdmin();

    patchJson('/api/v1/settings/vlc_media_player_path', ['value' => $path])
        ->assertOk()
        ->assertJsonPath('value', $path);
})->with([
    'linux path' => '/mnt/c/Program Files/VideoLAN/VLC/vlc.exe',
    'empty' => '',
    'on the PATH' => 'vlc',
]);

test('the video base path is only changed by a relocation', function () {
    actingAsAdmin();
    $before = Setting::query()->find('video_base_path')->value;

    patchJson('/api/v1/settings/video_base_path', ['value' => '/somewhere/else'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.value.0.code', 'video_base_path_needs_relocation');

    expect(Setting::query()->find('video_base_path')->value)->toBe($before);
});

test('the VLC path must name vlc or vlc.exe', function (string $path) {
    actingAsAdmin();

    patchJson('/api/v1/settings/vlc_media_player_path', ['value' => $path])
        ->assertUnprocessable()
        ->assertExactJson([
            'code' => 'vlc_wrong_binary',
            'params' => ['path' => $path],
            'errors' => ['value' => [['code' => 'vlc_wrong_binary', 'params' => ['path' => $path]]]],
        ]);
})->with([
    'a shell' => '/bin/sh',
    'a script named like vlc' => '/usr/bin/vlc.sh',
]);
