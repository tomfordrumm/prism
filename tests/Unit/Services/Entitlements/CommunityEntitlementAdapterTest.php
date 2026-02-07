<?php

namespace Tests\Unit\Services\Entitlements;

use App\Services\Entitlements\CommunityEntitlementService;
use App\Services\Entitlements\CommunityUsageCapabilityResolver;
use App\Services\Entitlements\Contracts\EntitlementServiceInterface;
use App\Services\Entitlements\Contracts\UsageCapabilityResolverInterface;
use App\Services\Entitlements\EntitlementDecision;
use App\Services\Entitlements\QuotaDecision;
use App\Services\Entitlements\UsageCapabilities;
use Tests\TestCase;

class CommunityEntitlementAdapterTest extends TestCase
{
    public function test_default_bindings_resolve_to_community_adapter(): void
    {
        $entitlements = $this->app->make(EntitlementServiceInterface::class);
        $usageCapabilities = $this->app->make(UsageCapabilityResolverInterface::class);

        $this->assertInstanceOf(CommunityEntitlementService::class, $entitlements);
        $this->assertInstanceOf(CommunityUsageCapabilityResolver::class, $usageCapabilities);
    }

    public function test_community_adapter_allows_features_and_unlimited_quota(): void
    {
        $adapter = $this->app->make(EntitlementServiceInterface::class);

        $featureDecision = $adapter->checkFeatureAccess(
            tenantId: 42,
            feature: 'runs.execute',
        );
        $quotaDecision = $adapter->checkQuota(
            tenantId: 42,
            quota: 'run_count',
            requestedUnits: 1000,
        );

        $this->assertTrue($featureDecision->allowed);
        $this->assertSame('community', $featureDecision->meta['edition']);
        $this->assertTrue($quotaDecision->allowed);
        $this->assertNull($quotaDecision->limit);
        $this->assertNull($quotaDecision->used);
    }

    public function test_community_usage_capabilities_are_explicit_and_stable(): void
    {
        $resolver = $this->app->make(UsageCapabilityResolverInterface::class);
        $capabilities = $resolver->forTenant(42);

        $this->assertTrue($capabilities->supports('run_count'));
        $this->assertTrue($capabilities->supports('input_tokens'));
        $this->assertTrue($capabilities->supports('output_tokens'));
        $this->assertTrue($capabilities->supports('active_members'));
        $this->assertFalse($capabilities->supports('storage_bytes'));
    }

    public function test_bindings_can_be_overridden_by_cloud_package(): void
    {
        $this->app->bind(EntitlementServiceInterface::class, CloudEntitlementServiceFake::class);
        $this->app->bind(UsageCapabilityResolverInterface::class, CloudUsageCapabilityResolverFake::class);

        $entitlements = $this->app->make(EntitlementServiceInterface::class);
        $capabilities = $this->app->make(UsageCapabilityResolverInterface::class);

        $this->assertInstanceOf(CloudEntitlementServiceFake::class, $entitlements);
        $this->assertInstanceOf(CloudUsageCapabilityResolverFake::class, $capabilities);
    }
}

final class CloudEntitlementServiceFake implements EntitlementServiceInterface
{
    public function checkFeatureAccess(int $tenantId, string $feature, array $context = []): EntitlementDecision
    {
        return EntitlementDecision::deny('blocked_by_cloud_policy');
    }

    public function checkQuota(int $tenantId, string $quota, int $requestedUnits = 1, array $context = []): QuotaDecision
    {
        return QuotaDecision::deny(10, 10, 'quota_exceeded');
    }
}

final class CloudUsageCapabilityResolverFake implements UsageCapabilityResolverInterface
{
    public function forTenant(int $tenantId): UsageCapabilities
    {
        return new UsageCapabilities([
            'run_count' => true,
        ]);
    }
}
