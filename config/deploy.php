<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Media Folders
    |--------------------------------------------------------------------------
    |
    | The folders the production installer mounts into the containers, at the
    | same path as on the host (MOVIEBROWSER3_MEDIA_DIRS, ":"-separated). Only
    | paths inside them are visible to the backend and VLC. Empty in dev,
    | where every path is.
    |
    */

    'media_folders' => array_values(array_filter(
        array_map(fn (string $folder): string => rtrim($folder, '/'), explode(':', (string) env('MOVIEBROWSER3_MEDIA_DIRS', ''))),
    )),

];
