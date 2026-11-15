<?php

namespace App\Http\Middleware;

use App\Exceptions\VideoRelocationRunningException;
use App\Models\VideoRelocation\VideoRelocation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns away requests that touch video files while they are being moved to another base folder: the relocation
 * resolves each file's path when it reaches it, so a rename, upload or delete meanwhile would land in the wrong folder.
 */
class EnsureNoVideoRelocationRunning
{
    public function handle(Request $request, Closure $next): Response
    {
        if (VideoRelocation::query()->running()->exists()) {
            throw new VideoRelocationRunningException;
        }

        return $next($request);
    }
}
