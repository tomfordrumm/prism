<?php

namespace Tests\Feature\Entitlements;

use App\Jobs\ExecuteRunJob;
use App\Models\Chain;
use App\Models\ChainNode;
use App\Models\Project;
use App\Models\PromptTemplate;
use App\Models\ProviderCredential;
use App\Models\User;
use App\Services\Entitlements\Contracts\EntitlementServiceInterface;
use App\Services\Entitlements\EntitlementDecision;
use App\Services\Entitlements\QuotaDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class RunLimitEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_chain_run_is_blocked_when_can_run_chain_feature_is_denied(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $this->actingAs($user);
        $this->app->bind(EntitlementServiceInterface::class, DenyRunFeatureEntitlementsFake::class);

        $project = Project::factory()->create([
            'tenant_id' => currentTenantId(),
            'name' => 'Demo',
        ]);

        $credential = ProviderCredential::factory()->create([
            'tenant_id' => currentTenantId(),
            'provider' => 'openai',
            'name' => 'Sandbox',
        ]);

        $chain = Chain::factory()->create([
            'tenant_id' => currentTenantId(),
            'project_id' => $project->id,
            'name' => 'Chain',
        ]);

        ChainNode::factory()->create([
            'tenant_id' => currentTenantId(),
            'chain_id' => $chain->id,
            'name' => 'Step 1',
            'order_index' => 1,
            'provider_credential_id' => $credential->id,
            'model_name' => 'gpt-test',
            'messages_config' => [],
            'model_params' => [],
            'output_schema' => null,
            'stop_on_validation_error' => false,
        ]);

        $response = $this->from(route('projects.chains.show', [$project, $chain]))
            ->post(route('projects.chains.run', [$project, $chain]), [
                'input' => json_encode(['topic' => 'hooks']),
            ]);

        $response->assertRedirect(route('projects.chains.show', [$project, $chain]));
        $response->assertSessionHasErrors('entitlements');
        $this->assertDatabaseCount('runs', 0);
        Bus::assertNotDispatched(ExecuteRunJob::class);
    }

    public function test_prompt_run_is_blocked_when_run_quota_is_denied(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $this->actingAs($user);
        $this->app->bind(EntitlementServiceInterface::class, DenyRunQuotaEntitlementsFake::class);

        $project = Project::factory()->create([
            'tenant_id' => currentTenantId(),
            'name' => 'Demo',
        ]);

        $template = PromptTemplate::factory()->create([
            'tenant_id' => currentTenantId(),
            'project_id' => $project->id,
            'name' => 'Greeting',
            'description' => null,
        ]);

        $template->createNewVersion([
            'content' => 'Hello {{ name }}',
            'changelog' => 'v1',
            'created_by' => $user->id,
        ]);

        $credential = ProviderCredential::factory()->create([
            'tenant_id' => currentTenantId(),
            'provider' => 'anthropic',
            'name' => 'Default',
        ]);

        $response = $this->from(route('projects.prompts.index', $project))
            ->post(route('projects.prompts.run', [$project, $template]), [
                'provider_credential_id' => $credential->id,
                'model_name' => 'claude-test',
                'variables' => json_encode(['name' => 'Ada']),
            ]);

        $response->assertRedirect(route('projects.prompts.index', $project));
        $response->assertSessionHasErrors('entitlements');
        $this->assertDatabaseCount('runs', 0);
        Bus::assertNotDispatched(ExecuteRunJob::class);
    }
}

final class DenyRunFeatureEntitlementsFake implements EntitlementServiceInterface
{
    public function checkFeatureAccess(int $tenantId, string $feature, array $context = []): EntitlementDecision
    {
        if ($feature === 'canRunChain') {
            return EntitlementDecision::deny('run_feature_blocked');
        }

        return EntitlementDecision::allow();
    }

    public function checkQuota(int $tenantId, string $quota, int $requestedUnits = 1, array $context = []): QuotaDecision
    {
        return QuotaDecision::allowUnlimited();
    }
}

final class DenyRunQuotaEntitlementsFake implements EntitlementServiceInterface
{
    public function checkFeatureAccess(int $tenantId, string $feature, array $context = []): EntitlementDecision
    {
        return EntitlementDecision::allow();
    }

    public function checkQuota(int $tenantId, string $quota, int $requestedUnits = 1, array $context = []): QuotaDecision
    {
        if ($quota === 'run_count') {
            return QuotaDecision::deny(100, 100, 'run_quota_exceeded');
        }

        return QuotaDecision::allowUnlimited();
    }
}
