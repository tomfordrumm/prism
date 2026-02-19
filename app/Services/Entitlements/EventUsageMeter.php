<?php

namespace App\Services\Entitlements;

use App\Events\UsageMetered;
use App\Services\Entitlements\Contracts\UsageCapabilityResolverInterface;
use App\Services\Entitlements\Contracts\UsageMeterInterface;
use Illuminate\Support\Facades\Log;

class EventUsageMeter implements UsageMeterInterface
{
    public function __construct(
        private UsageCapabilityResolverInterface $capabilities
    ) {}

    public function meter(int $tenantId, string $meter, int $quantity, array $context = []): void
    {
        if ($tenantId <= 0 || $quantity <= 0) {
            Log::debug('Usage metering skipped for invalid tenant or quantity.', [
                'tenant_id' => $tenantId,
                'meter' => $meter,
                'quantity' => $quantity,
                'reason' => 'invalid_tenant_or_quantity',
            ]);

            return;
        }

        $tenantCapabilities = $this->capabilities->forTenant($tenantId);

        if (! $tenantCapabilities->supports($meter)) {
            Log::debug('Usage metering skipped for unsupported meter.', [
                'tenant_id' => $tenantId,
                'meter' => $meter,
                'quantity' => $quantity,
                'reason' => 'unsupported_meter',
            ]);

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
