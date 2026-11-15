<?php

namespace App\Jobs;

use App\Actions\Backup\CreateScheduledBackupAction;
use App\Exceptions\BackupFailedException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * The hourly backup check, run by `queue` because the scheduler only queues work and has no backup folder. A failed
 * dump only goes to the log: an unplugged backup drive would otherwise add a `failed_jobs` row every hour.
 */
class CreateScheduledBackupJob implements ShouldQueue
{
    use Queueable;

    // pg_dump has its own timeout (backups.timeout_seconds); 0 keeps the worker's 60 seconds from cutting it short
    public int $timeout = 0;

    public function handle(CreateScheduledBackupAction $createScheduledBackupAction): void
    {
        try {
            $createScheduledBackupAction->handle();
        } catch (BackupFailedException $exception) {
            report($exception);
        }
    }
}
