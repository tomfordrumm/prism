<?php

namespace App\Services\Entitlements;

use App\Services\Entitlements\Contracts\EntitlementServiceInterface;
use Illuminate\Validation\ValidationException;

class EntitlementEnforcer
{
    public function __construct(
        private EntitlementServiceInterface $entitlements
    ) {}

    public function ensureCanCreateProject(int $tenantId): void
    {
        $this->enforceFeature(
            tenantId: $tenantId,
            feature: 'canCreateProject',
            message: 'Your workspace cannot create more projects on the current plan.',
        );

        $this->enforceQuota(
            tenantId: $tenantId,
            quota: 'project_count',
            requestedUnits: 1,
            message: 'Project limit reached for your workspace.',
        );
    }

    public function ensureCanInviteMember(int $tenantId): void
    {
        $this->enforceFeature(
            tenantId: $tenantId,
            feature: 'canInviteMember',
            message: 'Your workspace cannot add more members on the current plan.',
        );

        $this->enforceQuota(
            tenantId: $tenantId,
            quota: 'active_members',
            requestedUnits: 1,
            message: 'Member limit reached for your workspace.',
        );
    }

    public function ensureCanRunChain(int $tenantId, int $requestedRuns = 1): void
    {
        $this->enforceFeature(
            tenantId: $tenantId,
            feature: 'canRunChain',
            message: 'Your workspace cannot run chains on the current plan.',
        );

        $this->enforceQuota(
            tenantId: $tenantId,
            quota: 'run_count',
            requestedUnits: $requestedRuns,
            message: 'Run limit reached for your workspace.',
        );
    }

    private function enforceFeature(int $tenantId, string $feature, string $message): void
    {
        if ($tenantId <= 0) {
            throw ValidationException::withMessages([
                'tenant_id' => 'Invalid tenant id',
            ]);
        }

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
        if ($tenantId <= 0) {
            throw ValidationException::withMessages([
                'tenant_id' => 'Invalid tenant id',
            ]);
        }

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
}
