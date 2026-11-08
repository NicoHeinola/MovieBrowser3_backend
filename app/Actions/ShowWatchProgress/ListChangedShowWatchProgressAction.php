<?php

namespace App\Actions\ShowWatchProgress;

use App\Models\ShowWatchProgress\ShowWatchProgress;
use App\Models\User\User;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class ListChangedShowWatchProgressAction
{
    use AsAction;

    /**
     * The user's progress rows saved since $since that differ from the row last known for their show.
     *
     * @param  Collection<int, ShowWatchProgress>  $known  keyed by show id
     * @return Collection<int, ShowWatchProgress> keyed by show id
     */
    public function handle(User $user, DateTimeInterface $since, Collection $known): Collection
    {
        return ShowWatchProgress::query()
            ->where('user_id', $user->id)
            ->where('updated_at', '>=', $since)
            ->get()
            ->keyBy('show_id')
            ->reject(fn (ShowWatchProgress $progress): bool => $known->has($progress->show_id)
                && $this->version($known->get($progress->show_id)) === $this->version($progress));
    }

    /**
     * What a row tells the client; timestamps have whole seconds, so a second save within the same second still
     * differs by episode or position.
     *
     * @return array{int, int|null, int|null, int|null}
     */
    private function version(ShowWatchProgress $progress): array
    {
        return [$progress->show_entry_id, $progress->episode_id, $progress->position_seconds, $progress->updated_at?->getTimestamp()];
    }
}
