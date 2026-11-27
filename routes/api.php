<?php

use App\Http\Controllers\AppVersionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardStatsController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\DownloadFilesController;
use App\Http\Controllers\DownloadRetryController;
use App\Http\Controllers\EpisodeController;
use App\Http\Controllers\EpisodePlaybackController;
use App\Http\Controllers\MalAnimeController;
use App\Http\Controllers\NekoBtTorrentController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SetupCheckController;
use App\Http\Controllers\SetupCompletionController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\ShowCategoryController;
use App\Http\Controllers\ShowController;
use App\Http\Controllers\ShowEntryController;
use App\Http\Controllers\ShowEntryWatchStatusController;
use App\Http\Controllers\ShowLinkController;
use App\Http\Controllers\ShowSectionController;
use App\Http\Controllers\ShowTemplateController;
use App\Http\Controllers\ShowTitleController;
use App\Http\Controllers\ShowVideoFileCheckController;
use App\Http\Controllers\ShowWatchProgressStreamController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VideoBaseFolderMoveController;
use App\Http\Controllers\VideoFileCheckController;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\WebsiteTagController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->controller(AuthController::class)->group(function (): void {
        Route::post('register', 'register');
        Route::post('login', 'login');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('me', 'me');
            Route::post('logout', 'logout');
        });
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
        Route::apiResource('show-sections', ShowSectionController::class)->only(['index', 'show']);
        Route::apiResource('shows', ShowController::class)->only(['index', 'show']);
        Route::apiSingleton('show-entries.watch-status', ShowEntryWatchStatusController::class)->only(['update', 'destroy'])->destroyable();
        Route::apiSingleton('episodes.playback', EpisodePlaybackController::class)->creatable()->only(['store'])->middleware('video-files-idle');
        Route::get('watch-progress/stream', [ShowWatchProgressStreamController::class, 'show'])->name('watch-progress.stream');

        Route::apiResource('shows.entries', ShowEntryController::class)->shallow()->only(['index', 'show']);
        Route::apiResource('show-entries.episodes', EpisodeController::class)->shallow()->only(['index', 'show']);
        Route::apiResource('shows.links', ShowLinkController::class)->shallow()->only(['index', 'show']);
        Route::apiResource('shows.titles', ShowTitleController::class)->shallow()->only(['index', 'show']);
    });

    Route::middleware(['auth:sanctum', 'admin'])->group(function (): void {
        Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);
        Route::apiResource('show-sections', ShowSectionController::class)->except(['index', 'show']);
        Route::post('shows/{show}/categories', [ShowCategoryController::class, 'store'])->name('shows.categories.store');
        Route::delete('shows/{show}/categories/{category}', [ShowCategoryController::class, 'destroy'])->name('shows.categories.destroy');

        // `video-files-idle`: writes that move, store or delete video files wait for a video base folder move to end
        Route::delete('shows', [ShowController::class, 'destroyMany'])->name('shows.destroy-many')->middleware('video-files-idle');
        Route::apiResource('shows', ShowController::class)->except(['index', 'show'])->middlewareFor('destroy', 'video-files-idle');

        // Looks at the disk in the request: one show takes a moment, and the admin waits for what it finds
        Route::apiSingleton('shows.video-file-check', ShowVideoFileCheckController::class)->creatable()->only(['store'])->middleware('video-files-idle');

        Route::apiResource('shows.entries', ShowEntryController::class)->shallow()->except(['index', 'show'])->middlewareFor(['update', 'destroy'], 'video-files-idle');
        Route::apiResource('show-entries.episodes', EpisodeController::class)->shallow()->except(['index', 'show'])->middleware('video-files-idle');
        Route::apiResource('shows.links', ShowLinkController::class)->shallow()->except(['index', 'show']);
        Route::apiResource('shows.titles', ShowTitleController::class)->shallow()->except(['index', 'show'])->middleware('video-files-idle');

        Route::patch('settings/{setting}', [SettingController::class, 'update'])->name('settings.update');

        Route::apiResource('websites', WebsiteController::class);
        Route::apiResource('website-tags', WebsiteTagController::class);

        Route::apiResource('nekobt-torrents', NekoBtTorrentController::class)->only(['index']);
        // Torrents handed to qBittorrent, admin only like the torrent search; reading one asks qBittorrent
        Route::apiResource('downloads', DownloadController::class)->only(['index', 'store', 'show', 'destroy']);
        Route::apiSingleton('downloads.files', DownloadFilesController::class)->only(['update']);
        Route::apiSingleton('downloads.retry', DownloadRetryController::class)->creatable()->only(['store']);
        Route::apiResource('mal-anime', MalAnimeController::class)->only(['index']);
        // A show described by an outside page, for the show editor to start from; nothing is saved
        Route::apiSingleton('show-template', ShowTemplateController::class)->only(['show']);

        Route::apiResource('users', UserController::class)->except(['update']);

        Route::apiSingleton('dashboard-stats', DashboardStatsController::class)->only(['show']);
        Route::apiSingleton('app-version', AppVersionController::class)->only(['show']);

        Route::apiSingleton('setup', SetupController::class)->only(['show']);
        // Not resources: a dry run of setup values, and marking the first-login setup done
        Route::post('setup/checks', [SetupCheckController::class, 'store'])->name('setup.checks.store');
        Route::post('setup/complete', [SetupCompletionController::class, 'store'])->name('setup.complete.store');

        Route::apiSingleton('video-files/check', VideoFileCheckController::class)->creatable()->names('video-files.check')->only(['show', 'store']);
        Route::apiSingleton('video-files/move', VideoBaseFolderMoveController::class)->creatable()->names('video-files.move')->only(['show', 'store']);

        Route::apiResource('backups', BackupController::class)->only(['index', 'store']);
    });
});
