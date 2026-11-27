<?php

use Symfony\Component\Finder\Finder;

test('actions that write more than once do it in a database transaction', function () {
    $actionsPath = dirname(__DIR__, 2).'/app/Actions';

    // Each of their writes stands on its own: one per download or torrent with slow work in between, one of several
    // outcomes, or a cleanup that is right whatever comes after
    $exempt = [
        'Download/Client/SyncUnfinishedDownloadsAction.php',
        'VideoFileCheck/CheckEpisodeVideoFileAction.php',
        'VideoFileCheck/StartVideoFileCheckAction.php',
    ];

    // Methods of a model, a query or a relation; `$this->` ones are the action's own
    $writes = '/(?<!\$this)->(create|createOrFirst|firstOrCreate|updateOrCreate|update|save|increment|delete|attach|detach)\(/';

    $missing = collect(Finder::create()->files()->in($actionsPath)->name('*Action.php'))
        ->filter(fn (SplFileInfo $file): bool => preg_match_all($writes, $file->getContents()) > 1)
        ->reject(fn (SplFileInfo $file): bool => str_contains($file->getContents(), 'DB::transaction('))
        ->map(fn (SplFileInfo $file): string => str_replace('\\', '/', $file->getRelativePathname()))
        ->reject(fn (string $path): bool => in_array($path, $exempt, true))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});
