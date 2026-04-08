<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ShowController;
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

    Route::middleware(['auth:sanctum', 'admin'])->group(function (): void {
        Route::delete('shows', [ShowController::class, 'destroyMany'])->name('shows.destroy-many');
        Route::apiResource('shows', ShowController::class);
    });
});
