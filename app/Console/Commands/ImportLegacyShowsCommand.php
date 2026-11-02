<?php

namespace App\Console\Commands;

use App\Actions\Episode\VideoFile\CopyVideoFileAction;
use App\Actions\Episode\VideoFile\ResolveFreeDiskSpaceAction;
use App\Actions\Episode\VideoFile\ResolveVideoBasePathAction;
use App\Actions\LegacyImport\CheckLegacyShowImportAction;
use App\Actions\LegacyImport\CreateLegacyShowImportAction;
use App\Dtos\LegacyImport\LegacyShowData;
use App\Dtos\LegacyImport\LegacyShowImportCheckData;
use App\Models\Setting\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Number;
use RuntimeException;
use Throwable;

class ImportLegacyShowsCommand extends Command
{
    protected $signature = 'moviebrowser2:import-shows
        {file : The JSON from the MovieBrowser2 export endpoint}
        {--source-root= : MovieBrowser2\'s video folder, when it is not at the absolute paths in the export}
        {--only=* : Import only these MovieBrowser2 show ids}
        {--dry-run : Only check what would be imported}
        {--force : Skip the confirmation}';

    protected $description = 'Import shows from a MovieBrowser2 export, copying their videos. The originals are only read.';

    private bool $cancelled = false;

    public function handle(
        CheckLegacyShowImportAction $checkLegacyShowImportAction,
        CreateLegacyShowImportAction $createLegacyShowImportAction,
        ResolveFreeDiskSpaceAction $resolveFreeDiskSpaceAction,
        ResolveVideoBasePathAction $resolveVideoBasePathAction,
    ): int {
        $file = (string) $this->argument('file');

        if (!File::isFile($file)) {
            $this->components->error("File not found: {$file}");

            return self::FAILURE;
        }

        // Until the setup wizard picks a video folder, copies would land in the fallback storage/app/videos, which in
        // Docker is the WSL disk rather than the media drive
        if (!Setting::query()->whereKey('setup_completed_at')->value('value')) {
            $this->components->error('Finish the first-login setup in MovieBrowser3 first, so the videos are copied into your video folder.');

            return self::FAILURE;
        }

        $sourceRoot = $this->option('source-root') ?: null;
        $only = array_map('intval', (array) $this->option('only'));

        $legacyShows = collect(LegacyShowData::collect(json_decode(File::get($file), true, flags: JSON_THROW_ON_ERROR)))
            ->filter(fn (LegacyShowData $show): bool => $only === [] || in_array($show->id, $only, true))
            ->values();

        $this->components->info("Checking {$legacyShows->count()} shows.");

        $checks = $legacyShows->map(fn (LegacyShowData $show): LegacyShowImportCheckData => $checkLegacyShowImportAction->handle($show, $sourceRoot));
        $pending = $checks->whereNull('importedShowId');

        foreach ($pending as $check) {
            foreach ($check->missingFiles as $missingFile) {
                $this->components->warn("{$check->title}: video missing, the episode is imported without one: {$missingFile}");
            }

            foreach ($check->unknownUsernames as $username) {
                $this->components->warn("{$check->title}: no MovieBrowser3 user \"{$username}\", their watch status is skipped.");
            }
        }

        $basePath = $resolveVideoBasePathAction->handle();
        $totalBytes = $pending->sum('totalBytes');
        $freeBytes = $resolveFreeDiskSpaceAction->handle($basePath);

        $this->components->twoColumnDetail('Already imported, skipped', (string) ($checks->count() - $pending->count()));
        $this->components->twoColumnDetail('Shows to import', (string) $pending->count());
        $this->components->twoColumnDetail('Videos to copy', (string) $pending->sum('fileCount'));
        $this->components->twoColumnDetail('Videos missing', (string) $pending->sum(fn (LegacyShowImportCheckData $check): int => count($check->missingFiles)));
        $this->components->twoColumnDetail('Size to copy', Number::fileSize($totalBytes, precision: 1));
        $this->components->twoColumnDetail('Copy into', $basePath);
        $this->components->twoColumnDetail('Free space there', $freeBytes === null ? 'unknown' : Number::fileSize($freeBytes, precision: 1));

        if ($pending->isEmpty()) {
            $this->components->info('Nothing to import.');

            return self::SUCCESS;
        }

        // Every video missing means MovieBrowser2's folder isn't visible here; importing would leave every episode without one
        if ($pending->sum('fileCount') === 0 && $pending->sum(fn (LegacyShowImportCheckData $check): int => count($check->missingFiles)) > 0) {
            $this->components->error('None of the videos were found. Check that the MovieBrowser2 video folder is mounted, or pass it with --source-root.');

            return self::FAILURE;
        }

        if ($freeBytes !== null && $freeBytes < $totalBytes + CopyVideoFileAction::RESERVED_DISK_BYTES) {
            $this->components->error('Not enough free space for the copies. Free some up, or import fewer shows at a time with --only.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->components->info('Dry run: nothing was imported.');

            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm("Import {$pending->count()} shows and copy their videos into {$basePath}?")) {
            $this->components->info('Cancelled.');

            return self::SUCCESS;
        }

        // Ctrl+C lets the running copy finish, then cancels the current show (rolling back its rows and copies)
        $this->trap([SIGINT, SIGTERM], function (): void {
            $this->cancelled = true;
            $this->newLine();
            $this->components->warn('Stopping after the current file. The show in progress is rolled back.');
        });

        $failures = [];
        $position = 0;

        foreach ($pending as $check) {
            if ($this->cancelled) {
                break;
            }

            $position++;
            $legacyShow = $legacyShows->firstWhere('id', $check->legacyShowId);

            $this->newLine();
            $this->line("[{$position}/{$pending->count()}] {$check->title}: {$check->fileCount} videos, ".Number::fileSize($check->totalBytes, precision: 1));

            $bar = $this->output->createProgressBar($check->fileCount);

            try {
                $import = $createLegacyShowImportAction->handle($legacyShow, $sourceRoot, function () use ($bar): void {
                    $bar->advance();

                    // After the show's last copy there is nothing left to save time on, so let it commit
                    if ($this->cancelled && $bar->getProgress() < $bar->getMaxSteps()) {
                        throw new RuntimeException('Cancelled.');
                    }
                });

                $bar->finish();
                $this->newLine();
                $this->components->info("Imported as show {$import->show_id}.");
            } catch (Throwable $exception) {
                $this->newLine();
                $this->components->error("{$check->title} was not imported: {$exception->getMessage()}");
                $failures[] = $check->title;
            }
        }

        $this->newLine();

        if ($this->cancelled) {
            $notStarted = $pending->count() - $position;
            $this->components->warn("Stopped. {$notStarted} shows were not started".($failures === [] ? '' : ', and these were not imported: '.implode(', ', $failures)).'. Run the same command again to continue; imported shows are skipped.');

            return self::FAILURE;
        }

        if ($failures !== []) {
            $this->components->warn('Not imported: '.implode(', ', $failures).'. Run the command again to retry; imported shows are skipped.');

            return self::FAILURE;
        }

        $this->components->info('Every show was imported.');

        return self::SUCCESS;
    }
}
