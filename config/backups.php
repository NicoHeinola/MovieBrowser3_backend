<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup Folder
    |--------------------------------------------------------------------------
    |
    | Where database dumps go. The production installer asks for it and
    | mounts it at the same path as on the host, ideally on another drive
    | than the database. Empty uses storage/app/backups.
    |
    */

    'path' => rtrim((string) env('BACKUP_PATH', ''), '/') ?: storage_path('app/backups'),

    /*
    |--------------------------------------------------------------------------
    | Interval And Retention
    |--------------------------------------------------------------------------
    |
    | A backup runs once the newest dump is older than `interval_hours`. After
    | each one, only the newest `keep` dumps stay. Without a successful backup
    | for twice the interval, the status reports it as stale.
    |
    */

    'interval_hours' => (int) env('BACKUP_INTERVAL_HOURS', 24),

    'keep' => max(1, (int) env('BACKUP_KEEP', 14)),

    /*
    |--------------------------------------------------------------------------
    | Dump Timeout
    |--------------------------------------------------------------------------
    |
    | Seconds pg_dump may run before the backup counts as failed.
    |
    */

    'timeout_seconds' => 600,

];
