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

    public function test_registration_succeeds_when_entitlements_and_quota_allow(): void
    {
        $this->app->bind(EntitlementServiceInterface::class, AllowRegistrationEntitlementsFake::class);
        $password = 'Aa1!'.fake()->unique()->bothify('######??');
        $email = fake()->unique()->safeEmail();

        $response = $this->post(route('register.store'), [
            'name' => fake()->name(),
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertSessionDoesntHaveErrors('entitlements');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => $email]);
    }

    public function test_registration_is_blocked_when_member_invite_is_denied(): void
    {
        $this->app->bind(EntitlementServiceInterface::class, DenyInviteMemberEntitlementsFake::class);
        $password = 'Aa1!'.fake()->unique()->bothify('######??');

        $response = $this->from(route('register'))->post(route('register.store'), [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('entitlements');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_is_blocked_when_quota_exceeded_for_active_members(): void
    {
        $this->app->bind(EntitlementServiceInterface::class, DenyActiveMembersQuotaEntitlementsFake::class);
        $password = 'Aa1!'.fake()->unique()->bothify('######??');

        $response = $this->from(route('register'))->post(route('register.store'), [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('entitlements');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }
}

final class AllowRegistrationEntitlementsFake implements EntitlementServiceInterface
{
    public function checkFeatureAccess(int $tenantId, string $feature, array $context = []): EntitlementDecision
    {
        return EntitlementDecision::allow();
    }

    public function checkQuota(int $tenantId, string $quota, int $requestedUnits = 1, array $context = []): QuotaDecision
    {
        return QuotaDecision::allowUnlimited();
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

final class DenyActiveMembersQuotaEntitlementsFake implements EntitlementServiceInterface
{
    public function checkFeatureAccess(int $tenantId, string $feature, array $context = []): EntitlementDecision
    {
        return EntitlementDecision::allow();
    }

    public function checkQuota(int $tenantId, string $quota, int $requestedUnits = 1, array $context = []): QuotaDecision
    {
        if ($quota === 'active_members') {
            return QuotaDecision::deny(1, 1, 'active_members_quota_exceeded');
        }

        return QuotaDecision::allowUnlimited();
    }
}
