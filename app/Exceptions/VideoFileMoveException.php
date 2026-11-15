<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A video file could not be moved. The message is for the logs; `path` is the file, for the status the admin sees.
 */
class VideoFileMoveException extends RuntimeException
{
    public function __construct(public readonly string $path, Throwable $previous)
    {
        parent::__construct("Could not move {$path}: {$previous->getMessage()}", previous: $previous);
    }
}
