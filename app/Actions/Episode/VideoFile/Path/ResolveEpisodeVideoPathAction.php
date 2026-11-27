<?php

namespace App\Actions\Episode\VideoFile\Path;

use App\Models\Episode\Episode;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveEpisodeVideoPathAction
{
    use AsAction;

    public function __construct(
        private readonly ResolveVideoBaseFolderAction $resolveVideoBaseFolderAction,
        private readonly ResolveShowFolderNameAction $resolveShowFolderNameAction,
    ) {}

    /**
     * Builds `{base}/{showId}_{showName}/{entryId}_{entryName}/{episodeId}_{episodeName}.{ext}`.
     * The overrides resolve the path an episode had before (or will have after) a rename or move.
     * Without a filename, the episode's directory is returned.
     */
    public function handle(
        Episode $episode,
        ?string $videoBaseFolder = null,
        ?string $showName = null,
        ?string $entryName = null,
        ?string $filename = null,
    ): string {
        $entry = $episode->entry;
        $show = $entry->show;

        $videoBaseFolder ??= $this->resolveVideoBaseFolderAction->handle();
        $showName ??= $this->resolveShowFolderNameAction->handle($show);
        $entryName ??= $entry->name;
        $filename ??= $episode->filename;

        $directory = implode('/', [
            rtrim($videoBaseFolder, '/\\'),
            $this->buildPathPart($show->id, $showName),
            $this->buildPathPart($entry->id, $entryName),
        ]);

        if ($filename === '') {
            return $directory;
        }

        $path = $directory.'/'.$this->buildPathPart($episode->id, $episode->name);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        if ($extension === '') {
            return $path;
        }

        return "{$path}.{$extension}";
    }

    private function buildPathPart(int $id, string $name): string
    {
        return $id.'_'.$this->sanitizeName($name);
    }

    /**
     * Keeps a name valid on Windows as well: the videos usually live on a Windows drive and a Windows vlc.exe opens
     * them.
     */
    private function sanitizeName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[\\\\\/:*?"<>|]+/', '-', $name) ?? '';
        $name = preg_replace('/\s+/', ' ', $name) ?? '';
        // Windows drops trailing dots and spaces when it opens a path, so a folder Linux created with one can't be
        // found
        $name = rtrim($name, ' .');

        return $name !== '' ? $name : 'untitled';
    }
}
