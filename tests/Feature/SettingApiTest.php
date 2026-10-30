<?php

use App\Models\Episode\Episode;
use App\Models\Setting\Setting;
use App\Models\Show\Show;
use App\Models\ShowEntry\ShowEntry;
use App\Models\User\User;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\withToken;

test('any user can list settings', function () {
    Setting::factory()->count(2)->create();

    $response = getJson('/api/v1/settings')
        ->assertOk()
        ->assertJsonCount(4 + 2, 'data'); // 4 seeded + 2 factory

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

test('video base path must be absolute', function () {
    actingAsAdmin();

    patchJson('/api/v1/settings/video_base_path', ['value' => 'relative/videos'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('value');
});

test('default settings are seeded', function () {
    // This assumes migrations have run
    assertDatabaseHas('settings', ['key' => 'banner_default_videos', 'type' => 'json']);
    assertDatabaseHas('settings', ['key' => 'banner_default_backgrounds', 'type' => 'json']);
});

test('changing video base path relocates all episode files', function () {
    global $videoBasePath;

    actingAsAdmin();

    $oldBasePath = dirname(__DIR__, 2).'/storage/app/testing-video-old-base';
    $newBasePath = dirname(__DIR__, 2).'/storage/app/testing-video-new-base';

    File::deleteDirectory($oldBasePath);
    File::deleteDirectory($newBasePath);

    Setting::query()->whereKey('video_base_path')->update(['value' => $oldBasePath]);

    $show = Show::factory()->create();
    $entry = ShowEntry::factory()->for($show)->create(['name' => 'Season 1']);
    $episodeOne = Episode::factory()->for($entry, 'entry')->create([
        'name' => 'Episode 1',
        'filename' => 'episode_1.mkv',
    ]);
    $episodeTwo = Episode::factory()->for($entry, 'entry')->create([
        'name' => 'Episode 2',
        'filename' => 'episode_2.mkv',
    ]);

    $oldPathOne = "{$oldBasePath}/{$show->id}_{$show->id}/{$entry->id}_Season 1/{$episodeOne->id}_Episode 1.mkv";
    $oldPathTwo = "{$oldBasePath}/{$show->id}_{$show->id}/{$entry->id}_Season 1/{$episodeTwo->id}_Episode 2.mkv";
    $newPathOne = "{$newBasePath}/{$show->id}_{$show->id}/{$entry->id}_Season 1/{$episodeOne->id}_Episode 1.mkv";
    $newPathTwo = "{$newBasePath}/{$show->id}_{$show->id}/{$entry->id}_Season 1/{$episodeTwo->id}_Episode 2.mkv";

    File::ensureDirectoryExists(dirname($oldPathOne));
    File::ensureDirectoryExists(dirname($oldPathTwo));
    File::put($oldPathOne, 'video-1');
    File::put($oldPathTwo, 'video-2');

    patchJson('/api/v1/settings/video_base_path', ['value' => $newBasePath])
        ->assertOk()
        ->assertJsonPath('value', $newBasePath);

    expect(File::exists($oldPathOne))->toBeFalse();
    expect(File::exists($oldPathTwo))->toBeFalse();
    expect(File::exists($newPathOne))->toBeTrue();
    expect(File::exists($newPathTwo))->toBeTrue();

    File::deleteDirectory($oldBasePath);
    File::deleteDirectory($newBasePath);
});

test('changing an absolute video base path relocates episode files on disk', function () {
    actingAsAdmin();

    $oldBasePath = dirname(__DIR__, 2).'/storage/app/testing-video-old';
    $newBasePath = dirname(__DIR__, 2).'/storage/app/testing-video-new';

    File::deleteDirectory($oldBasePath);
    File::deleteDirectory($newBasePath);

    Setting::query()->whereKey('video_base_path')->update(['value' => $oldBasePath]);

    $show = Show::factory()->create();
    $entry = ShowEntry::factory()->for($show)->create(['name' => 'Season 1']);
    $episode = Episode::factory()->for($entry, 'entry')->create([
        'name' => 'Episode 1',
        'filename' => 'episode_1.mkv',
    ]);

    $oldPath = "{$oldBasePath}/{$show->id}_{$show->id}/{$entry->id}_Season 1/{$episode->id}_Episode 1.mkv";
    $newPath = "{$newBasePath}/{$show->id}_{$show->id}/{$entry->id}_Season 1/{$episode->id}_Episode 1.mkv";

    File::ensureDirectoryExists(dirname($oldPath));
    File::put($oldPath, 'video-1');

    patchJson('/api/v1/settings/video_base_path', ['value' => $newBasePath])
        ->assertOk()
        ->assertJsonPath('value', $newBasePath);

    expect(File::exists($oldPath))->toBeFalse();
    expect(File::exists($newPath))->toBeTrue();

    File::deleteDirectory($oldBasePath);
    File::deleteDirectory($newBasePath);
});

test('changing an empty video base path relocates files from the default location', function () {
    actingAsAdmin();

    $storagePath = dirname(__DIR__, 2).'/storage/app/testing-storage';
    $newBasePath = dirname(__DIR__, 2).'/storage/app/testing-video-new';

    File::deleteDirectory($storagePath);
    File::deleteDirectory($newBasePath);

    app()->useStoragePath($storagePath);

    Setting::query()->whereKey('video_base_path')->update(['value' => '']);

    $show = Show::factory()->create();
    $entry = ShowEntry::factory()->for($show)->create(['name' => 'Season 1']);
    $episode = Episode::factory()->for($entry, 'entry')->create([
        'name' => 'Episode 1',
        'filename' => 'episode_1.mkv',
    ]);

    $oldPath = "{$storagePath}/app/videos/{$show->id}_{$show->id}/{$entry->id}_Season 1/{$episode->id}_Episode 1.mkv";
    $newPath = "{$newBasePath}/{$show->id}_{$show->id}/{$entry->id}_Season 1/{$episode->id}_Episode 1.mkv";

    File::ensureDirectoryExists(dirname($oldPath));
    File::put($oldPath, 'video-1');

    patchJson('/api/v1/settings/video_base_path', ['value' => $newBasePath])
        ->assertOk();

    expect(File::exists($oldPath))->toBeFalse();
    expect(File::exists($newPath))->toBeTrue();

    File::deleteDirectory($storagePath);
    File::deleteDirectory($newBasePath);
});

test('changing the video base path keeps the old base folder and its parents', function () {
    actingAsAdmin();

    $parentPath = dirname(__DIR__, 2).'/storage/app/testing-video-parent';
    $oldBasePath = "{$parentPath}/old";
    $newBasePath = dirname(__DIR__, 2).'/storage/app/testing-video-new';

    File::deleteDirectory($parentPath);
    File::deleteDirectory($newBasePath);

    Setting::query()->whereKey('video_base_path')->update(['value' => $oldBasePath]);

    $show = Show::factory()->create();
    $entry = ShowEntry::factory()->for($show)->create(['name' => 'Season 1']);
    $episode = Episode::factory()->for($entry, 'entry')->create([
        'name' => 'Episode 1',
        'filename' => 'episode_1.mkv',
    ]);

    $oldPath = "{$oldBasePath}/{$show->id}_{$show->id}/{$entry->id}_Season 1/{$episode->id}_Episode 1.mkv";
    File::ensureDirectoryExists(dirname($oldPath));
    File::put($oldPath, 'video-1');

    patchJson('/api/v1/settings/video_base_path', ['value' => $newBasePath])
        ->assertOk();

    expect(File::exists($oldPath))->toBeFalse();
    expect(File::isDirectory($oldBasePath))->toBeTrue();

    File::deleteDirectory($parentPath);
    File::deleteDirectory($newBasePath);
});

test('windows absolute video base paths are accepted', function (string $path) {
    actingAsAdmin();

    patchJson('/api/v1/settings/video_base_path', ['value' => $path])
        ->assertOk()
        ->assertJsonPath('value', $path);
})->with([
    'backslash' => 'C:\\Videos',
    'forward slash' => 'C:/Videos',
]);

test('a failed base path relocation restores moved files and the old setting', function () {
    actingAsAdmin();

    $oldBasePath = dirname(__DIR__, 2).'/storage/app/testing-video-old';
    $newBasePath = dirname(__DIR__, 2).'/storage/app/testing-video-new';
    File::deleteDirectory($oldBasePath);
    File::deleteDirectory($newBasePath);

    Setting::query()->whereKey('video_base_path')->update(['value' => $oldBasePath]);

    $show = Show::factory()->create();
    $firstEntry = ShowEntry::factory()->for($show)->create(['name' => 'Season 1']);
    $secondEntry = ShowEntry::factory()->for($show)->create(['name' => 'Season 2']);
    $firstEpisode = Episode::factory()->for($firstEntry, 'entry')->create(['name' => 'Episode 1', 'filename' => 'ep1.mkv']);
    $secondEpisode = Episode::factory()->for($secondEntry, 'entry')->create(['name' => 'Episode 1', 'filename' => 'ep1.mkv']);
    $firstPath = putVideoFile("$oldBasePath/{$show->id}_{$show->id}/{$firstEntry->id}_Season 1/{$firstEpisode->id}_Episode 1.mkv", 'first');
    $secondPath = putVideoFile("$oldBasePath/{$show->id}_{$show->id}/{$secondEntry->id}_Season 2/{$secondEpisode->id}_Episode 1.mkv", 'second');

    // A regular file where the second episode's new folder must go makes that move fail after the first one succeeded.
    putVideoFile("$newBasePath/{$show->id}_{$show->id}/{$secondEntry->id}_Season 2", 'blocker');

    patchJson('/api/v1/settings/video_base_path', ['value' => $newBasePath])->assertServerError();

    expect(Setting::query()->find('video_base_path')->value)->toBe($oldBasePath);
    expect(File::get($firstPath))->toBe('first');
    expect(File::get($secondPath))->toBe('second');
    expect(File::exists("$newBasePath/{$show->id}_{$show->id}/{$firstEntry->id}_Season 1/{$firstEpisode->id}_Episode 1.mkv"))->toBeFalse();

    File::deleteDirectory($oldBasePath);
    File::deleteDirectory($newBasePath);
});
