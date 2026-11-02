<?php

namespace App\Actions\Episode\VideoFile;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

class CopyVideoFileAction
{
    use AsAction;

    /** Space left free on the target disk after a copy, so a full disk fails the copy instead of the system */
    public const RESERVED_DISK_BYTES = 1024 ** 3;

    public function __construct(
        private readonly PruneEmptyDirectoriesAction $pruneEmptyDirectoriesAction,
        private readonly ResolveFreeDiskSpaceAction $resolveFreeDiskSpaceAction,
        private readonly ResolveVideoBasePathAction $resolveVideoBasePathAction,
    ) {}

    /**
     * Copies a video and returns its size. The source is only ever read. The copy is written next to the target
     * as `.part` and renamed once its size matches, so a target never holds a half-written file. If the surrounding
     * transaction rolls back, the copy is deleted and the emptied target folders are pruned up to the base path.
     */
    public function handle(string $fromPath, string $toPath): int
    {
        $partPath = "{$toPath}.part";

        if (!File::isFile($fromPath)) {
            throw new RuntimeException("The video to copy does not exist: {$fromPath}");
        }

        // Checked before the rollback cleanup is registered, so a file that was already there is never deleted
        if (File::exists($toPath) || File::exists($partPath)) {
            throw new RuntimeException("Refusing to overwrite an existing file: {$toPath}");
        }

        $size = File::size($fromPath);
        $freeBytes = $this->resolveFreeDiskSpaceAction->handle(dirname($toPath));

        if ($freeBytes !== null && $freeBytes < $size + self::RESERVED_DISK_BYTES) {
            throw new RuntimeException("Not enough free disk space to copy {$fromPath}");
        }

        $basePath = $this->resolveVideoBasePathAction->handle();

        DB::afterRollBack(function () use ($toPath, $partPath, $basePath): void {
            File::delete([$partPath, $toPath]);

            $this->pruneEmptyDirectoriesAction->handle(dirname($toPath), $basePath);
        });

        File::ensureDirectoryExists(dirname($toPath));

        if (!File::copy($fromPath, $partPath)) {
            throw new RuntimeException("Copying failed: {$fromPath}");
        }

        clearstatcache(true, $partPath);

        if (File::size($partPath) !== $size) {
            throw new RuntimeException("The copy's size does not match the original: {$fromPath}");
        }

        if (!File::move($partPath, $toPath)) {
            throw new RuntimeException("Could not rename the finished copy: {$partPath}");
        }

        return $size;
    }
}
