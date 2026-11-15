<?php

namespace App\Actions\Backup;

use App\Dtos\Backup\BackupFileData;
use App\Exceptions\BackupFailedException;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateScheduledBackupAction
{
    use AsAction;

    public function __construct(
        private readonly ListBackupFilesAction $listBackupFilesAction,
        private readonly CreateBackupAction $createBackupAction,
    ) {}

    /**
     * The hourly schedule: backs up once the newest dump, from any source, is older than `backups.interval_hours`.
     * Returns null while it isn't due.
     *
     * @throws BackupFailedException
     */
    public function handle(): ?BackupFileData
    {
        $newest = $this->listBackupFilesAction->handle()[0] ?? null;

        // A few minutes of slack: a dump taken just after the hour would otherwise wait for the next tick, a day later
        // each day
        $dueBefore = now()->subHours((int) config('backups.interval_hours'))->addMinutes(10);

        if ($newest !== null && $newest->createdAt->gt($dueBefore)) {
            return null;
        }

        return $this->createBackupAction->handle();
    }
}
