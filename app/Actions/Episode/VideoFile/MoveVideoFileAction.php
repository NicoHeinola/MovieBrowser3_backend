<?php

namespace App\Actions\Episode\VideoFile;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;

class MoveVideoFileAction
{
    use AsAction;

    public function __construct(
        private readonly PruneEmptyDirectoriesAction $pruneEmptyDirectoriesAction,
        private readonly ResolveVideoBasePathAction $resolveVideoBasePathAction,
    ) {}

    /**
     * Moves the file and prunes the emptied source folders up to `$fromBasePath`.
     * If the surrounding transaction rolls back, the file is moved back and the emptied
     * destination folders are pruned up to `$toBasePath`. Both default to the current base path.
     */
    public function handle(string $fromPath, string $toPath, ?string $fromBasePath = null, ?string $toBasePath = null): void
    {
        if ($fromPath === $toPath) {
            return;
        }

        if (!File::exists($fromPath)) {
            return;
        }

        $fromBasePath ??= $this->resolveVideoBasePathAction->handle();
        $toBasePath ??= $fromBasePath;

        File::ensureDirectoryExists(dirname($toPath));

        File::move($fromPath, $toPath);

        DB::afterRollBack(function () use ($fromPath, $toPath, $toBasePath): void {
            File::ensureDirectoryExists(dirname($fromPath));
            File::move($toPath, $fromPath);

            $this->pruneEmptyDirectoriesAction->handle(dirname($toPath), $toBasePath);
        });

        $this->pruneEmptyDirectoriesAction->handle(dirname($fromPath), $fromBasePath);
    }
}
