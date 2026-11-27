<?php

namespace App\Actions\File;

use App\Exceptions\NotEnoughDiskSpaceException;
use Lorisleiva\Actions\Concerns\AsAction;

class EnsureDiskSpaceAction
{
    use AsAction;

    /** Space left free, so a full disk fails the write instead of the system */
    private const int RESERVED_BYTES = 1024 ** 3;

    public function __construct(private readonly ResolveDiskSpaceAction $resolveDiskSpaceAction) {}

    /**
     * Makes sure something fits on a disk before it's written there. A disk that can't be measured counts as big enough.
     *
     * @param  string  $path  The folder it's written to
     *
     * @throws NotEnoughDiskSpaceException
     */
    public function handle(string $path, int $bytes): void
    {
        $diskSpace = $this->resolveDiskSpaceAction->handle($path);
        $neededBytes = $bytes + self::RESERVED_BYTES;

        if ($diskSpace !== null && $diskSpace->freeBytes < $neededBytes) {
            throw new NotEnoughDiskSpaceException($neededBytes, $diskSpace->freeBytes);
        }
    }
}
