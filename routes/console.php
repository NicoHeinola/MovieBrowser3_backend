<?php

use App\Actions\Episode\VideoFile\CreateVideoFileCheckAction;
use App\Jobs\CreateScheduledBackupJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => CreateVideoFileCheckAction::run())->name('check-episode-video-files')->daily();

// Hourly, but it only backs up once the newest dump is older than backups.interval_hours: a PC that's off at night would
// miss a fixed daily time
Schedule::job(new CreateScheduledBackupJob)->name('create-backup')->hourly();
