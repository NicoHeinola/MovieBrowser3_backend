<?php

namespace App\Actions\ShowWatchProgress;

use App\Actions\ShowEntryWatchStatus\ListChangedShowEntryWatchStatusesAction;
use App\Dtos\ShowEntryWatchStatus\WatchStatusChangeData;
use App\Models\ShowWatchProgress\ShowWatchProgress;
use App\Models\User\User;
use Generator;
use Illuminate\Support\Collection;
use Illuminate\Support\Sleep;
use Lorisleiva\Actions\Concerns\AsAction;

class StreamShowWatchProgressAction
{
    use AsAction;

    public function __construct(
        private readonly ListChangedShowWatchProgressAction $listChangedShowWatchProgressAction,
        private readonly ListChangedShowEntryWatchStatusesAction $listChangedShowEntryWatchStatusesAction,
    ) {}

    /**
     * Follows the user's progress and entry watch statuses: every `watch_progress.stream_poll_seconds` it yields what
     * changed since the last check (often nothing), whatever wrote it. Anything saved before the stream started is not
     * sent. Ends once the token is revoked or after `watch_progress.stream_lifetime_seconds`.
     *
     * @return Generator<int, array{progress: Collection<int, ShowWatchProgress>, statuses: Collection<int, WatchStatusChangeData>}>
     */
    public function handle(User $user, int $tokenId): Generator
    {
        $endsAt = now()->addSeconds((int) config('watch_progress.stream_lifetime_seconds'));

        // Timestamps have whole seconds, so rows saved earlier in this second are already known rather than sent.
        $since = now()->startOfSecond();

        // What the client counts as current: everything that exists when the stream opens.
        $knownProgress = $this->listChangedShowWatchProgressAction->handle($user, $since, collect());
        $knownStatuses = $this->listChangedShowEntryWatchStatusesAction->handle($user, collect());

        while (now()->lessThan($endsAt)) {
            Sleep::for((int) config('watch_progress.stream_poll_seconds'))->seconds();

            // Logging out deletes the token; a stream must not outlive it.
            if (!$user->tokens()->whereKey($tokenId)->exists()) {
                return;
            }

            $progress = $this->listChangedShowWatchProgressAction->handle($user, $since, $knownProgress);
            $statuses = $this->listChangedShowEntryWatchStatusesAction->handle($user, $knownStatuses);

            $knownProgress = $knownProgress->replace($progress);
            $knownStatuses = $knownStatuses->replace($statuses);

            yield ['progress' => $progress->values(), 'statuses' => $statuses->values()];
        }
    }
}
