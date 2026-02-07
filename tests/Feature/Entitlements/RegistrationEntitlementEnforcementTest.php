<?php

namespace Tests\Feature\Entitlements;

use App\Services\Entitlements\Contracts\EntitlementServiceInterface;
use App\Services\Entitlements\EntitlementDecision;
use App\Services\Entitlements\QuotaDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationEntitlementEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_blocked_when_member_invite_is_denied(): void
    {
        $this->app->bind(EntitlementServiceInterface::class, DenyInviteMemberEntitlementsFake::class);

        $response = $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('entitlements');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }
}

final class DenyInviteMemberEntitlementsFake implements EntitlementServiceInterface
{
    public function checkFeatureAccess(int $tenantId, string $feature, array $context = []): EntitlementDecision
    {
        if ($feature === 'canInviteMember') {
            return EntitlementDecision::deny('invite_member_blocked');
        }

        return EntitlementDecision::allow();
    }

    public function checkQuota(int $tenantId, string $quota, int $requestedUnits = 1, array $context = []): QuotaDecision
    {
        return QuotaDecision::allowUnlimited();
    }
}
