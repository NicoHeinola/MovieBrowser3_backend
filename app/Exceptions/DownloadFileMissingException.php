<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A finished file isn't in the download folder, such as after someone moved it.
 */
class DownloadFileMissingException extends RuntimeException
{
    public function __construct(string $path)
    {
        parent::__construct("{$path} is not in the download folder");
    }
}
