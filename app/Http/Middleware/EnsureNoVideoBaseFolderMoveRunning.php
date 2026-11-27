<?php

namespace App\Http\Middleware;

use App\Exceptions\VideoBaseFolderMoveRunningException;
use App\Models\VideoBaseFolderMove\VideoBaseFolderMove;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns away requests that touch video files while they are being moved to another video base folder: the folder move
 * resolves each file's path when it reaches it, so a rename, upload or delete meanwhile would land in the wrong folder.
 */
class EnsureNoVideoBaseFolderMoveRunning
{
    public function handle(Request $request, Closure $next): Response
    {
        if (VideoBaseFolderMove::query()->running()->exists()) {
            throw new VideoBaseFolderMoveRunningException;
        }

        return $next($request);
    }
}
