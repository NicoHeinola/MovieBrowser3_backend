<?php

namespace App\Actions\EpisodePlayback;

use App\Actions\Episode\VideoFile\ResolveEpisodeVideoPathAction;
use App\Models\Episode\Episode;
use App\Models\ShowWatchProgress\ShowWatchProgress;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateEpisodePlaybackAction
{
    use AsAction;

    public function __construct(
        private readonly ResolveEpisodeVideoPathAction $resolveEpisodeVideoPathAction,
        private readonly OpenVlcPlaylistAction $openVlcPlaylistAction,
    ) {}

    /**
     * Opens every playable episode of the episode's entry in VLC starting at this one, then saves it as the user's
     * progress for its show. VLC opens before the transaction so its slow startup holds no lock, and a failed launch
     * leaves the previous progress untouched.
     */
    public function handle(Episode $episode, User $user): ShowWatchProgress
    {
        $entry = $episode->entry;

        $playlist = $entry->episodes
            ->map(fn (Episode $entryEpisode): array => [$entryEpisode->id, $this->resolveEpisodeVideoPathAction->handle($entryEpisode)])
            ->filter(fn (array $item): bool => File::isFile($item[1]))
            ->values();

        $this->openVlcPlaylistAction->handle(
            $playlist->pluck(1)->all(),
            $playlist->search(fn (array $item): bool => $item[0] === $episode->id),
        );

        return DB::transaction(function () use ($entry, $episode, $user): ShowWatchProgress {
            $progress = ShowWatchProgress::query()->updateOrCreate(
                ['show_id' => $entry->show_id, 'user_id' => $user->id],
                ['show_entry_id' => $entry->id, 'episode_id' => $episode->id],
            );

            // Replaying the same episode changes no column, but it still counts as the latest watch.
            $progress->touch();

            return $progress;
        });
    }
}
