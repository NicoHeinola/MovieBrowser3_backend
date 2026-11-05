<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Check Stale After
    |--------------------------------------------------------------------------
    |
    | How many seconds a video file check counts as running after its last
    | progress. Every chunk renews it, so this only runs out when the worker
    | died mid-check, and then the next check can start. Keep it well above
    | the time one chunk takes.
    |
    */

    'check_stale_after_seconds' => 300,

];
