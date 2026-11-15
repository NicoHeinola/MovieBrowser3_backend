<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Video files are being moved to another base folder, so nothing may touch them or start another move.
 */
class VideoRelocationRunningException extends RuntimeException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct('A video relocation is running.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['code' => ApiErrorCode::VideoRelocationRunning, 'params' => (object) []], 409);
    }
}
