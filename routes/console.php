<?php

use App\Actions\VideoFileCheck\StartVideoFileCheckAction;
use App\Jobs\CreateScheduledBackupJob;
use App\Jobs\SyncDownloadsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => StartVideoFileCheckAction::run())->name('check-episode-video-files')->daily();

// Hourly, but it only backs up once the newest dump is older than backups.interval_hours: a PC that's off at night would
// miss a fixed daily time
Schedule::job(new CreateScheduledBackupJob)->name('create-backup')->hourly();

// Imports finished torrent files and cleans up seeded torrents; a no-op while there are no unfinished downloads
Schedule::job(new SyncDownloadsJob)->name('sync-downloads')->everyMinute();
