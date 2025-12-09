<?php

use App\Models\Tenant;
use App\Support\TenantManager;

if (! function_exists('currentTenant')) {
    function currentTenant(): ?Tenant
    {
        return app(TenantManager::class)->currentTenant();
    }
}

if (! function_exists('currentTenantId')) {
    function currentTenantId(): ?int
    {
        return app(TenantManager::class)->currentTenantId();
    }
}
