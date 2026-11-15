<?php

namespace App\Console\Commands;

use App\Actions\Backup\CreateBackupAction;
use App\Exceptions\BackupFailedException;
use Illuminate\Console\Command;
use Illuminate\Support\Number;

class CreateBackupCommand extends Command
{
    protected $signature = 'backups:create
        {--label= : Added to the file name, e.g. before-update}
        {--no-prune : Keep every older dump, e.g. before a restore that reads one of them}';

    protected $description = 'Dump the database into the backup folder now, keeping only the newest backups.keep dumps unless --no-prune.';

    public function handle(CreateBackupAction $createBackupAction): int
    {
        try {
            $backup = $createBackupAction->handle($this->option('label') ?: null, prune: !$this->option('no-prune'));
        } catch (BackupFailedException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Backed up the database to {$backup->path} (".Number::fileSize($backup->size, 1).').');

        return self::SUCCESS;
    }
}
