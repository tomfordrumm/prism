<?php

namespace App\Services\Entitlements;

use App\Services\Entitlements\Contracts\UsageCapabilityResolverInterface;

final class CommunityUsageCapabilityResolver implements UsageCapabilityResolverInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function forTenant(int $tenantId): UsageCapabilities
    {
        return new UsageCapabilities([
            'run_count' => true,
            'input_tokens' => true,
            'output_tokens' => true,
            'active_members' => true,
            'storage_bytes' => false,
        ]);
    }
}
