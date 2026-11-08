<?php

use Symfony\Component\Finder\Finder;

test('write actions run inside a database transaction', function () {
    $actionsPath = dirname(__DIR__, 2).'/app/Actions';

    // Filesystem and VLC helpers write no rows and run inside their caller's transaction; login must not hold one open during its delay,
    // the read-only progress stream (and the change lists it polls) must not hold one open for its whole lifetime, and the app version, dashboard stats and legacy import checks only read.
    $exempt = [
        'AppVersion/FetchLatestReleaseAction.php',
        'AppVersion/GetAppVersionAction.php',
        'Auth/LoginUserAction.php',
        'Dashboard/GetDashboardStatsAction.php',
        'Episode/DeleteEpisodeVideosAction.php',
        'Episode/RelocateEpisodeVideosAction.php',
        'EpisodePlayback/CheckVlcBinaryAction.php',
        'EpisodePlayback/OpenVlcPlaylistAction.php',
        'EpisodePlayback/RequestVlcBridgeAction.php',
        'EpisodePlayback/RequestVlcStatusAction.php',
        'EpisodePlayback/ResolveEphemeralPortRangeAction.php',
        'LegacyImport/CheckLegacyShowImportAction.php',
        'LegacyImport/ResolveLegacyEpisodeSourcePathAction.php',
        'Setup/CheckSetupSettingsAction.php',
        'Setup/GetSetupStatusAction.php',
        'Show/RelocateShowVideosAction.php',
        'ShowEntryWatchStatus/ListChangedShowEntryWatchStatusesAction.php',
        'ShowWatchProgress/ListChangedShowWatchProgressAction.php',
        'ShowWatchProgress/StreamShowWatchProgressAction.php',
    ];

    $missing = collect(Finder::create()->files()->in($actionsPath)->name('*Action.php')->notPath('Episode/VideoFile'))
        ->map(fn (SplFileInfo $file): string => str_replace('\\', '/', $file->getRelativePathname()))
        ->reject(fn (string $path): bool => in_array($path, $exempt, true))
        ->reject(fn (string $path): bool => str_contains(file_get_contents("{$actionsPath}/{$path}"), 'DB::transaction('))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});
