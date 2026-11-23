<?php

return [

    // Seconds to wait for qBittorrent's Web API before it counts as unreachable
    'timeout_seconds' => 5,

    // The oldest qBittorrent whose Web API the app speaks: 5.0 renamed pause and resume to stop and start
    'minimum_version' => '5.0',

    // The category that marks the app's torrents in qBittorrent, so it only ever touches its own
    'category' => 'moviebrowser3',

    // How often to look for a just-added torrent, half a second apart: qBittorrent first reads a `.torrent` link
    'add_wait_attempts' => 20,

];
