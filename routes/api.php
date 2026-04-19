<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EpisodeController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ShowController;
use App\Http\Controllers\ShowEntryController;
use App\Http\Controllers\ShowLinkController;
use App\Http\Controllers\ShowTitleController;
use App\Http\Controllers\UserController;
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

        Route::apiResource('shows.entries', ShowEntryController::class)->shallow()->only(['index', 'show']);
        Route::apiResource('show-entries.episodes', EpisodeController::class)->shallow()->only(['index', 'show']);
        Route::apiResource('shows.links', ShowLinkController::class)->shallow()->only(['index', 'show']);
        Route::apiResource('shows.titles', ShowTitleController::class)->shallow()->only(['index', 'show']);
    });

    Route::get('settings', [SettingController::class, 'index'])->name('settings.index');

    Route::middleware(['auth:sanctum', 'admin'])->group(function (): void {
        Route::delete('shows', [ShowController::class, 'destroyMany'])->name('shows.destroy-many');
        Route::apiResource('shows', ShowController::class);

        Route::apiResource('shows.entries', ShowEntryController::class)->shallow()->except(['index', 'show']);
        Route::apiResource('show-entries.episodes', EpisodeController::class)->shallow()->except(['index', 'show']);
        Route::apiResource('shows.links', ShowLinkController::class)->shallow()->except(['index', 'show']);
        Route::apiResource('shows.titles', ShowTitleController::class)->shallow()->except(['index', 'show']);

        Route::patch('settings/{key}', [SettingController::class, 'update'])->name('settings.update');

        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});
