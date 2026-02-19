<?php

namespace App\Services\Entitlements;

use App\Services\Entitlements\Contracts\EntitlementServiceInterface;
use Illuminate\Validation\ValidationException;

class EntitlementEnforcer
{
    public const FEATURE_CAN_CREATE_PROJECT = 'canCreateProject';

    public const FEATURE_CAN_INVITE_MEMBER = 'canInviteMember';

    public const FEATURE_CAN_RUN_CHAIN = 'canRunChain';

    public const QUOTA_PROJECT_COUNT = 'project_count';

    public const QUOTA_ACTIVE_MEMBERS = 'active_members';

    public const QUOTA_RUN_COUNT = 'run_count';

    public function __construct(
        private EntitlementServiceInterface $entitlements
    ) {}

    public function ensureCanCreateProject(int $tenantId): void
    {
        $this->enforceFeature(
            tenantId: $tenantId,
            feature: self::FEATURE_CAN_CREATE_PROJECT,
            message: 'Your workspace cannot create more projects on the current plan.',
        );

        $this->enforceQuota(
            tenantId: $tenantId,
            quota: self::QUOTA_PROJECT_COUNT,
            requestedUnits: 1,
            message: 'Project limit reached for your workspace.',
        );
    }

    public function ensureCanInviteMember(int $tenantId): void
    {
        $this->enforceFeature(
            tenantId: $tenantId,
            feature: self::FEATURE_CAN_INVITE_MEMBER,
            message: 'Your workspace cannot add more members on the current plan.',
        );

        $this->enforceQuota(
            tenantId: $tenantId,
            quota: self::QUOTA_ACTIVE_MEMBERS,
            requestedUnits: 1,
            message: 'Member limit reached for your workspace.',
        );
    }

    public function ensureCanRunChain(int $tenantId, int $requestedRuns = 1): void
    {
        $this->enforceFeature(
            tenantId: $tenantId,
            feature: self::FEATURE_CAN_RUN_CHAIN,
            message: 'Your workspace cannot run chains on the current plan.',
        );

        $this->enforceQuota(
            tenantId: $tenantId,
            quota: self::QUOTA_RUN_COUNT,
            requestedUnits: $requestedRuns,
            message: 'Run limit reached for your workspace.',
        );
    }

    private function enforceFeature(int $tenantId, string $feature, string $message): void
    {
        $this->validateTenantId($tenantId);

        $decision = $this->entitlements->checkFeatureAccess(
            tenantId: $tenantId,
            feature: $feature,
            context: ['tenant_id' => $tenantId],
        );

        if (! $decision->allowed) {
            throw ValidationException::withMessages([
                'entitlements' => $message,
            ]);
        }
    }

    private function enforceQuota(int $tenantId, string $quota, int $requestedUnits, string $message): void
    {
        $this->validateTenantId($tenantId);

        $decision = $this->entitlements->checkQuota(
            tenantId: $tenantId,
            quota: $quota,
            requestedUnits: max(1, $requestedUnits),
            context: ['tenant_id' => $tenantId],
        );

        if (! $decision->allowed) {
            throw ValidationException::withMessages([
                'entitlements' => $message,
            ]);
        }
    }

    private function validateTenantId(int $tenantId): void
    {
        if ($tenantId <= 0) {
            throw ValidationException::withMessages([
                'tenant_id' => 'Invalid tenant id',
            ]);
        }
    }
}
