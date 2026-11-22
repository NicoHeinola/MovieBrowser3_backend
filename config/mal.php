<?php

return [

    // MyAnimeList's website; it has no public API without a client id, so the search JSON and anime pages are read
    'base_url' => 'https://myanimelist.net',

    // Seconds to wait for an answer before MyAnimeList counts as unavailable
    'timeout_seconds' => 10,

    // How long a search result is reused, so typing the same name again doesn't ask MyAnimeList again
    'cache_seconds' => 3600,

];
