<?php

namespace Tests\Unit\Services\Entitlements;

use App\Services\Entitlements\Contracts\EntitlementServiceInterface;
use App\Services\Entitlements\Contracts\UsageCapabilityResolverInterface;
use App\Services\Entitlements\EntitlementDecision;
use App\Services\Entitlements\QuotaDecision;
use App\Services\Entitlements\UsageCapabilities;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class EntitlementContractsTest extends TestCase
{
    public function test_entitlement_service_interface_uses_tenant_scoped_signatures(): void
    {
        $featureMethod = new ReflectionMethod(EntitlementServiceInterface::class, 'checkFeatureAccess');
        $this->assertSame('tenantId', $featureMethod->getParameters()[0]->getName());
        $this->assertSame('int', (string) $featureMethod->getParameters()[0]->getType());
        $this->assertSame(EntitlementDecision::class, (string) $featureMethod->getReturnType());

        $quotaMethod = new ReflectionMethod(EntitlementServiceInterface::class, 'checkQuota');
        $this->assertSame('tenantId', $quotaMethod->getParameters()[0]->getName());
        $this->assertSame('int', (string) $quotaMethod->getParameters()[0]->getType());
        $this->assertSame(QuotaDecision::class, (string) $quotaMethod->getReturnType());
    }

    public function test_usage_capability_resolver_is_tenant_scoped(): void
    {
        $method = new ReflectionMethod(UsageCapabilityResolverInterface::class, 'forTenant');
        $this->assertSame('tenantId', $method->getParameters()[0]->getName());
        $this->assertSame('int', (string) $method->getParameters()[0]->getType());
        $this->assertSame(UsageCapabilities::class, (string) $method->getReturnType());
    }

    public function test_entitlement_decision_has_stable_allow_and_deny_shapes(): void
    {
        $allow = EntitlementDecision::allow(['source' => 'community']);
        $this->assertTrue($allow->allowed);
        $this->assertNull($allow->reason);
        $this->assertSame(['source' => 'community'], $allow->meta);

        $deny = EntitlementDecision::deny('plan_missing', ['feature' => 'runs.execute']);
        $this->assertFalse($deny->allowed);
        $this->assertSame('plan_missing', $deny->reason);
        $this->assertSame(['feature' => 'runs.execute'], $deny->meta);
    }

    public function test_quota_decision_exposes_normalized_limit_data(): void
    {
        $unlimited = QuotaDecision::allowUnlimited(['quota' => 'run_count']);
        $this->assertTrue($unlimited->allowed);
        $this->assertNull($unlimited->limit);
        $this->assertNull($unlimited->used);
        $this->assertNull($unlimited->remaining());

        $withinLimit = QuotaDecision::allowWithinLimit(10, 3);
        $this->assertTrue($withinLimit->allowed);
        $this->assertSame(7, $withinLimit->remaining());

        $denied = QuotaDecision::deny(10, 12, 'quota_exceeded');
        $this->assertFalse($denied->allowed);
        $this->assertSame('quota_exceeded', $denied->reason);
        $this->assertSame(0, $denied->remaining());
    }

    public function test_usage_capabilities_expose_meter_support_map(): void
    {
        $capabilities = new UsageCapabilities([
            'run_count' => true,
            'storage_bytes' => false,
        ]);

        $this->assertTrue($capabilities->supports('run_count'));
        $this->assertFalse($capabilities->supports('storage_bytes'));
        $this->assertFalse($capabilities->supports('input_tokens'));
        $this->assertSame(
            ['run_count' => true, 'storage_bytes' => false],
            $capabilities->all(),
        );
    }
}
