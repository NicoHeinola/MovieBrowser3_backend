<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Host Bridge
    |--------------------------------------------------------------------------
    |
    | A backend in a container can't start vlc.exe, run wslpath or have Windows
    | copy files. The host bridge (deploy/host_bridge.py), a small server on
    | the host, does that for it. It listens on localhost only and checks the
    | shared token. Off in dev, where the backend runs on the host itself.
    |
    */

    'enabled' => (bool) env('HOST_BRIDGE_ENABLED', false),

    'url' => rtrim((string) env('HOST_BRIDGE_URL', 'http://127.0.0.1:8767'), '/'),

    'token' => (string) env('HOST_BRIDGE_TOKEN', ''),

];
