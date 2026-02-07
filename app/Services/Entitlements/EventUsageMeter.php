<?php

namespace App\Services\Entitlements;

use App\Events\UsageMetered;
use App\Services\Entitlements\Contracts\UsageCapabilityResolverInterface;
use App\Services\Entitlements\Contracts\UsageMeterInterface;

class EventUsageMeter implements UsageMeterInterface
{
    public function __construct(
        private UsageCapabilityResolverInterface $capabilities
    ) {}

    public function meter(int $tenantId, string $meter, int $quantity, array $context = []): void
    {
        if ($tenantId <= 0 || $quantity === 0) {
            return;
        }

        $tenantCapabilities = $this->capabilities->forTenant($tenantId);

        if (! $tenantCapabilities->supports($meter)) {
            return;
        }

        event(UsageMetered::create(
            tenantId: $tenantId,
            meter: $meter,
            quantity: $quantity,
            context: $context,
        ));
    }
}
