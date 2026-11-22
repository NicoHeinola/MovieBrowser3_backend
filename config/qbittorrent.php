<?php

return [

    // Seconds to wait for qBittorrent's Web API before it counts as unreachable
    'timeout_seconds' => 5,

    // The oldest qBittorrent whose Web API the app speaks: 5.0 renamed pause and resume to stop and start
    'minimum_version' => '5.0',

];
