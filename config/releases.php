<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Release Repositories
    |--------------------------------------------------------------------------
    |
    | The git URLs the installer builds from (its saved answers). The app
    | compares the newest release tag of each GitHub repo against the
    | running version, so users learn an update exists. A repo that isn't
    | on GitHub is skipped.
    |
    */

    'backend_repo_url' => env('MOVIEBROWSER3_BACKEND_REPO', 'https://github.com/NicoHeinola/MovieBrowser3_backend'),

    'frontend_repo_url' => env('MOVIEBROWSER3_FRONTEND_REPO', 'https://github.com/NicoHeinola/MovieBrowser3_frontend'),

    /*
    |--------------------------------------------------------------------------
    | Check Intervals
    |--------------------------------------------------------------------------
    |
    | How long a GitHub answer is reused, in seconds. Unauthenticated GitHub
    | calls are limited to 60 an hour.
    |
    */

    'cache_seconds' => (int) env('RELEASE_CHECK_CACHE_SECONDS', 60 * 60),

];
