<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use Illuminate\Contracts\Debug\ShouldntReport;

/**
 * Something doesn't fit on the disk it would be written to. Its own class, so an import can tell a full disk from a failed
 * copy.
 */
class NotEnoughDiskSpaceException extends ApiException implements ShouldntReport
{
    public function __construct(int $neededBytes, int $freeBytes)
    {
        // Bytes: the frontend formats them in the reader's language
        $params = ['needed_bytes' => (string) $neededBytes, 'free_bytes' => (string) $freeBytes];

        parent::__construct(
            ApiErrorCode::NotEnoughDiskSpace,
            422,
            "The disk has {$freeBytes} bytes free, but {$neededBytes} are needed.",
            $params,
        );
    }
}
