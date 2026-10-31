<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Watch Progress Stream
    |--------------------------------------------------------------------------
    |
    | GET watch-progress/stream checks the user's progress every
    | stream_poll_seconds and sends the shows that changed. With nothing to
    | send it writes a heartbeat comment every stream_heartbeat_seconds so
    | idle connections stay open, and it closes after stream_lifetime_seconds
    | (the client reconnects). Each open stream holds one PHP server worker.
    | Values under the minimums (including an empty or 0 value) are raised to
    | them: 1 s for polls and heartbeats so the stream never polls non-stop,
    | 60 s for the lifetime so clients don't reconnect over and over.
    |
    */

    'stream_poll_seconds' => max(1, (int) env('WATCH_PROGRESS_STREAM_POLL_SECONDS', 2)),

    'stream_heartbeat_seconds' => max(1, (int) env('WATCH_PROGRESS_STREAM_HEARTBEAT_SECONDS', 25)),

    'stream_lifetime_seconds' => max(60, (int) env('WATCH_PROGRESS_STREAM_LIFETIME_SECONDS', 3600)),

];
