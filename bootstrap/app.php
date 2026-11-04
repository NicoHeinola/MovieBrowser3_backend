<?php

use App\Enums\ApiErrorCode;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Resources\Validation\ValidationErrorResource;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(fn (ValidationException $exception) => ValidationErrorResource::make($exception)
            ->response()
            ->setStatusCode($exception->status));

        $exceptions->render(fn (AuthenticationException $exception) => response()->json([
            'code' => ApiErrorCode::Unauthenticated,
            'params' => (object) [],
        ], 401));
    })->create();
