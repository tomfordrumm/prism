<?php

namespace Tests\Unit\Services\Entitlements;

use App\Services\Entitlements\Contracts\EntitlementServiceInterface;
use App\Services\Entitlements\EntitlementDecision;
use App\Services\Entitlements\EntitlementEnforcer;
use App\Services\Entitlements\QuotaDecision;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EntitlementEnforcerCompatibilityTest extends TestCase
{
    public function test_enforcer_uses_stable_feature_and_quota_contract_keys(): void
    {
        $spy = new EntitlementContractSpy;
        $enforcer = new EntitlementEnforcer($spy);

        $enforcer->ensureCanCreateProject(tenantId: 11);
        $enforcer->ensureCanInviteMember(tenantId: 11);
        $enforcer->ensureCanRunChain(tenantId: 11, requestedRuns: 3);

        $this->assertSame([
            ['tenant_id' => 11, 'feature' => EntitlementEnforcer::FEATURE_CAN_CREATE_PROJECT, 'context' => ['tenant_id' => 11]],
            ['tenant_id' => 11, 'feature' => EntitlementEnforcer::FEATURE_CAN_INVITE_MEMBER, 'context' => ['tenant_id' => 11]],
            ['tenant_id' => 11, 'feature' => EntitlementEnforcer::FEATURE_CAN_RUN_CHAIN, 'context' => ['tenant_id' => 11]],
        ], $spy->featureChecks);

        $this->assertSame([
            ['tenant_id' => 11, 'quota' => EntitlementEnforcer::QUOTA_PROJECT_COUNT, 'requested_units' => 1, 'context' => ['tenant_id' => 11]],
            ['tenant_id' => 11, 'quota' => EntitlementEnforcer::QUOTA_ACTIVE_MEMBERS, 'requested_units' => 1, 'context' => ['tenant_id' => 11]],
            ['tenant_id' => 11, 'quota' => EntitlementEnforcer::QUOTA_RUN_COUNT, 'requested_units' => 3, 'context' => ['tenant_id' => 11]],
        ], $spy->quotaChecks);
    }

    public function test_enforcer_normalizes_requested_runs_to_minimum_of_one(): void
    {
        $spy = new EntitlementContractSpy;
        $enforcer = new EntitlementEnforcer($spy);

        $enforcer->ensureCanRunChain(tenantId: 20, requestedRuns: 0);

        $this->assertSame(1, $spy->quotaChecks[0]['requested_units']);
    }

    public function test_enforcer_throws_entitlements_validation_error_on_denied_quota(): void
    {
        $spy = new EntitlementContractSpy;
        $spy->quotaDecision = QuotaDecision::deny(
            limit: 100,
            used: 100,
            reason: 'quota_exceeded',
        );

        $enforcer = new EntitlementEnforcer($spy);

        try {
            $enforcer->ensureCanRunChain(tenantId: 9, requestedRuns: 1);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('entitlements', $exception->errors());
        }
    }

    public function test_enforcer_throws_entitlements_validation_error_on_denied_feature(): void
    {
        $spy = new EntitlementContractSpy;
        $spy->featureDecision = EntitlementDecision::deny('feature_denied');

        $enforcer = new EntitlementEnforcer($spy);

        try {
            $enforcer->ensureCanRunChain(tenantId: 9, requestedRuns: 1);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('entitlements', $exception->errors());
        }
    }

    public function test_enforcer_throws_tenant_id_error_for_invalid_tenant(): void
    {
        $spy = new EntitlementContractSpy;
        $enforcer = new EntitlementEnforcer($spy);

        try {
            $enforcer->ensureCanCreateProject(tenantId: 0);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('tenant_id', $exception->errors());
        }
    }
}

final class EntitlementContractSpy implements EntitlementServiceInterface
{
    /**
     * @var array<int, array{tenant_id: int, feature: string, context: array<string, mixed>}>
     */
    public array $featureChecks = [];

    /**
     * @var array<int, array{tenant_id: int, quota: string, requested_units: int, context: array<string, mixed>}>
     */
    public array $quotaChecks = [];

    public EntitlementDecision $featureDecision;

    public QuotaDecision $quotaDecision;

    public function __construct()
    {
        $this->featureDecision = EntitlementDecision::allow();
        $this->quotaDecision = QuotaDecision::allowUnlimited();
    }

    public function checkFeatureAccess(int $tenantId, string $feature, array $context = []): EntitlementDecision
    {
        $this->featureChecks[] = [
            'tenant_id' => $tenantId,
            'feature' => $feature,
            'context' => $context,
        ];

        return $this->featureDecision;
    }

    public function checkQuota(int $tenantId, string $quota, int $requestedUnits = 1, array $context = []): QuotaDecision
    {
        $this->quotaChecks[] = [
            'tenant_id' => $tenantId,
            'quota' => $quota,
            'requested_units' => $requestedUnits,
            'context' => $context,
        ];

        return $this->quotaDecision;
    }
}
