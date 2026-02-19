<?php

namespace App\Providers;

use App\Models\Run;
use App\Observers\RunObserver;
use App\Services\Entitlements\CommunityEntitlementService;
use App\Services\Entitlements\CommunityUsageCapabilityResolver;
use App\Services\Entitlements\Contracts\EntitlementServiceInterface;
use App\Services\Entitlements\Contracts\UsageCapabilityResolverInterface;
use App\Services\Entitlements\Contracts\UsageMeterInterface;
use App\Services\Entitlements\EventUsageMeter;
use App\Services\Routing\Contracts\HomeRouteHandlerInterface;
use App\Services\Routing\CoreHomeRouteHandler;
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
        $this->app->singletonIf(EntitlementServiceInterface::class, CommunityEntitlementService::class);
        $this->app->singletonIf(UsageCapabilityResolverInterface::class, CommunityUsageCapabilityResolver::class);
        $this->app->singletonIf(UsageMeterInterface::class, EventUsageMeter::class);
        $this->app->singletonIf(HomeRouteHandlerInterface::class, CoreHomeRouteHandler::class);
        $this->registerHelpers();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Run::observe(RunObserver::class);
    }

    private function registerHelpers(): void
    {
        $helpersPath = app_path('Support/helpers.php');

        if (file_exists($helpersPath)) {
            require_once $helpersPath;
        }
    }
}
