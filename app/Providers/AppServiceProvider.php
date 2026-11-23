<?php

namespace App\Providers;

use App\Actions\Download\ResolveQBittorrentConnectionAction;
use App\Dtos\QBittorrent\QBittorrentConnectionData;
use App\Models\User\User;
use App\Policies\UserPolicy;
use App\Services\Mal\MalService;
use App\Services\NekoBt\NekoBtService;
use App\Services\QBittorrent\QBittorrentMapper;
use App\Services\QBittorrent\QBittorrentService;
use App\Services\ShowTemplateSource;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The sites a show template can come from, asked in this order; another site is another tagged service
        $this->app->tag([NekoBtService::class, MalService::class], ShowTemplateSource::class);

        // One per request or job, so every action in it shares the logged-in qBittorrent session. It reads the saved
        // connection only when it first calls qBittorrent: an action that may not need it must not fail while downloads
        // are off.
        $this->app->scoped(QBittorrentService::class, fn (Application $app): QBittorrentService => new QBittorrentService(
            $app->make(QBittorrentMapper::class),
            fn (): QBittorrentConnectionData => ResolveQBittorrentConnectionAction::run(),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        Gate::policy(User::class, UserPolicy::class);
    }
}
