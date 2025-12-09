<?php

namespace App\Support;

use App\Models\Tenant;

class TenantManager
{
    private ?Tenant $tenant = null;

    public function setCurrentTenant(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function currentTenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function currentTenantId(): ?int
    {
        return $this->tenant?->id;
    }
}
