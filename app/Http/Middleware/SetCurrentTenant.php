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
            }

            if (! $tenant) {
                /** @var \App\Models\Tenant|null $tenantCandidate */
                $tenantCandidate = $user->tenants()->orderBy('tenants.id')->first();
                $tenant = $tenantCandidate;

                if ($tenant) {
                    $request->session()->put('tenant_id', $tenant->id);
                }
            }
        }

        $tenantManager->setCurrentTenant($tenant);

        return $next($request);
    }
}
