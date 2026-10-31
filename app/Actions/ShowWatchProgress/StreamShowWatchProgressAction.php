<?php

namespace App\Actions\ShowWatchProgress;

use App\Models\ShowWatchProgress\ShowWatchProgress;
use App\Models\User\User;
use DateTimeInterface;
use Generator;
use Illuminate\Support\Collection;
use Illuminate\Support\Sleep;
use Lorisleiva\Actions\Concerns\AsAction;

class StreamShowWatchProgressAction
{
    use AsAction;

    /**
     * Follows the user's progress: every `watch_progress.stream_poll_seconds` it yields the progress rows that changed
     * since the last check (often none), whatever wrote them. Progress saved before the stream started is not sent.
     * Ends once the token is revoked or after `watch_progress.stream_lifetime_seconds`.
     *
     * @return Generator<int, Collection<int, ShowWatchProgress>>
     */
    public function handle(User $user, int $tokenId): Generator
    {
        $endsAt = now()->addSeconds((int) config('watch_progress.stream_lifetime_seconds'));

        // Timestamps have whole seconds, so rows saved earlier in this second are already known rather than sent.
        $since = now()->startOfSecond();
        $sent = $this->changedSince($user, $since)->map($this->version(...));

        while (now()->lessThan($endsAt)) {
            Sleep::for((int) config('watch_progress.stream_poll_seconds'))->seconds();

            // Logging out deletes the token; a stream must not outlive it.
            if (!$user->tokens()->whereKey($tokenId)->exists()) {
                return;
            }

            $changed = $this->changedSince($user, $since)
                ->reject(fn (ShowWatchProgress $progress): bool => $sent->get($progress->show_id) === $this->version($progress));

            $sent = $sent->replace($changed->map($this->version(...)));

            yield $changed->values();
        }
    }

    /**
     * @return Collection<int, ShowWatchProgress> keyed by show id
     */
    private function changedSince(User $user, DateTimeInterface $since): Collection
    {
        return ShowWatchProgress::query()
            ->where('user_id', $user->id)
            ->where('updated_at', '>=', $since)
            ->get()
            ->keyBy('show_id');
    }

    /**
     * What the client has been told about a show; a second save within the same second still differs by episode.
     *
     * @return array{int, int|null, int|null}
     */
    private function version(ShowWatchProgress $progress): array
    {
        return [$progress->show_entry_id, $progress->episode_id, $progress->updated_at?->getTimestamp()];
    }
}
