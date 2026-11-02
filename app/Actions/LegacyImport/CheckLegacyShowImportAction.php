<?php

namespace App\Actions\LegacyImport;

use App\Dtos\LegacyImport\LegacyShowData;
use App\Dtos\LegacyImport\LegacyShowImportCheckData;
use App\Dtos\LegacyImport\LegacyUserShowStatusData;
use App\Models\LegacyShowImport\LegacyShowImport;
use App\Models\User\User;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckLegacyShowImportAction
{
    use AsAction;

    public function __construct(private readonly ResolveLegacyEpisodeSourcePathAction $resolveLegacyEpisodeSourcePathAction) {}

    /**
     * Read-only: finds the show's video files and their sizes, and what can't be imported, without writing anything.
     */
    public function handle(LegacyShowData $legacyShow, ?string $sourceRoot = null): LegacyShowImportCheckData
    {
        $fileCount = 0;
        $totalBytes = 0;
        $missingFiles = [];

        foreach ($legacyShow->seasons as $season) {
            foreach ($season->episodes as $episode) {
                $sourcePath = $this->resolveLegacyEpisodeSourcePathAction->handle($episode, $sourceRoot);

                if ($sourcePath === null) {
                    continue;
                }

                if (!File::isFile($sourcePath)) {
                    $missingFiles[] = $sourcePath;

                    continue;
                }

                $fileCount++;
                $totalBytes += File::size($sourcePath);
            }
        }

        $unknownUsernames = collect($legacyShow->userShowStatuses)
            ->map(fn (LegacyUserShowStatusData $status): string => $status->username)
            ->unique()
            ->reject(fn (string $username): bool => User::query()->usernameIgnoringCase($username)->exists())
            ->values()
            ->all();

        return new LegacyShowImportCheckData(
            legacyShowId: $legacyShow->id,
            title: $legacyShow->title,
            importedShowId: LegacyShowImport::query()->where('legacy_show_id', $legacyShow->id)->value('show_id'),
            fileCount: $fileCount,
            totalBytes: $totalBytes,
            missingFiles: $missingFiles,
            unknownUsernames: $unknownUsernames,
        );
    }
}
