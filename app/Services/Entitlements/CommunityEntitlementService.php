<?php

namespace App\Services\Entitlements;

use App\Services\Entitlements\Contracts\EntitlementServiceInterface;

final class CommunityEntitlementService implements EntitlementServiceInterface
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function checkFeatureAccess(int $tenantId, string $feature, array $context = []): EntitlementDecision
    {
        return EntitlementDecision::allow([
            'edition' => 'community',
            'tenant_id' => $tenantId,
            'feature' => $feature,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function checkQuota(int $tenantId, string $quota, int $requestedUnits = 1, array $context = []): QuotaDecision
    {
        if ($requestedUnits < 1) {
            return QuotaDecision::deny(
                limit: 0,
                used: 0,
                reason: 'invalid_requested_units',
                meta: [
                    'edition' => 'community',
                    'tenant_id' => $tenantId,
                    'quota' => $quota,
                    'requested_units' => $requestedUnits,
                ],
            );
        }

        return QuotaDecision::allowUnlimited([
            'edition' => 'community',
            'tenant_id' => $tenantId,
            'quota' => $quota,
            'requested_units' => $requestedUnits,
        ]);
    }
}
