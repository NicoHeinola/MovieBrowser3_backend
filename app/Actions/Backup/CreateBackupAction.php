<?php

namespace App\Actions\Backup;

use App\Dtos\Backup\BackupFileData;
use App\Enums\BackupErrorCode;
use App\Exceptions\BackupFailedException;
use Carbon\CarbonImmutable;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateBackupAction
{
    use AsAction;

    public function __construct(private readonly DeleteOldBackupsAction $deleteOldBackupsAction) {}

    /**
     * Dumps the database into the backup folder with `pg_dump --format=custom`, then deletes the dumps past
     * `backups.keep` unless `$prune` is false. `$label` (`before-update`) ends up in the file name. Takes a second or two for this database,
     * so requests and jobs run it directly. Writes no rows, so it works before the migrations too.
     *
     * @throws BackupFailedException
     */
    public function handle(?string $label = null, bool $prune = true): BackupFileData
    {
        if ($label !== null && !preg_match('/^[a-z]+(-[a-z]+)*$/', $label)) {
            throw new InvalidArgumentException("A backup label is lowercase words joined by '-', not '{$label}'.");
        }

        $folder = config('backups.path');

        if (!File::isDirectory($folder) || !File::isWritable($folder)) {
            throw new BackupFailedException(BackupErrorCode::DirUnavailable, "The backup folder {$folder} doesn't exist or isn't writable.");
        }

        $createdAt = CarbonImmutable::now('UTC');
        $name = 'moviebrowser3-'.$createdAt->format('Ymd-His').($label !== null ? "-{$label}" : '').'.dump';
        $path = "{$folder}/{$name}";

        // Its own name, so two dumps started in the same second never write one file
        $partial = "{$path}.".Str::lower(Str::random(8)).'.partial';
        $this->dump($partial);

        // Renamed only once complete, so a half-written dump never counts as a backup
        File::move($partial, $path);

        if ($prune) {
            $this->deleteOldBackupsAction->handle();
        }

        return new BackupFileData(name: $name, path: $path, size: File::size($path), createdAt: $createdAt);
    }

    /**
     * Production and dev run on Postgres (tests fake pg_dump). The password goes through the environment, so it
     * stays out of the process list.
     */
    private function dump(string $file): void
    {
        $connection = config('database.connections.pgsql');

        try {
            $result = Process::timeout((int) config('backups.timeout_seconds'))
                ->env(['PGPASSWORD' => (string) $connection['password']])
                ->run([
                    'pg_dump',
                    '--format=custom',
                    "--host={$connection['host']}",
                    "--port={$connection['port']}",
                    "--username={$connection['username']}",
                    "--file={$file}",
                    $connection['database'],
                ]);
        } catch (ProcessTimedOutException) {
            File::delete($file);

            throw new BackupFailedException(BackupErrorCode::Failed, 'pg_dump timed out.');
        }

        if ($result->failed()) {
            File::delete($file);

            throw new BackupFailedException(BackupErrorCode::Failed, 'pg_dump failed: '.trim($result->errorOutput()));
        }
    }
}
