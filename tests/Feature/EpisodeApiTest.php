<?php

use App\Actions\Episode\VideoFile\ResolveEpisodeVideoPathAction;
use App\Actions\Episode\VideoFile\StoreVideoFileAction;
use App\Models\Episode\Episode;
use App\Models\Setting\Setting;
use App\Models\Show\Show;
use App\Models\ShowEntry\ShowEntry;
use App\Models\ShowTitle\ShowTitle;
use App\Models\User\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patch;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

$videoBasePath = videoBasePathForTests();

test('episode read endpoints require authentication', function () {
    global $videoBasePath;
    $entry = ShowEntry::factory()->create();

    getJson("/api/v1/show-entries/{$entry->id}/episodes")->assertUnauthorized();
});

test('episode write endpoints require admin', function () {
    global $videoBasePath;
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;
    withToken($token);

    $entry = ShowEntry::factory()->create();

    postJson("/api/v1/show-entries/{$entry->id}/episodes", [])
        ->assertForbidden();
});

test('authenticated users can list episodes for an entry', function () {
    global $videoBasePath;
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
    global $videoBasePath;
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;
    withToken($token);

    Setting::query()->whereKey('video_base_path')->update(['value' => $videoBasePath]);

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
        ->assertJsonPath('file_path', "$videoBasePath/{$show->id}_Dr. Stone/{$entry->id}_Season 1/{$episode->id}_Episode 1.mkv");
});

test('resolving preloaded episode video paths does not trigger extra queries', function () {
    global $videoBasePath;
    Setting::query()->whereKey('video_base_path')->update(['value' => $videoBasePath]);

    $show = Show::factory()->create();
    ShowTitle::factory()->for($show)->primary()->create(['title' => 'Dr. Stone']);

    $entry = ShowEntry::factory()->for($show)->create(['name' => 'Season 1']);
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

    $loadedShow = Show::query()
        ->with('titles', 'entries.episodes')
        ->findOrFail($show->id);

    $connection = DB::connection();
    $connection->enableQueryLog();
    $connection->flushQueryLog();

    $paths = $loadedShow->entries
        ->flatMap(fn (ShowEntry $loadedEntry) => $loadedEntry->episodes)
        ->map(fn (Episode $loadedEpisode) => ResolveEpisodeVideoPathAction::run($loadedEpisode))
        ->values()
        ->all();

    $queryLog = $connection->getQueryLog();
    $connection->disableQueryLog();

    expect($paths)->toBe([
        "$videoBasePath/{$show->id}_Dr. Stone/{$entry->id}_Season 1/{$episodeOne->id}_Episode 1.mkv",
        "$videoBasePath/{$show->id}_Dr. Stone/{$entry->id}_Season 1/{$episodeTwo->id}_Episode 2.mkv",
    ]);
    // The video base path is resolved once and reused for every episode.
    expect($queryLog)->toHaveCount(1);
});

test('an admin can create an episode', function () {
    global $videoBasePath;
    actingAsAdmin();

    Setting::query()->whereKey('video_base_path')->update(['value' => $videoBasePath]);

    $entry = ShowEntry::factory()->create();
    $video = UploadedFile::fake()->create('episode_1.mkv', 1);

    $response = post("/api/v1/show-entries/{$entry->id}/episodes", [
        'name' => 'Episode 1',
        'file' => $video,
        'sequence_number' => 1,
    ], [
        'Accept' => 'application/json',
    ]);

    $episodeId = $response->json('id');

    $response
        ->assertCreated()
        ->assertJsonPath('name', 'Episode 1')
        ->assertJsonPath('filename', 'episode_1.mkv')
        ->assertJsonPath('file_path', "$videoBasePath/{$entry->show_id}_{$entry->show_id}/{$entry->id}_{$entry->name}/{$episodeId}_Episode 1.mkv")
        ->assertJsonPath('sequence_number', 1);

    assertDatabaseHas('episodes', [
        'show_entry_id' => $entry->id,
        'name' => 'Episode 1',
        'filename' => 'episode_1.mkv',
        'sequence_number' => 1,
    ]);

    expect(File::exists("$videoBasePath/{$entry->show_id}_{$entry->show_id}/{$entry->id}_{$entry->name}/{$episodeId}_Episode 1.mkv"))->toBeTrue();
});

test('an admin can create an episode without uploading a file', function () {
    global $videoBasePath;
    actingAsAdmin();

    Setting::query()->whereKey('video_base_path')->update(['value' => $videoBasePath]);

    $entry = ShowEntry::factory()->create();

    postJson("/api/v1/show-entries/{$entry->id}/episodes", [
        'name' => 'Episode 1',
        'sequence_number' => 1,
    ])
        ->assertCreated()
        ->assertJsonPath('name', 'Episode 1')
        ->assertJsonPath('filename', '')
        ->assertJsonPath('sequence_number', 1);

    assertDatabaseHas('episodes', [
        'show_entry_id' => $entry->id,
        'name' => 'Episode 1',
        'filename' => '',
        'sequence_number' => 1,
    ]);
});

test('an admin can update an episode', function () {
    global $videoBasePath;
    actingAsAdmin();

    Setting::query()->whereKey('video_base_path')->update(['value' => $videoBasePath]);

    $entry = ShowEntry::factory()->create();
    $episode = Episode::factory()->for($entry, 'entry')->create([
        'name' => 'Episode 1',
        'filename' => 'ep1.mkv',
        'sequence_number' => 1,
    ]);

    $oldPath = "$videoBasePath/{$entry->show_id}_{$entry->show_id}/{$entry->id}_{$entry->name}/{$episode->id}_Episode 1.mkv";
    File::ensureDirectoryExists(dirname($oldPath));
    File::put($oldPath, 'old-video-bytes');

    $video = UploadedFile::fake()->create('ep1_revised.mkv', 1);

    patch("/api/v1/episodes/{$episode->id}", [
        'name' => 'Episode 1 - Revised',
        'file' => $video,
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('name', 'Episode 1 - Revised')
        ->assertJsonPath('filename', 'ep1_revised.mkv')
        ->assertJsonPath('file_path', "$videoBasePath/{$entry->show_id}_{$entry->show_id}/{$entry->id}_{$entry->name}/{$episode->id}_Episode 1 - Revised.mkv")
        ->assertJsonPath('sequence_number', 1);

    assertDatabaseHas('episodes', [
        'id' => $episode->id,
        'name' => 'Episode 1 - Revised',
        'filename' => 'ep1_revised.mkv',
    ]);

    expect(File::exists("$videoBasePath/{$entry->show_id}_{$entry->show_id}/{$entry->id}_{$entry->name}/{$episode->id}_Episode 1 - Revised.mkv"))->toBeTrue();
    expect(File::exists($oldPath))->toBeFalse();
});

test('renaming an episode without a new upload moves existing file to the new path', function () {
    global $videoBasePath;
    actingAsAdmin();

    Setting::query()->whereKey('video_base_path')->update(['value' => $videoBasePath]);

    $entry = ShowEntry::factory()->create();
    $episode = Episode::factory()->for($entry, 'entry')->create([
        'name' => 'Episode 1',
        'filename' => 'ep1.mkv',
        'sequence_number' => 1,
    ]);

    $oldPath = "$videoBasePath/{$entry->show_id}_{$entry->show_id}/{$entry->id}_{$entry->name}/{$episode->id}_Episode 1.mkv";
    $newPath = "$videoBasePath/{$entry->show_id}_{$entry->show_id}/{$entry->id}_{$entry->name}/{$episode->id}_Episode 1 - Renamed.mkv";
    File::ensureDirectoryExists(dirname($oldPath));
    File::put($oldPath, 'video-bytes');

    patchJson("/api/v1/episodes/{$episode->id}", [
        'name' => 'Episode 1 - Renamed',
    ])
        ->assertOk()
        ->assertJsonPath('name', 'Episode 1 - Renamed')
        ->assertJsonPath('filename', 'ep1.mkv')
        ->assertJsonPath('file_path', $newPath);

    expect(File::exists($oldPath))->toBeFalse();
    expect(File::exists($newPath))->toBeTrue();
});

test('an admin can delete an episode', function () {
    global $videoBasePath;
    actingAsAdmin();

    Setting::query()->whereKey('video_base_path')->update(['value' => $videoBasePath]);

    $entry = ShowEntry::factory()->create();
    $episode = Episode::factory()->for($entry, 'entry')->create([
        'name' => 'Episode 1',
        'filename' => 'episode_1.mkv',
        'sequence_number' => 1,
    ]);

    $path = "$videoBasePath/{$entry->show_id}_{$entry->show_id}/{$entry->id}_{$entry->name}/{$episode->id}_Episode 1.mkv";
    $episodeDirectory = dirname($path);
    File::ensureDirectoryExists($episodeDirectory);
    File::put($path, 'video-bytes');
    expect(File::exists($path))->toBeTrue();

    deleteJson("/api/v1/episodes/{$episode->id}")
        ->assertNoContent();

    assertDatabaseMissing('episodes', ['id' => $episode->id]);
    expect(File::exists($path))->toBeFalse();
    expect(File::exists($episodeDirectory))->toBeFalse();
});

test('creating an episode requires name, file, and sequence_number', function () {
    global $videoBasePath;
    actingAsAdmin();

    $entry = ShowEntry::factory()->create();

    postJson("/api/v1/show-entries/{$entry->id}/episodes", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'sequence_number']);
});

test('uploading a video stores its file size', function () {
    actingAsAdmin();
    useTestVideoBasePath();

    $episode = Episode::factory()->create(['filename' => '', 'has_video_file' => false]);

    patch("/api/v1/episodes/{$episode->id}", [
        'file' => UploadedFile::fake()->createWithContent('ep1.mkv', 'twelve bytes'),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('video_file_size', 12);
});

test('re-uploading a video with the same name and extension keeps the new file', function () {
    actingAsAdmin();
    $base = useTestVideoBasePath();

    $entry = ShowEntry::factory()->create(['name' => 'Season 1']);
    $episode = Episode::factory()->for($entry, 'entry')->create(['name' => 'Episode 1', 'filename' => 'ep1.mkv']);
    $path = putVideoFile("$base/{$entry->show_id}_{$entry->show_id}/{$entry->id}_Season 1/{$episode->id}_Episode 1.mkv", 'old-bytes');

    patch("/api/v1/episodes/{$episode->id}", [
        'file' => UploadedFile::fake()->createWithContent('ep1.mkv', 'new-bytes'),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('file_path', $path);

    expect(File::get($path))->toBe('new-bytes');
    expect(File::files(dirname($path)))->toHaveCount(1);
});

test('re-uploading a video with a different extension replaces the old file', function () {
    actingAsAdmin();
    $base = useTestVideoBasePath();

    $entry = ShowEntry::factory()->create(['name' => 'Season 1']);
    $episode = Episode::factory()->for($entry, 'entry')->create(['name' => 'Episode 1', 'filename' => 'ep1.mkv']);
    $directory = "$base/{$entry->show_id}_{$entry->show_id}/{$entry->id}_Season 1";
    $oldPath = putVideoFile("$directory/{$episode->id}_Episode 1.mkv", 'old-bytes');

    patch("/api/v1/episodes/{$episode->id}", [
        'file' => UploadedFile::fake()->createWithContent('ep1.mp4', 'new-bytes'),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('filename', 'ep1.mp4')
        ->assertJsonPath('file_path', "$directory/{$episode->id}_Episode 1.mp4");

    expect(File::exists($oldPath))->toBeFalse();
    expect(File::get("$directory/{$episode->id}_Episode 1.mp4"))->toBe('new-bytes');
});

test('uploading a video to an episode without one keeps sibling episode files', function () {
    actingAsAdmin();
    $base = useTestVideoBasePath();

    $entry = ShowEntry::factory()->create(['name' => 'Season 1']);
    $directory = "$base/{$entry->show_id}_{$entry->show_id}/{$entry->id}_Season 1";
    $sibling = Episode::factory()->for($entry, 'entry')->create(['name' => 'Episode 1', 'filename' => 'ep1.mkv']);
    $siblingPath = putVideoFile("$directory/{$sibling->id}_Episode 1.mkv", 'sibling-bytes');
    $episode = Episode::factory()->for($entry, 'entry')->create(['name' => 'Episode 2', 'filename' => '']);

    patch("/api/v1/episodes/{$episode->id}", [
        'file' => UploadedFile::fake()->createWithContent('ep2.mkv', 'new-bytes'),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('file_path', "$directory/{$episode->id}_Episode 2.mkv");

    expect(File::get("$directory/{$episode->id}_Episode 2.mkv"))->toBe('new-bytes');
    expect(File::get($siblingPath))->toBe('sibling-bytes');
});

test('deleting an episode keeps sibling episode files and their folder', function () {
    actingAsAdmin();
    $base = useTestVideoBasePath();

    $entry = ShowEntry::factory()->create(['name' => 'Season 1']);
    $directory = "$base/{$entry->show_id}_{$entry->show_id}/{$entry->id}_Season 1";
    $episode = Episode::factory()->for($entry, 'entry')->create(['name' => 'Episode 1', 'filename' => 'ep1.mkv']);
    $sibling = Episode::factory()->for($entry, 'entry')->create(['name' => 'Episode 2', 'filename' => 'ep2.mkv']);
    $path = putVideoFile("$directory/{$episode->id}_Episode 1.mkv");
    $siblingPath = putVideoFile("$directory/{$sibling->id}_Episode 2.mkv", 'sibling-bytes');

    deleteJson("/api/v1/episodes/{$episode->id}")->assertNoContent();

    expect(File::exists($path))->toBeFalse();
    expect(File::get($siblingPath))->toBe('sibling-bytes');
});

test('deleting the last episode prunes empty folders but keeps the base path', function () {
    actingAsAdmin();
    $base = useTestVideoBasePath();

    $entry = ShowEntry::factory()->create(['name' => 'Season 1']);
    $episode = Episode::factory()->for($entry, 'entry')->create(['name' => 'Episode 1', 'filename' => 'ep1.mkv']);
    putVideoFile("$base/{$entry->show_id}_{$entry->show_id}/{$entry->id}_Season 1/{$episode->id}_Episode 1.mkv");

    deleteJson("/api/v1/episodes/{$episode->id}")->assertNoContent();

    expect(File::isDirectory("$base/{$entry->show_id}_{$entry->show_id}"))->toBeFalse();
    expect(File::isDirectory($base))->toBeTrue();
});

test('deleting an episode whose video is already missing still succeeds', function () {
    actingAsAdmin();
    useTestVideoBasePath();

    $episode = Episode::factory()->create(['filename' => 'missing.mkv']);

    deleteJson("/api/v1/episodes/{$episode->id}")->assertNoContent();

    assertDatabaseMissing('episodes', ['id' => $episode->id]);
});

test('updating an episode without renaming leaves its video in place', function () {
    actingAsAdmin();
    $base = useTestVideoBasePath();

    $entry = ShowEntry::factory()->create(['name' => 'Season 1']);
    $episode = Episode::factory()->for($entry, 'entry')->create(['name' => 'Episode 1', 'filename' => 'ep1.mkv', 'sequence_number' => 1]);
    $path = putVideoFile("$base/{$entry->show_id}_{$entry->show_id}/{$entry->id}_Season 1/{$episode->id}_Episode 1.mkv");

    patchJson("/api/v1/episodes/{$episode->id}", ['sequence_number' => 5])
        ->assertOk()
        ->assertJsonPath('file_path', $path);

    expect(File::exists($path))->toBeTrue();
});

test('renaming an episode with slashes and extra spaces stores a safe file name', function () {
    actingAsAdmin();
    $base = useTestVideoBasePath();

    $entry = ShowEntry::factory()->create(['name' => 'Season 1']);
    $episode = Episode::factory()->for($entry, 'entry')->create(['name' => 'Episode 1', 'filename' => 'ep1.mkv']);
    $directory = "$base/{$entry->show_id}_{$entry->show_id}/{$entry->id}_Season 1";
    putVideoFile("$directory/{$episode->id}_Episode 1.mkv");

    patchJson("/api/v1/episodes/{$episode->id}", ['name' => '  Part 1/2 \\ the   End  '])
        ->assertOk()
        ->assertJsonPath('file_path', "$directory/{$episode->id}_Part 1-2 - the End.mkv");

    expect(File::exists("$directory/{$episode->id}_Part 1-2 - the End.mkv"))->toBeTrue();
});

test('uploaded file names cannot escape the episode folder', function () {
    actingAsAdmin();
    $base = useTestVideoBasePath();

    $entry = ShowEntry::factory()->create(['name' => 'Season 1']);

    $response = post("/api/v1/show-entries/{$entry->id}/episodes", [
        'name' => 'Episode 1',
        'file' => UploadedFile::fake()->createWithContent('../../../escape.mkv', 'bytes'),
        'sequence_number' => 1,
    ], ['Accept' => 'application/json'])->assertCreated();

    $expectedPath = "$base/{$entry->show_id}_{$entry->show_id}/{$entry->id}_Season 1/{$response->json('id')}_Episode 1.mkv";

    $response->assertJsonPath('filename', 'escape.mkv')->assertJsonPath('file_path', $expectedPath);
    expect(File::exists($expectedPath))->toBeTrue();
    expect(File::exists(dirname($base).'/escape.mkv'))->toBeFalse();
});

test('a rolled back re-upload restores the replaced video', function () {
    $base = useTestVideoBasePath();

    $entry = ShowEntry::factory()->create(['name' => 'Season 1']);
    $episode = Episode::factory()->for($entry, 'entry')->create(['name' => 'Episode 1', 'filename' => 'ep1.mkv']);
    $path = putVideoFile("$base/{$entry->show_id}_{$entry->show_id}/{$entry->id}_Season 1/{$episode->id}_Episode 1.mkv", 'old-bytes');

    expect(fn () => DB::transaction(function () use ($episode): void {
        app(StoreVideoFileAction::class)->handle($episode, UploadedFile::fake()->createWithContent('ep1.mkv', 'new-bytes'));

        throw new RuntimeException('Simulated failure');
    }))->toThrow(RuntimeException::class);

    expect(File::get($path))->toBe('old-bytes');
    expect(File::files(dirname($path)))->toHaveCount(1);
});

test('a rolled back first upload removes the stored video', function () {
    $base = useTestVideoBasePath();

    $entry = ShowEntry::factory()->create(['name' => 'Season 1']);
    $episode = Episode::factory()->for($entry, 'entry')->create(['name' => 'Episode 1', 'filename' => '']);

    expect(fn () => DB::transaction(function () use ($episode): void {
        app(StoreVideoFileAction::class)->handle($episode, UploadedFile::fake()->createWithContent('ep1.mkv', 'new-bytes'));

        throw new RuntimeException('Simulated failure');
    }))->toThrow(RuntimeException::class);

    expect(File::exists("$base/{$entry->show_id}_{$entry->show_id}/{$entry->id}_Season 1/{$episode->id}_Episode 1.mkv"))->toBeFalse();
    expect(File::isDirectory("$base/{$entry->show_id}_{$entry->show_id}"))->toBeFalse();
});
