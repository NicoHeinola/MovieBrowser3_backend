<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Video File Check
    |--------------------------------------------------------------------------
    |
    | A check counts as running for `check_stale_after_seconds` after its last
    | checked episode. It only runs out when the worker died, and then the next
    | check can start.
    |
    */

    'check_stale_after_seconds' => 300,

];
