<?php

namespace Tests\Feature\Entitlements;

use App\Models\Project;
use App\Models\User;
use App\Services\Entitlements\Contracts\EntitlementServiceInterface;
use App\Services\Entitlements\EntitlementDecision;
use App\Services\Entitlements\QuotaDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectLimitEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_project_when_entitled(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post(route('projects.store'), [
            'name' => 'Allowed Project',
            'description' => 'Demo',
        ]);

        $project = Project::query()->where('name', 'Allowed Project')->first();

        $this->assertNotNull($project);
        $response->assertRedirect(route('projects.show', $project));
    }

    public function test_user_cannot_create_project_when_feature_is_denied(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->app->bind(EntitlementServiceInterface::class, DenyProjectCreationEntitlementsFake::class);

        $response = $this->from(route('projects.create'))->post(route('projects.store'), [
            'name' => 'Denied Project',
            'description' => 'Blocked',
        ]);

        $response->assertRedirect(route('projects.create'));
        $response->assertSessionHasErrors('entitlements');
        $this->assertDatabaseMissing('projects', ['name' => 'Denied Project']);
    }
}

final class DenyProjectCreationEntitlementsFake implements EntitlementServiceInterface
{
    public function checkFeatureAccess(int $tenantId, string $feature, array $context = []): EntitlementDecision
    {
        if ($feature === 'canCreateProject') {
            return EntitlementDecision::deny('project_creation_blocked');
        }

        return EntitlementDecision::allow();
    }

    public function checkQuota(int $tenantId, string $quota, int $requestedUnits = 1, array $context = []): QuotaDecision
    {
        return QuotaDecision::allowUnlimited();
    }
}
