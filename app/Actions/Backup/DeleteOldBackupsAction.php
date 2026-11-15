<?php

namespace App\Actions\Backup;

use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteOldBackupsAction
{
    use AsAction;

    public function __construct(private readonly ListBackupFilesAction $listBackupFilesAction) {}

    /**
     * Deletes every dump but the newest `backups.keep`, and half-written ones a killed dump left behind.
     */
    public function handle(): void
    {
        foreach (array_slice($this->listBackupFilesAction->handle(), (int) config('backups.keep')) as $backup) {
            File::delete($backup->path);
        }

        // Older than any dump could still be running, so it can't be one in progress
        $abandonedBefore = now()->subSeconds(2 * (int) config('backups.timeout_seconds'))->getTimestamp();

        foreach (File::glob(config('backups.path').'/moviebrowser3-*.partial') as $partial) {
            if (File::lastModified($partial) < $abandonedBefore) {
                File::delete($partial);
            }
        }
    }
}
