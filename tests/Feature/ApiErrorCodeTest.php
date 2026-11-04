<?php

use App\Enums\VlcLaunchCode;
use App\Exceptions\VlcLaunchException;
use App\Models\Category\Category;
use App\Models\Episode\Episode;
use App\Models\Setting\Setting;
use App\Models\Show\Show;
use App\Models\ShowTitle\ShowTitle;
use App\Models\User\User;
use App\Models\WebsiteTag\WebsiteTag;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

test('a guest gets a code instead of a sentence', function () {
    getJson('/api/v1/shows')
        ->assertUnauthorized()
        ->assertExactJson(['code' => 'unauthenticated', 'params' => []]);
});

test('a default validation rule goes out as a code with no message', function () {
    actingAsAdmin();

    postJson('/api/v1/categories', ['value' => 'drama'])
        ->assertUnprocessable()
        ->assertExactJson([
            'code' => 'required',
            'params' => [],
            'errors' => ['name' => [['code' => 'required', 'params' => []]]],
        ]);
});

test('empty params encode as an object', function () {
    actingAsAdmin();

    $body = postJson('/api/v1/categories', ['value' => 'drama'])->getContent();

    expect($body)->toContain('"params":{}')->not->toContain('"params":[]');
});

test('max sends its limit and unique keeps its table out of the params', function () {
    actingAsAdmin();
    Category::factory()->create(['value' => 'drama']);

    postJson('/api/v1/categories', ['name' => str_repeat('a', 256), 'value' => 'drama'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'max')
        ->assertJsonPath('params.value', '255')
        ->assertJsonPath('errors.value.0.code', 'unique')
        ->assertJsonPath('errors.value.0.params', []);
});

test('a rule object goes out under its own code', function () {
    actingAsAdmin();
    WebsiteTag::factory()->create(['name' => 'Tools']);

    postJson('/api/v1/website-tags', ['name' => 'tools'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'unique_ignoring_case');

    $setting = Setting::query()->updateOrCreate(['key' => 'video_base_path'], ['value' => '/videos', 'type' => 'string']);

    patchJson("/api/v1/settings/{$setting->key}", ['value' => 'relative/path'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'absolute_path');
});

test('a wrong password sends a code', function () {
    config(['auth.login_wait_duration_ms' => 0]);
    $user = User::factory()->create();

    postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'invalid_credentials')
        ->assertJsonPath('errors.username.0.code', 'invalid_credentials');
});

test('deleting the only primary title sends a code', function () {
    actingAsAdmin();
    $title = ShowTitle::factory()->for(Show::factory())->primary()->create();

    deleteJson("/api/v1/titles/{$title->id}")
        ->assertUnprocessable()
        ->assertJsonPath('code', 'only_primary_title');
});

test('playing an episode without a video file sends a code', function () {
    actingAsAdmin();
    $episode = Episode::factory()->create();

    postJson("/api/v1/episodes/{$episode->id}/playback")
        ->assertUnprocessable()
        ->assertJsonPath('code', 'no_video_file');
});

test('a plain text error added by hand goes out as invalid', function () {
    actingAsAdmin();

    postJson('/api/v1/setup/checks', [])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'invalid');
});

test('a vlc launch failure renders its code and params, not its log message', function () {
    $response = (new VlcLaunchException('Log text.', VlcLaunchCode::VlcStartFailed, ['detail' => 'sh: not found']))->render();

    expect($response->getStatusCode())->toBe(503)
        ->and($response->getData(true))->toBe(['code' => 'vlc_start_failed', 'params' => ['detail' => 'sh: not found']]);
});
