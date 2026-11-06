<?php

namespace App\Actions\Episode\VideoFile;

use App\Models\Episode\Episode;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveEpisodeVideoPathAction
{
    use AsAction;

    public function __construct(
        private readonly ResolveVideoBasePathAction $resolveVideoBasePathAction,
        private readonly ResolveShowVideoFolderNameAction $resolveShowVideoFolderNameAction,
    ) {}

    /**
     * Builds `{base}/{showId}_{showName}/{entryId}_{entryName}/{episodeId}_{episodeName}.{ext}`.
     * The overrides resolve the path an episode had before (or will have after) a rename or move.
     * Without a filename, the episode's directory is returned.
     */
    public function handle(
        Episode $episode,
        ?string $basePath = null,
        ?string $showName = null,
        ?string $entryName = null,
        ?string $filename = null,
    ): string {
        $entry = $episode->entry;
        $show = $entry->show;

        $directory = implode('/', array_filter([
            rtrim($basePath ?? $this->resolveVideoBasePathAction->handle(), '/\\'),
            $show->id.'_'.$this->sanitizePathPart($showName ?? $this->resolveShowVideoFolderNameAction->handle($show)),
            $entry->id.'_'.$this->sanitizePathPart($entryName ?? $entry->name),
        ], fn (string $part): bool => $part !== ''));

        $resolvedFilename = $filename ?? $episode->filename;

        if ($resolvedFilename === '') {
            return $directory;
        }

        $episodeSegment = $episode->id.'_'.$this->sanitizePathPart($episode->name);
        $extension = pathinfo($resolvedFilename, PATHINFO_EXTENSION);

        return $extension === ''
            ? "{$directory}/{$episodeSegment}"
            : "{$directory}/{$episodeSegment}.{$extension}";
    }

    /**
     * Keeps a name valid on Windows as well: the videos usually live on a Windows drive and a Windows vlc.exe opens them.
     */
    private function sanitizePathPart(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[\\\\\/:*?"<>|]+/', '-', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        // Windows drops trailing dots and spaces when it opens a path, so a folder Linux created with one can't be found
        $value = rtrim($value, ' .');

        return $value !== '' ? $value : 'untitled';
    }
}
