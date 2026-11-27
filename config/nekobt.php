<?php

return [

    // nekoBT's public JSON API lives under this address
    'base_url' => 'https://nekobt.to',

    // Seconds to wait for an answer before the search counts as unavailable
    'timeout_seconds' => 10,

    // How long a search result is reused, so paging back and forth doesn't ask nekoBT again
    'cache_seconds' => 600,

];
