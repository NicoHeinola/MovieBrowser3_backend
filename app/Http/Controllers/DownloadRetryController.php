<?php

namespace App\Http\Controllers;

use App\Actions\Download\CreateDownloadRetryAction;
use App\Models\Download\Download;
use Illuminate\Http\Response;

/**
 * Tries a failed download's imports again: the next sync picks it up.
 */
class DownloadRetryController extends Controller
{
    public function store(Download $download, CreateDownloadRetryAction $action): Response
    {
        $action->handle($download);

        return response()->noContent();
    }
}
