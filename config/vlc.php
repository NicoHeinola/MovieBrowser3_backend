<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Playback Tracking Interval
    |--------------------------------------------------------------------------
    |
    | How many seconds TrackVlcPlaybackJob waits between asking an open VLC
    | window which episode it is playing. Shorter catches episode changes
    | sooner; longer means fewer queued jobs. Values under 5 (including an
    | empty or 0 value) are raised to 5 so the worker never polls non-stop.
    |
    */

    'track_interval_seconds' => max(5, (int) env('VLC_TRACK_INTERVAL_SECONDS', 30)),

];
