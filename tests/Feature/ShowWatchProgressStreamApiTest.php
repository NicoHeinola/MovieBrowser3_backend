<?php

use App\Actions\ShowWatchProgress\UpdateShowWatchProgressAction;
use App\Dtos\EpisodePlayback\VlcConnectionData;
use App\Dtos\ShowWatchProgress\WatchPositionData;
use App\Jobs\TrackVlcPlaybackJob;
use App\Models\Episode\Episode;
use App\Models\ShowWatchProgress\ShowWatchProgress;
use App\Models\User\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;

use function Pest\Laravel\getJson;
use function Pest\Laravel\withToken;

beforeEach(function () {
    Carbon::setTestNow('2026-10-31 20:14:00');
    Sleep::fake(syncWithCarbon: true);
    config([
        'watch_progress.stream_poll_seconds' => 2,
        'watch_progress.stream_heartbeat_seconds' => 25,
        'watch_progress.stream_lifetime_seconds' => 10,
    ]);
});

function actingAsStreamUser(): User
{
    $user = User::factory()->create();
    withToken($user->createToken('test-token')->plainTextToken);

    return $user;
}

/**
 * Runs the callback while the stream sleeps before its given poll (1 = first poll).
 */
function beforeStreamPoll(int $poll, Closure $callback): void
{
    $sleeps = 0;

    Sleep::whenFakingSleep(function () use (&$sleeps, $poll, $callback): void {
        if (++$sleeps === $poll) {
            $callback();
        }
    });
}

function streamWatchProgress(): string
{
    return getJson('/api/v1/watch-progress/stream')->assertOk()->streamedContent();
}

function watchProgressEvent(Episode $episode, string $updatedAt, ?int $positionSeconds = null): string
{
    $data = json_encode([
        'show_id' => $episode->entry->show_id,
        'show_entry_id' => $episode->show_entry_id,
        'episode_id' => $episode->id,
        'position_seconds' => $positionSeconds,
        'duration_seconds' => $positionSeconds === null ? null : 1440,
        'updated_at' => $updatedAt,
    ]);

    return "event: watch-progress\nid: {$updatedAt}\ndata: {$data}\n\n";
}

test('the watch progress stream requires authentication', function () {
    getJson('/api/v1/watch-progress/stream')->assertUnauthorized();
});

test('the stream is an unbuffered event stream that tells the client how soon to reconnect', function () {
    actingAsStreamUser();

    $response = getJson('/api/v1/watch-progress/stream')->assertOk();

    expect($response->headers->get('Content-Type'))->toStartWith('text/event-stream')
        ->and($response->headers->get('Cache-Control'))->toContain('no-cache')
        ->and($response->headers->get('X-Accel-Buffering'))->toBe('no')
        ->and($response->streamedContent())->toBe("retry: 3000\n\n");
});

test('progress saved while the stream is open is sent, and progress from before it is not', function () {
    $user = actingAsStreamUser();
    ShowWatchProgress::factory()->for($user)->create();
    $episode = Episode::factory()->create();

    beforeStreamPoll(2, fn () => UpdateShowWatchProgressAction::run($episode, $user));

    expect(streamWatchProgress())->toBe("retry: 3000\n\n".watchProgressEvent($episode, '2026-10-31T20:14:04.000000Z'));
});

test('vlc moving to the next episode sends the show again', function () {
    Queue::fake();
    $user = actingAsStreamUser();
    [$first, $second] = Episode::factory()->count(2)->sequence(['sequence_number' => 1], ['sequence_number' => 2])
        ->create(['show_entry_id' => Episode::factory()->create()->show_entry_id]);

    beforeStreamPoll(1, fn () => UpdateShowWatchProgressAction::run($first, $user));
    // The tracking job run by the queue worker finds VLC playing the second episode.
    beforeStreamPoll(3, function () use ($user, $second): void {
        Http::fake(['127.0.0.1:*' => Http::response([
            'state' => 'playing',
            'information' => ['category' => ['meta' => ['filename' => "{$second->id}_episode.mkv"]]],
        ])]);
        app()->call([new TrackVlcPlaybackJob($user, new VlcConnectionData(45678, 'secret')), 'handle']);
    });

    expect(streamWatchProgress())->toBe("retry: 3000\n\n"
        .watchProgressEvent($first, '2026-10-31T20:14:02.000000Z')
        .watchProgressEvent($second, '2026-10-31T20:14:06.000000Z'));
});

test('a second save of the same episode within the same second is not sent again', function () {
    $user = actingAsStreamUser();
    $episode = Episode::factory()->create();

    beforeStreamPoll(1, fn () => UpdateShowWatchProgressAction::run($episode, $user));
    beforeStreamPoll(2, function () use ($episode, $user): void {
        Carbon::setTestNow(now()->subSeconds(2));
        UpdateShowWatchProgressAction::run($episode, $user);
        Carbon::setTestNow(now()->addSeconds(2));
    });

    expect(streamWatchProgress())->toBe("retry: 3000\n\n".watchProgressEvent($episode, '2026-10-31T20:14:02.000000Z'));
});

test('a new position within the same second is sent', function () {
    $user = actingAsStreamUser();
    $episode = Episode::factory()->create();

    beforeStreamPoll(1, fn () => UpdateShowWatchProgressAction::run($episode, $user, new WatchPositionData(100, 1440)));
    beforeStreamPoll(2, function () use ($episode, $user): void {
        Carbon::setTestNow(now()->subSeconds(2));
        UpdateShowWatchProgressAction::run($episode, $user, new WatchPositionData(130, 1440));
        Carbon::setTestNow(now()->addSeconds(2));
    });

    expect(streamWatchProgress())->toBe("retry: 3000\n\n"
        .watchProgressEvent($episode, '2026-10-31T20:14:02.000000Z', 100)
        .watchProgressEvent($episode, '2026-10-31T20:14:02.000000Z', 130));
});

test('other users progress is not sent', function () {
    actingAsStreamUser();
    $otherUser = User::factory()->create();

    beforeStreamPoll(1, fn () => UpdateShowWatchProgressAction::run(Episode::factory()->create(), $otherUser));

    expect(streamWatchProgress())->toBe("retry: 3000\n\n");
});

test('an idle stream sends a heartbeat comment', function () {
    config(['watch_progress.stream_heartbeat_seconds' => 4]);
    actingAsStreamUser();

    // Polls at 2, 4, 6, 8 and 10 s: pings at 4 and 8 s.
    expect(streamWatchProgress())->toBe("retry: 3000\n\n: ping\n\n: ping\n\n");
});

test('the stream ends after its lifetime', function () {
    actingAsStreamUser();

    streamWatchProgress();

    Sleep::assertSleptTimes(5);
});

test('the stream ends once its token is deleted', function () {
    $user = actingAsStreamUser();

    beforeStreamPoll(1, fn () => $user->tokens()->delete());
    beforeStreamPoll(1, fn () => UpdateShowWatchProgressAction::run(Episode::factory()->create(), $user));

    expect(streamWatchProgress())->toBe("retry: 3000\n\n");
    Sleep::assertSleptTimes(1);
});
