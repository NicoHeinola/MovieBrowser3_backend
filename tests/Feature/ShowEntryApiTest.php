<?php

use App\Enums\ShowEntryType;
use App\Models\Episode\Episode;
use App\Models\Setting\Setting;
use App\Models\Show\Show;
use App\Models\ShowEntry\ShowEntry;
use App\Models\User\User;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

test('show entry read endpoints require authentication', function () {
    global $videoBasePath;
    $show = Show::factory()->create();

    getJson("/api/v1/shows/{$show->id}/entries")->assertUnauthorized();
});

test('show entry write endpoints require admin', function () {
    global $videoBasePath;
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;
    withToken($token);

    $show = Show::factory()->create();

    postJson("/api/v1/shows/{$show->id}/entries", [])
        ->assertForbidden();
});

test('authenticated users can list show entries', function () {
    global $videoBasePath;
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;
    withToken($token);

    $show = Show::factory()->create();
    ShowEntry::factory()->for($show)->create(['name' => 'Season 1', 'type' => ShowEntryType::Season, 'sort_order' => 1]);
    ShowEntry::factory()->for($show)->tvSpecial()->create(['sort_order' => 2]);

    getJson("/api/v1/shows/{$show->id}/entries")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('authenticated users can view a single entry', function () {
    global $videoBasePath;
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;
    withToken($token);

    $show = Show::factory()->create();
    $entry = ShowEntry::factory()->for($show)->create([
        'name' => 'Season 1: Stone Wars',
        'type' => ShowEntryType::Season,
    ]);

    getJson("/api/v1/entries/{$entry->id}")
        ->assertOk()
        ->assertJsonPath('id', $entry->id)
        ->assertJsonPath('name', 'Season 1: Stone Wars')
        ->assertJsonPath('type', 'season');
});

test('an admin can create a show entry', function () {
    global $videoBasePath;
    actingAsAdmin();

    $show = Show::factory()->create();

    $response = postJson("/api/v1/shows/{$show->id}/entries", [
        'type' => 'season',
        'name' => 'Season 1',
        'sort_order' => 1,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('name', 'Season 1')
        ->assertJsonPath('type', 'season')
        ->assertJsonPath('show_id', $show->id)
        ->assertJsonPath('sort_order', 1);

    assertDatabaseHas('show_entries', [
        'show_id' => $show->id,
        'type' => 'season',
        'name' => 'Season 1',
        'sort_order' => 1,
    ]);
});

test('an admin can update a show entry', function () {
    global $videoBasePath;
    actingAsAdmin();

    $show = Show::factory()->create();
    $entry = ShowEntry::factory()->for($show)->create([
        'name' => 'Season 1',
        'type' => ShowEntryType::Season,
    ]);

    patchJson("/api/v1/entries/{$entry->id}", [
        'name' => 'Season 1: Stone Wars',
        'sort_order' => 5,
    ])
        ->assertOk()
        ->assertJsonPath('name', 'Season 1: Stone Wars')
        ->assertJsonPath('type', 'season')
        ->assertJsonPath('sort_order', 5);

    assertDatabaseHas('show_entries', [
        'id' => $entry->id,
        'name' => 'Season 1: Stone Wars',
        'sort_order' => 5,
    ]);
});

test('renaming a show entry relocates its episode files', function () {
    global $videoBasePath;
    actingAsAdmin();

    Setting::query()->whereKey('video_base_path')->update(['value' => $videoBasePath]);

    $show = Show::factory()->create();
    $entry = ShowEntry::factory()->for($show)->create(['name' => 'Season 1']);
    $episode = Episode::factory()->for($entry, 'entry')->create([
        'name' => 'Episode 1',
        'filename' => 'episode_1.mkv',
    ]);

    $oldPath = "$videoBasePath/{$show->id}_{$show->id}/{$entry->id}_Season 1/{$episode->id}_Episode 1/episode_1.mkv";
    $newPath = "$videoBasePath/{$show->id}_{$show->id}/{$entry->id}_Season 1 Stone Wars/{$episode->id}_Episode 1/episode_1.mkv";
    File::ensureDirectoryExists(dirname($oldPath));
    File::put($oldPath, 'video-bytes');

    patchJson("/api/v1/entries/{$entry->id}", [
        'name' => 'Season 1 Stone Wars',
    ])
        ->assertOk()
        ->assertJsonPath('name', 'Season 1 Stone Wars');

    expect(File::exists($oldPath))->toBeFalse();
    expect(File::exists($newPath))->toBeTrue();
});

test('an admin can delete a show entry', function () {
    global $videoBasePath;
    actingAsAdmin();

    $show = Show::factory()->create();
    $entry = ShowEntry::factory()->for($show)->create();

    deleteJson("/api/v1/entries/{$entry->id}")
        ->assertNoContent();

    assertDatabaseMissing('show_entries', ['id' => $entry->id]);
});

test('deleting a show entry cascades to its episodes', function () {
    global $videoBasePath;
    actingAsAdmin();

    Setting::query()->whereKey('video_base_path')->update(['value' => $videoBasePath]);

    $show = Show::factory()->create();
    $entry = ShowEntry::factory()->for($show)->create();
    $episodeOne = Episode::factory()->for($entry, 'entry')->create([
        'name' => 'Episode 1',
        'filename' => 'episode_1.mkv',
        'sequence_number' => 1,
    ]);
    $episodeTwo = Episode::factory()->for($entry, 'entry')->create([
        'name' => 'Episode 2',
        'filename' => 'episode_2.mkv',
        'sequence_number' => 2,
    ]);

    $pathOne = "$videoBasePath/{$show->id}_{$show->id}/{$entry->id}_{$entry->name}/{$episodeOne->id}_Episode 1/episode_1.mkv";
    $pathTwo = "$videoBasePath/{$show->id}_{$show->id}/{$entry->id}_{$entry->name}/{$episodeTwo->id}_Episode 2/episode_2.mkv";
    $episodeDirectoryOne = dirname($pathOne);
    $episodeDirectoryTwo = dirname($pathTwo);
    File::ensureDirectoryExists($episodeDirectoryOne);
    File::ensureDirectoryExists($episodeDirectoryTwo);
    File::put($pathOne, 'video-bytes-1');
    File::put($pathTwo, 'video-bytes-2');

    assertDatabaseCount('episodes', 2);

    deleteJson("/api/v1/entries/{$entry->id}")
        ->assertNoContent();

    assertDatabaseCount('episodes', 0);
    expect(File::exists($pathOne))->toBeFalse();
    expect(File::exists($pathTwo))->toBeFalse();
    expect(File::exists($episodeDirectoryOne))->toBeFalse();
    expect(File::exists($episodeDirectoryTwo))->toBeFalse();
});

test('creating a show entry validates type enum', function () {
    global $videoBasePath;
    actingAsAdmin();

    $show = Show::factory()->create();

    postJson("/api/v1/shows/{$show->id}/entries", [
        'type' => 'invalid_type',
        'name' => 'Season 1',
        'sort_order' => 1,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

test('creating a show entry requires name, type, and sort_order', function () {
    global $videoBasePath;
    actingAsAdmin();

    $show = Show::factory()->create();

    postJson("/api/v1/shows/{$show->id}/entries", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type', 'name', 'sort_order']);
});
