<?php

namespace App\Services\Entitlements\Contracts;

use App\Services\Entitlements\EntitlementDecision;
use App\Services\Entitlements\QuotaDecision;

interface EntitlementServiceInterface
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function checkFeatureAccess(int $tenantId, string $feature, array $context = []): EntitlementDecision;

    /**
     * @param  array<string, mixed>  $context
     */
    public function checkQuota(int $tenantId, string $quota, int $requestedUnits = 1, array $context = []): QuotaDecision;
}
