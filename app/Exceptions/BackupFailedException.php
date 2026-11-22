<?php

namespace App\Exceptions;

use App\Enums\BackupErrorCode;
use App\Http\Resources\ApiError\ApiErrorDetail;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A database dump that could not be written. Reported, so a failed scheduled backup leaves its reason in the log.
 */
class BackupFailedException extends RuntimeException
{
    public function __construct(public readonly BackupErrorCode $errorCode, string $message)
    {
        parent::__construct($message);
    }

    /**
     * A missing or read-only folder is the admin's to fix (often a drive that isn't mounted); anything else is ours.
     */
    public function render(): JsonResponse
    {
        $status = $this->errorCode === BackupErrorCode::DirUnavailable ? 422 : 500;

        return ApiErrorDetail::response($this->errorCode, $status);
    }
}
