<?php

namespace App\Actions\Backup;

use App\Dtos\Backup\BackupFileData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Finder\SplFileInfo;

class ListBackupFilesAction
{
    use AsAction;

    /**
     * `moviebrowser3-<UTC Ymd-His>[-<label>].dump`. The name holds when the dump was taken, so copying a file around
     * keeps its place. Other files in the folder are never listed or deleted.
     */
    public const NAME_PATTERN = '/^moviebrowser3-(\d{8}-\d{6})(?:-[a-z]+(?:-[a-z]+)*)?\.dump$/';

    /**
     * The dumps in the backup folder, newest first. Empty when the folder is missing.
     *
     * @return list<BackupFileData>
     */
    public function handle(): array
    {
        $folder = config('backups.path');

        if (!File::isDirectory($folder)) {
            return [];
        }

        // File::files rather than an SPL directory iterator: on a Windows drive (drvfs) those skip entries
        return collect(File::files($folder))
            ->map(function (SplFileInfo $file): ?BackupFileData {
                if (!preg_match(self::NAME_PATTERN, $file->getFilename(), $matches)) {
                    return null;
                }

                return new BackupFileData(
                    name: $file->getFilename(),
                    path: $file->getPathname(),
                    size: $file->getSize(),
                    createdAt: CarbonImmutable::createFromFormat('Ymd-His', $matches[1], 'UTC'),
                );
            })
            ->filter()
            ->sortBy([['createdAt', 'desc'], ['name', 'desc']])
            ->values()
            ->all();
    }
}
