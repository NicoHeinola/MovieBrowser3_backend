<?php

namespace App\Providers;

use App\Models\User\User;
use App\Policies\UserPolicy;
use App\Services\NekoBt\NekoBtService;
use App\Services\ShowTemplateSource;
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
        $this->app->tag([NekoBtService::class], ShowTemplateSource::class);
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
