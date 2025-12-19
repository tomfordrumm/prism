<?php

namespace App\Providers;

use App\Support\TenantManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantManager::class);
        $this->registerHelpers();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
    }

    private function registerHelpers(): void
    {
        $helpersPath = app_path('Support/helpers.php');

        if (file_exists($helpersPath)) {
            require_once $helpersPath;
        }
    }
}
