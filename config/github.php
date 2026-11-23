<?php

return [

    // GitHub's REST API, read without a token: 60 calls an hour, so release checks are cached (see releases.php)
    'base_url' => 'https://api.github.com',

    // Seconds to wait for an answer before the release check counts as failed
    'timeout_seconds' => 5,

];
