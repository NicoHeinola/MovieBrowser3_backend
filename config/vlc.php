<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Playback Tracking Interval
    |--------------------------------------------------------------------------
    |
    | How many seconds TrackVlcPlaybackJob waits between asking an open VLC
    | window what it is playing. Shorter saves position and episode changes
    | sooner; longer means fewer queued jobs. Values under 1 (including an
    | empty or 0 value) are raised to 1 so the worker never polls non-stop.
    |
    */

    'track_interval_seconds' => max(1, (int) env('VLC_TRACK_INTERVAL_SECONDS', 5)),

    /*
    |--------------------------------------------------------------------------
    | Usual VLC Locations
    |--------------------------------------------------------------------------
    |
    | Where the first-login setup wizard looks for VLC, in order: a Windows
    | install seen from WSL, then Linux.
    |
    */

    'detect_paths' => [
        '/mnt/c/Program Files/VideoLAN/VLC/vlc.exe',
        '/mnt/c/Program Files (x86)/VideoLAN/VLC/vlc.exe',
        '/usr/bin/vlc',
        'vlc',
    ],

];
