<?php

use App\Actions\ShowWatchProgress\UpdateShowWatchProgressAction;
use App\Dtos\EpisodePlayback\VlcConnectionData;
use App\Jobs\TrackVlcPlaybackJob;
use App\Models\Episode\Episode;
use App\Models\ShowWatchProgress\ShowWatchProgress;
use App\Models\User\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    Queue::fake();
    config(['vlc.track_interval_seconds' => 45]);
});

function runTrackVlcPlaybackJob(User $user): void
{
    app()->call([new TrackVlcPlaybackJob($user, new VlcConnectionData(45678, 'secret')), 'handle']);
}

function fakeVlcStatus(string $state, ?string $filename): void
{
    Http::fake(['127.0.0.1:*' => Http::response([
        'state' => $state,
        'information' => ['category' => ['meta' => array_filter(['filename' => $filename])]],
    ])]);
}

function assertTrackingContinues(User $user): void
{
    Queue::assertPushed(TrackVlcPlaybackJob::class, fn (TrackVlcPlaybackJob $job): bool => $job->user->is($user)
        && $job->vlc->port === 45678
        && $job->delay === 45);
}

test('the playing episode becomes the user progress', function () {
    $user = User::factory()->create();
    $episode = Episode::factory()->create();
    fakeVlcStatus('playing', "{$episode->id}_{$episode->name}.mkv");

    runTrackVlcPlaybackJob($user);

    assertDatabaseHas('show_watch_progress', [
        'user_id' => $user->id,
        'show_id' => $episode->entry->show_id,
        'show_entry_id' => $episode->show_entry_id,
        'episode_id' => $episode->id,
    ]);
    assertTrackingContinues($user);
});

test('moving to the next episode in VLC updates the progress', function () {
    $user = User::factory()->create();
    $current = Episode::factory()->create();
    $next = Episode::factory()->create(['show_entry_id' => $current->show_entry_id]);
    ShowWatchProgress::factory()->create(['user_id' => $user->id, 'episode_id' => $current->id]);
    fakeVlcStatus('playing', "{$next->id}_{$next->name}.mkv");

    runTrackVlcPlaybackJob($user);

    assertDatabaseCount('show_watch_progress', 1);
    assertDatabaseHas('show_watch_progress', ['user_id' => $user->id, 'episode_id' => $next->id]);
});

test('a paused VLC keeps the saved progress', function () {
    $user = User::factory()->create();
    $saved = Episode::factory()->create();
    $paused = Episode::factory()->create(['show_entry_id' => $saved->show_entry_id]);
    ShowWatchProgress::factory()->create(['user_id' => $user->id, 'episode_id' => $saved->id]);
    fakeVlcStatus('paused', "{$paused->id}_{$paused->name}.mkv");

    runTrackVlcPlaybackJob($user);

    assertDatabaseHas('show_watch_progress', ['user_id' => $user->id, 'episode_id' => $saved->id]);
    assertTrackingContinues($user);
});

test('a file that is not one of our episodes is ignored', function (?string $filename) {
    $user = User::factory()->create();
    fakeVlcStatus('playing', $filename);

    runTrackVlcPlaybackJob($user);

    assertDatabaseCount('show_watch_progress', 0);
    assertTrackingContinues($user);
})->with([
    'no file' => [null],
    'no id prefix' => ['some movie.mkv'],
    'unknown id' => ['999999_deleted episode.mkv'],
]);

test('tracking stops once VLC is closed', function () {
    $user = User::factory()->create();
    Http::fake(['127.0.0.1:*' => Http::failedConnection()]);

    runTrackVlcPlaybackJob($user);

    Queue::assertNothingPushed();
});

test('a failed progress save keeps the tracking going', function () {
    $user = User::factory()->create();
    $episode = Episode::factory()->create();
    fakeVlcStatus('playing', "{$episode->id}_{$episode->name}.mkv");
    UpdateShowWatchProgressAction::mock()->shouldReceive('handle')->andThrow(new RuntimeException('Database unavailable'));

    expect(fn () => runTrackVlcPlaybackJob($user))->toThrow(RuntimeException::class, 'Database unavailable');

    assertTrackingContinues($user);
});

test('a status without a state is treated as not playing', function () {
    $user = User::factory()->create();
    Http::fake(['127.0.0.1:*' => Http::response(['version' => '3.0.24'])]);

    runTrackVlcPlaybackJob($user);

    assertDatabaseCount('show_watch_progress', 0);
    assertTrackingContinues($user);
});

test('the check interval never drops below 5 seconds', function (string $value, int $expected) {
    $_ENV['VLC_TRACK_INTERVAL_SECONDS'] = $_SERVER['VLC_TRACK_INTERVAL_SECONDS'] = $value;

    try {
        expect((require config_path('vlc.php'))['track_interval_seconds'])->toBe($expected);
    } finally {
        unset($_ENV['VLC_TRACK_INTERVAL_SECONDS'], $_SERVER['VLC_TRACK_INTERVAL_SECONDS']);
    }
})->with([
    'empty' => ['', 5],
    'zero' => ['0', 5],
    'too small' => ['2', 5],
    'normal' => ['15', 15],
]);
