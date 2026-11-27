<?php

namespace App\Http\Controllers;

use App\Actions\Download\RetryDownloadAction;
use App\Models\Download\Download;
use Illuminate\Http\Response;

/**
 * Goes over a download again: checks which of its files are really there, and starts what's missing.
 */
class DownloadRetryController extends Controller
{
    public function store(Download $download, RetryDownloadAction $action): Response
    {
        $action->handle($download);

        return response()->noContent();
    }
}
