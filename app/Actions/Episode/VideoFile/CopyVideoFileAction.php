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

    private const CHUNK_BYTES = 8 * 1024 ** 2;

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

        $this->copyInChunks($fromPath, $partPath);

        clearstatcache(true, $partPath);

        if (File::size($partPath) !== $size) {
            throw new RuntimeException("The copy's size does not match the original: {$fromPath}");
        }

        if (!File::move($partPath, $toPath)) {
            throw new RuntimeException("Could not rename the finished copy: {$partPath}");
        }

        return $size;
    }

    /**
     * Plain reads and writes instead of PHP's copy(), which memory-maps the source in 512 MB chunks: over WSL's drvfs
     * (Windows drives under /mnt) that failed with "Bad address" and took the drive mount down mid-import.
     */
    private function copyInChunks(string $fromPath, string $toPath): void
    {
        $from = @fopen($fromPath, 'rb');

        if ($from === false) {
            throw new RuntimeException("Could not open the video to copy: {$fromPath}");
        }

        $to = @fopen($toPath, 'xb');

        if ($to === false) {
            fclose($from);

            throw new RuntimeException("Could not create the copy: {$toPath}");
        }

        try {
            while (!feof($from)) {
                $chunk = fread($from, self::CHUNK_BYTES);

                if ($chunk === false) {
                    throw new RuntimeException("Reading the video failed: {$fromPath}");
                }

                if ($chunk !== '' && fwrite($to, $chunk) !== strlen($chunk)) {
                    throw new RuntimeException("Writing the copy failed: {$toPath}");
                }
            }

            if (!fflush($to)) {
                throw new RuntimeException("Writing the copy failed: {$toPath}");
            }
        } finally {
            fclose($from);
            fclose($to);
        }
    }
}
