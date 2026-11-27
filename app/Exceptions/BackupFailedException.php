<?php

namespace App\Exceptions;

use App\Enums\BackupErrorCode;

/**
 * A database dump that could not be written. Reported, so a failed scheduled backup leaves its reason in the log.
 */
class BackupFailedException extends ApiException
{
    public function __construct(BackupErrorCode $errorCode, string $message)
    {
        // A missing or read-only folder is the admin's to fix (often a drive that isn't mounted); anything else is ours
        $status = $errorCode === BackupErrorCode::DirUnavailable ? 422 : 500;

        parent::__construct($errorCode, $status, $message);
    }
}
