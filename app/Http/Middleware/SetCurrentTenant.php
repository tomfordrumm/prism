<?php

namespace App\Http\Middleware;

use App\Support\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantManager = app(TenantManager::class);
        $tenant = null;

        $user = $request->user();

        if ($user) {
            $tenantId = $request->session()->get('tenant_id');

            if ($tenantId) {
                /** @var \App\Models\Tenant|null $tenantCandidate */
                $tenantCandidate = $user->tenants()->whereKey($tenantId)->first();
                if ($tenantCandidate) {
                    $tenant = $tenantCandidate;
                } else {
                    $request->session()->forget('tenant_id');
                }
            } elseif ($request->routeIs('tenants.store')) {
                // allow tenant to be created without an existing tenant in session
                return $next($request);
            }

            if (! $tenant) {
                /** @var \App\Models\Tenant|null $tenantCandidate */
                $tenantCandidate = $user->tenants()->orderBy('tenants.id')->first();
                $tenant = $tenantCandidate;

                if ($tenant) {
                    $request->session()->put('tenant_id', $tenant->id);
                }
            }

            if (! $tenant && ! $this->allowsMissingTenant($request)) {
                abort(404);
            }
        }

        $tenantManager->setCurrentTenant($tenant);

        return $next($request);
    }

    private function allowsMissingTenant(Request $request): bool
    {
        if (! $request->user()) {
            return true;
        }

        return $request->routeIs('tenants.store');
    }
}
