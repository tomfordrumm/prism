<?php

namespace App\Services\Entitlements\Contracts;

use App\Services\Entitlements\UsageCapabilities;

interface UsageCapabilityResolverInterface
{
    public function forTenant(int $tenantId): UsageCapabilities;
}
