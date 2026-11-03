<?php

use App\Actions\Episode\VideoFile\ResolveEpisodeVideoPathAction;
use App\Actions\EpisodePlayback\OpenVlcPlaylistAction;
use App\Dtos\EpisodePlayback\VlcConnectionData;
use App\Exceptions\VlcLaunchException;
use App\Jobs\TrackVlcPlaybackJob;
use App\Models\Episode\Episode;
use App\Models\Show\Show;
use App\Models\ShowEntry\ShowEntry;
use App\Models\ShowWatchProgress\ShowWatchProgress;
use App\Models\User\User;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

// The tracking job must not run for real; the tests only check that it is queued.
beforeEach(fn () => Queue::fake());

function fakeVlcConnection(): VlcConnectionData
{
    return new VlcConnectionData(port: 45678, password: 'secret');
}

function actingAsPlaybackUser(): User
{
    $user = User::factory()->create();
    withToken($user->createToken('test-token')->plainTextToken);

    return $user;
}

/**
 * @return list<Episode>
 */
function playableEpisodes(ShowEntry $entry, int $count): array
{
    return collect(range(1, $count))
        ->map(function (int $sequence) use ($entry): Episode {
            $episode = Episode::factory()->create(['show_entry_id' => $entry->id, 'sequence_number' => $sequence]);
            putVideoFile(ResolveEpisodeVideoPathAction::run($episode));

            return $episode;
        })
        ->all();
}

test('episode playback requires authentication', function () {
    $episode = Episode::factory()->create();

    postJson("/api/v1/episodes/{$episode->id}/playback")->assertUnauthorized();
});

test('a regular user can play an episode with its whole entry as the playlist', function () {
    config(['vlc.track_interval_seconds' => 45]);
    $user = actingAsPlaybackUser();
    $entry = ShowEntry::factory()->create();
    [$first, $second, $third] = playableEpisodes($entry, 3);

    OpenVlcPlaylistAction::mock()->shouldReceive('handle')->once()->with([
        ResolveEpisodeVideoPathAction::run($first),
        ResolveEpisodeVideoPathAction::run($second),
        ResolveEpisodeVideoPathAction::run($third),
    ], 1, 0)->andReturn(fakeVlcConnection());

    postJson("/api/v1/episodes/{$second->id}/playback")
        ->assertCreated()
        ->assertJsonPath('show_id', $entry->show_id)
        ->assertJsonPath('show_entry_id', $entry->id)
        ->assertJsonPath('episode_id', $second->id)
        ->assertJsonStructure(['show_id', 'show_entry_id', 'episode_id', 'position_seconds', 'duration_seconds', 'updated_at']);

    assertDatabaseHas('show_watch_progress', [
        'show_id' => $entry->show_id,
        'user_id' => $user->id,
        'show_entry_id' => $entry->id,
        'episode_id' => $second->id,
    ]);
    Queue::assertPushed(TrackVlcPlaybackJob::class, fn (TrackVlcPlaybackJob $job): bool => $job->user->is($user)
        && $job->vlc->port === 45678
        && $job->delay === 45);
});

test('episodes without a video file are left out of the playlist', function () {
    actingAsPlaybackUser();
    $entry = ShowEntry::factory()->create();
    $missing = Episode::factory()->create(['show_entry_id' => $entry->id, 'sequence_number' => 1]);
    [$second, $third] = playableEpisodes($entry, 2);
    $second->update(['sequence_number' => 2]);
    $third->update(['sequence_number' => 3]);

    OpenVlcPlaylistAction::mock()->shouldReceive('handle')->once()->with([
        ResolveEpisodeVideoPathAction::run($second),
        ResolveEpisodeVideoPathAction::run($third),
    ], 1, 0)->andReturn(fakeVlcConnection());

    postJson("/api/v1/episodes/{$third->id}/playback")->assertCreated();

    expect($missing->exists)->toBeTrue();
});

test('playing another entry of the same show replaces the progress', function () {
    $user = actingAsPlaybackUser();
    $show = Show::factory()->create();
    [$season1Episode] = playableEpisodes(ShowEntry::factory()->create(['show_id' => $show->id]), 1);
    $season2 = ShowEntry::factory()->create(['show_id' => $show->id]);
    [$season2Episode] = playableEpisodes($season2, 1);
    OpenVlcPlaylistAction::mock()->shouldReceive('handle')->twice()->andReturn(fakeVlcConnection());

    postJson("/api/v1/episodes/{$season1Episode->id}/playback")->assertCreated();
    postJson("/api/v1/episodes/{$season2Episode->id}/playback")->assertCreated();

    assertDatabaseCount('show_watch_progress', 1);
    assertDatabaseHas('show_watch_progress', [
        'show_id' => $show->id,
        'user_id' => $user->id,
        'show_entry_id' => $season2->id,
        'episode_id' => $season2Episode->id,
    ]);
});

test('replaying the same episode bumps the progress timestamp', function () {
    $user = actingAsPlaybackUser();
    [$episode] = playableEpisodes(ShowEntry::factory()->create(), 1);
    $progress = ShowWatchProgress::factory()->create(['user_id' => $user->id, 'episode_id' => $episode->id]);
    $progress->forceFill(['updated_at' => now()->subDay()])->saveQuietly();
    OpenVlcPlaylistAction::mock()->shouldReceive('handle')->once()->andReturn(fakeVlcConnection());

    postJson("/api/v1/episodes/{$episode->id}/playback")->assertCreated();

    expect($progress->fresh()->updated_at->isToday())->toBeTrue();
});

test('replaying the episode in progress resumes at the saved position', function () {
    $user = actingAsPlaybackUser();
    [$first, $second] = playableEpisodes(ShowEntry::factory()->create(), 2);
    ShowWatchProgress::factory()->create([
        'user_id' => $user->id,
        'episode_id' => $second->id,
        'position_seconds' => 754,
        'duration_seconds' => 1440,
    ]);
    OpenVlcPlaylistAction::mock()->shouldReceive('handle')->once()->with(Mockery::any(), 1, 754)->andReturn(fakeVlcConnection());

    postJson("/api/v1/episodes/{$second->id}/playback")
        ->assertCreated()
        ->assertJsonPath('position_seconds', 754)
        ->assertJsonPath('duration_seconds', 1440);
});

test('a position with an unknown length still resumes', function () {
    $user = actingAsPlaybackUser();
    [$episode] = playableEpisodes(ShowEntry::factory()->create(), 1);
    ShowWatchProgress::factory()->create(['user_id' => $user->id, 'episode_id' => $episode->id, 'position_seconds' => 60]);
    OpenVlcPlaylistAction::mock()->shouldReceive('handle')->once()->with(Mockery::any(), 0, 60)->andReturn(fakeVlcConnection());

    postJson("/api/v1/episodes/{$episode->id}/playback")->assertCreated();
});

test('playing from the start ignores and clears the saved position', function () {
    $user = actingAsPlaybackUser();
    [$episode] = playableEpisodes(ShowEntry::factory()->create(), 1);
    ShowWatchProgress::factory()->create([
        'user_id' => $user->id,
        'episode_id' => $episode->id,
        'position_seconds' => 754,
        'duration_seconds' => 1440,
    ]);
    OpenVlcPlaylistAction::mock()->shouldReceive('handle')->once()->with(Mockery::any(), 0, 0)->andReturn(fakeVlcConnection());

    postJson("/api/v1/episodes/{$episode->id}/playback", ['from_start' => true])
        ->assertCreated()
        ->assertJsonPath('position_seconds', null)
        ->assertJsonPath('duration_seconds', null);
});

test('playing another episode than the one in progress starts it from the beginning', function () {
    $user = actingAsPlaybackUser();
    [$first, $second] = playableEpisodes(ShowEntry::factory()->create(), 2);
    ShowWatchProgress::factory()->create([
        'user_id' => $user->id,
        'episode_id' => $first->id,
        'position_seconds' => 754,
        'duration_seconds' => 1440,
    ]);
    OpenVlcPlaylistAction::mock()->shouldReceive('handle')->once()->with(Mockery::any(), 1, 0)->andReturn(fakeVlcConnection());

    postJson("/api/v1/episodes/{$second->id}/playback")->assertCreated();

    assertDatabaseHas('show_watch_progress', [
        'user_id' => $user->id,
        'episode_id' => $second->id,
        'position_seconds' => null,
        'duration_seconds' => null,
    ]);
});

test('from_start must be a boolean', function () {
    actingAsPlaybackUser();
    [$episode] = playableEpisodes(ShowEntry::factory()->create(), 1);
    OpenVlcPlaylistAction::mock()->shouldNotReceive('handle');

    postJson("/api/v1/episodes/{$episode->id}/playback", ['from_start' => 'soon'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('from_start');
});

test('progress is tracked per user', function () {
    $other = User::factory()->create();
    $entry = ShowEntry::factory()->create();
    [$first, $second] = playableEpisodes($entry, 2);
    ShowWatchProgress::factory()->create(['user_id' => $other->id, 'episode_id' => $first->id]);
    OpenVlcPlaylistAction::mock()->shouldReceive('handle')->once()->andReturn(fakeVlcConnection());

    $user = actingAsPlaybackUser();
    postJson("/api/v1/episodes/{$second->id}/playback")->assertCreated();

    assertDatabaseHas('show_watch_progress', ['user_id' => $other->id, 'episode_id' => $first->id]);
    assertDatabaseHas('show_watch_progress', ['user_id' => $user->id, 'episode_id' => $second->id]);
});

test('an episode without a video file cannot be played', function () {
    actingAsPlaybackUser();
    $episode = Episode::factory()->create();
    OpenVlcPlaylistAction::mock()->shouldNotReceive('handle');

    postJson("/api/v1/episodes/{$episode->id}/playback")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('episode');

    assertDatabaseCount('show_watch_progress', 0);
});

test('a failed VLC launch keeps the previous progress', function () {
    $user = actingAsPlaybackUser();
    $entry = ShowEntry::factory()->create();
    [$first, $second] = playableEpisodes($entry, 2);
    ShowWatchProgress::factory()->create(['user_id' => $user->id, 'episode_id' => $first->id]);
    OpenVlcPlaylistAction::mock()->shouldReceive('handle')->andThrow(new VlcLaunchException('VLC did not start.'));

    postJson("/api/v1/episodes/{$second->id}/playback")
        ->assertStatus(503)
        ->assertJsonPath('message', 'VLC did not start.');

    Queue::assertNothingPushed();

    assertDatabaseHas('show_watch_progress', ['user_id' => $user->id, 'episode_id' => $first->id]);
});

test('deleting the current episode keeps the show progress', function () {
    $user = actingAsPlaybackUser();
    $episode = Episode::factory()->create();
    $progress = ShowWatchProgress::factory()->create(['user_id' => $user->id, 'episode_id' => $episode->id]);

    actingAsAdmin();
    deleteJson("/api/v1/episodes/{$episode->id}")->assertNoContent();

    expect($progress->fresh())
        ->not->toBeNull()
        ->episode_id->toBeNull()
        ->show_entry_id->toBe($episode->show_entry_id);
});

test('shows include the current user watch progress', function () {
    $other = User::factory()->create();
    $watched = Show::factory()->create();
    $unwatched = Show::factory()->create();
    $episode = Episode::factory()->create(['show_entry_id' => ShowEntry::factory()->create(['show_id' => $watched->id])->id]);
    ShowWatchProgress::factory()->create(['user_id' => $other->id, 'episode_id' => $episode->id]);

    // The show endpoints are admin-only.
    $user = actingAsAdmin();
    ShowWatchProgress::factory()->create(['user_id' => $user->id, 'episode_id' => $episode->id]);

    getJson("/api/v1/shows/{$watched->id}")
        ->assertOk()
        ->assertJsonPath('watch_progress.show_id', $watched->id)
        ->assertJsonPath('watch_progress.show_entry_id', $episode->show_entry_id)
        ->assertJsonPath('watch_progress.episode_id', $episode->id);

    getJson("/api/v1/shows/{$unwatched->id}")
        ->assertOk()
        ->assertJsonPath('watch_progress', null);
});

test('shows can be filtered to started ones and sorted by last watched', function () {
    $user = actingAsAdmin();
    $older = Show::factory()->create();
    $newer = Show::factory()->create();
    $unwatched = Show::factory()->create();

    foreach ([[$older, now()->subDay()], [$newer, now()]] as [$show, $watchedAt]) {
        $episode = Episode::factory()->create(['show_entry_id' => ShowEntry::factory()->create(['show_id' => $show->id])->id]);
        ShowWatchProgress::factory()->create(['user_id' => $user->id, 'episode_id' => $episode->id])
            ->forceFill(['updated_at' => $watchedAt])->saveQuietly();
    }

    // Another user's progress must not count.
    $otherEpisode = Episode::factory()->create(['show_entry_id' => ShowEntry::factory()->create(['show_id' => $unwatched->id])->id]);
    ShowWatchProgress::factory()->create(['episode_id' => $otherEpisode->id]);

    getJson('/api/v1/shows?filter[has_watch_progress]=1&sort=-last_watched_at')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$newer->id, $older->id]);

    getJson('/api/v1/shows?sort=-last_watched_at')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$newer->id, $older->id, $unwatched->id]);

    foreach (['0', 'false'] as $value) {
        getJson("/api/v1/shows?filter[has_watch_progress]={$value}")
            ->assertOk()
            ->assertJsonPath('data.*.id', [$unwatched->id]);
    }

    getJson('/api/v1/shows?filter[has_watch_progress]=true&sort=-last_watched_at')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$newer->id, $older->id]);
});
