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

    /*
    |--------------------------------------------------------------------------
    | Launcher
    |--------------------------------------------------------------------------
    |
    | "local" starts VLC from this process and converts paths with wslpath.
    | "bridge" asks the host-side VLC bridge (deploy/vlc_bridge.py) to do
    | both, for a backend running in a container that cannot see vlc.exe.
    | The bridge only listens on localhost and checks the shared token.
    |
    */

    'launcher' => env('VLC_LAUNCHER', 'local') === 'bridge' ? 'bridge' : 'local',

    'bridge_url' => rtrim((string) env('VLC_BRIDGE_URL', 'http://127.0.0.1:8767'), '/'),

    'bridge_token' => (string) env('VLC_BRIDGE_TOKEN', ''),

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
