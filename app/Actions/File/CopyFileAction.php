<?php

namespace App\Actions\File;

use App\Exceptions\HostBridgeException;
use App\Services\HostBridge\HostBridgeService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Throwable;

class CopyFileAction
{
    use AsAction;

    private const int CHUNK_BYTES = 8 * 1024 ** 2;

    public function __construct(
        private readonly CreateFileLinkAction $createFileLinkAction,
        private readonly EnsureDiskSpaceAction $ensureDiskSpaceAction,
        private readonly HostBridgeService $hostBridgeService,
    ) {}

    /**
     * Copies a file the fastest way there is:
     * 1. on one drive it gets a second name (a hard link), which takes no time or space;
     * 2. between Windows drives the bridge has Windows copy it, many times faster than WSL's /mnt;
     * 3. otherwise it's read and written here.
     *
     * A link is the same data as the original, so nobody may change either in place. The copy is made next to the
     * target as `.part` and renamed once its size matches, so a target is always whole; a failed copy deletes it.
     */
    public function handle(string $fromPath, string $toPath): void
    {
        $partPath = "{$toPath}.part";

        if (File::exists($toPath)) {
            throw new RuntimeException("Refusing to overwrite an existing file: {$toPath}");
        }

        // Left behind by a copy whose worker was killed. No other copy can be writing it: imports run one at a time,
        // and so do the files of a folder move
        File::delete($partPath);

        try {
            $this->copyFile($fromPath, $partPath);
            $this->renameFinishedCopy($partPath, $toPath, File::size($fromPath));
        } catch (Throwable $exception) {
            File::delete($partPath);

            throw $exception;
        }
    }

    private function copyFile(string $fromPath, string $toPath): void
    {
        File::ensureDirectoryExists(dirname($toPath));

        // Before looking at the disk space: a link takes none
        $isLinked = $this->createFileLinkAction->handle($fromPath, $toPath);

        if ($isLinked) {
            return;
        }

        $this->ensureDiskSpaceAction->handle(dirname($toPath), File::size($fromPath));

        if (!config('host_bridge.enabled')) {
            $this->copyInChunks($fromPath, $toPath);

            return;
        }

        try {
            $this->hostBridgeService->copyFile($fromPath, $toPath);
        } catch (HostBridgeException $exception) {
            // Slow, but when the bridge stops while the video base folder moves, moving back would fail too and leave
            // the videos split over two folders
            Log::warning("The bridge could not copy a file, so it's copied here: {$exception->getMessage()}");

            $this->copyInChunks($fromPath, $toPath);
            $this->deleteBridgeLeftovers($fromPath, $toPath);
        }
    }

    private function renameFinishedCopy(string $partPath, string $toPath, int $size): void
    {
        clearstatcache(true, $partPath);

        if (File::size($partPath) !== $size) {
            throw new RuntimeException("The copy's size does not match the original: {$toPath}");
        }

        if (!File::move($partPath, $toPath)) {
            throw new RuntimeException("Could not rename the finished copy: {$partPath}");
        }
    }

    /**
     * A bridge that was stopped leaves its half-written copy in the folder it copies into (deploy/host_bridge.py).
     */
    private function deleteBridgeLeftovers(string $fromPath, string $toPath): void
    {
        $directory = "{$toPath}.copying";

        File::delete($directory.'/'.basename($fromPath));
        @rmdir($directory);
    }

    /**
     * Plain reads and writes instead of PHP's copy(), which memory-maps the source in 512 MB chunks: over WSL's drvfs
     * (Windows drives under /mnt) that failed with "Bad address" and took the drive mount down mid-copy.
     */
    private function copyInChunks(string $fromPath, string $toPath): void
    {
        $from = @fopen($fromPath, 'rb');

        if ($from === false) {
            throw new RuntimeException("Could not open the file to copy: {$fromPath}");
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
                    throw new RuntimeException("Reading the file failed: {$fromPath}");
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
