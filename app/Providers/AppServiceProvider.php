<?php

namespace App\Providers;

use App\Models\User;
use App\Services\AccessControlService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AccessControlService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('page', fn (User $user, string $pageName) => app(AccessControlService::class)->canAccessPage($user, $pageName));
        Gate::define('program', fn (User $user, string $programName) => app(AccessControlService::class)->canAccessProgram($user, $programName));
    }
}
