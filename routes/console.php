<?php

use App\Actions\Episode\VideoFile\CreateVideoFileCheckAction;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => CreateVideoFileCheckAction::run())->name('check-episode-video-files')->daily();
