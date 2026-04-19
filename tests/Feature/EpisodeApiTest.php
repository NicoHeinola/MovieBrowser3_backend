<?php

use App\Models\Episode\Episode;
use App\Models\Show\Show;
use App\Models\ShowEntry\ShowEntry;
use App\Models\ShowTitle\ShowTitle;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

test('episode read endpoints require authentication', function () {
    $entry = ShowEntry::factory()->create();

    getJson("/api/v1/show-entries/{$entry->id}/episodes")->assertUnauthorized();
});

test('episode write endpoints require admin', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;
    withToken($token);

    $entry = ShowEntry::factory()->create();

    postJson("/api/v1/show-entries/{$entry->id}/episodes", [])
        ->assertForbidden();
});

test('authenticated users can list episodes for an entry', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;
    withToken($token);

    $entry = ShowEntry::factory()->create();
    Episode::factory()->for($entry, 'entry')->create(['sequence_number' => 1]);
    Episode::factory()->for($entry, 'entry')->create(['sequence_number' => 2]);

    getJson("/api/v1/show-entries/{$entry->id}/episodes")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('authenticated users can view a single episode with file_path', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;
    withToken($token);

    $show = Show::factory()->create();
    ShowTitle::factory()->for($show)->primary()->create(['title' => 'Dr. Stone']);

    $entry = ShowEntry::factory()->for($show)->create(['name' => 'Season 1']);
    $episode = Episode::factory()->for($entry, 'entry')->create([
        'name' => 'Episode 1',
        'filename' => 'episode_1.mkv',
        'sequence_number' => 1,
    ]);

    getJson("/api/v1/episodes/{$episode->id}")
        ->assertOk()
        ->assertJsonPath('id', $episode->id)
        ->assertJsonPath('name', 'Episode 1')
        ->assertJsonPath('filename', 'episode_1.mkv')
        ->assertJsonPath('sequence_number', 1)
        ->assertJsonPath('file_path', 'Dr. Stone/Season 1/episode_1.mkv');
});

test('preloaded episode file_path values do not trigger extra queries', function () {
    $show = Show::factory()->create();
    ShowTitle::factory()->for($show)->primary()->create(['title' => 'Dr. Stone']);

    $entry = ShowEntry::factory()->for($show)->create(['name' => 'Season 1']);
    Episode::factory()->for($entry, 'entry')->create([
        'filename' => 'episode_1.mkv',
        'sequence_number' => 1,
    ]);
    Episode::factory()->for($entry, 'entry')->create([
        'filename' => 'episode_2.mkv',
        'sequence_number' => 2,
    ]);

    $loadedShow = Show::query()
        ->with('titles', 'entries.episodes')
        ->findOrFail($show->id);

    $connection = DB::connection();
    $connection->enableQueryLog();
    $connection->flushQueryLog();

    $paths = $loadedShow->entries
        ->flatMap(fn (ShowEntry $loadedEntry) => $loadedEntry->episodes)
        ->map(fn (Episode $loadedEpisode) => $loadedEpisode->file_path)
        ->values()
        ->all();

    $queryLog = $connection->getQueryLog();
    $connection->disableQueryLog();

    expect($paths)->toBe([
        'Dr. Stone/Season 1/episode_1.mkv',
        'Dr. Stone/Season 1/episode_2.mkv',
    ]);
    expect($queryLog)->toHaveCount(0);
});

test('an admin can create an episode', function () {
    actingAsAdmin();

    $entry = ShowEntry::factory()->create();

    $response = postJson("/api/v1/show-entries/{$entry->id}/episodes", [
        'name' => 'Episode 1',
        'filename' => 'episode_1.mkv',
        'sequence_number' => 1,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('name', 'Episode 1')
        ->assertJsonPath('filename', 'episode_1.mkv')
        ->assertJsonPath('sequence_number', 1);

    assertDatabaseHas('episodes', [
        'show_entry_id' => $entry->id,
        'name' => 'Episode 1',
        'filename' => 'episode_1.mkv',
        'sequence_number' => 1,
    ]);
});

test('an admin can update an episode', function () {
    actingAsAdmin();

    $entry = ShowEntry::factory()->create();
    $episode = Episode::factory()->for($entry, 'entry')->create([
        'name' => 'Episode 1',
        'filename' => 'ep1.mkv',
        'sequence_number' => 1,
    ]);

    patchJson("/api/v1/episodes/{$episode->id}", [
        'name' => 'Episode 1 - Revised',
        'filename' => 'ep1_revised.mkv',
    ])
        ->assertOk()
        ->assertJsonPath('name', 'Episode 1 - Revised')
        ->assertJsonPath('filename', 'ep1_revised.mkv')
        ->assertJsonPath('sequence_number', 1);

    assertDatabaseHas('episodes', [
        'id' => $episode->id,
        'name' => 'Episode 1 - Revised',
        'filename' => 'ep1_revised.mkv',
    ]);
});

test('an admin can delete an episode', function () {
    actingAsAdmin();

    $entry = ShowEntry::factory()->create();
    $episode = Episode::factory()->for($entry, 'entry')->create(['sequence_number' => 1]);

    deleteJson("/api/v1/episodes/{$episode->id}")
        ->assertNoContent();

    assertDatabaseMissing('episodes', ['id' => $episode->id]);
});

test('creating an episode requires name, filename, and sequence_number', function () {
    actingAsAdmin();

    $entry = ShowEntry::factory()->create();

    postJson("/api/v1/show-entries/{$entry->id}/episodes", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'filename', 'sequence_number']);
});
