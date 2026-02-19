<?php

namespace Tests\Feature\Entitlements;

use App\Events\UsageMetered;
use App\Jobs\ExecuteRunJob;
use App\Models\Project;
use App\Models\PromptTemplate;
use App\Models\ProviderCredential;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class UsageMeteringEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_prompt_run_emits_run_count_meter_event(): void
    {
        Event::fake([UsageMetered::class]);
        Bus::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

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

        $this->post(route('projects.prompts.run', [$project, $template]), [
            'provider_credential_id' => $credential->id,
            'model_name' => 'claude-test',
            'variables' => json_encode(['name' => 'Ada']),
        ])->assertRedirect();

        Event::assertDispatched(UsageMetered::class, function (UsageMetered $event) use ($project): bool {
            return $event->tenantId === (int) currentTenantId()
                && $event->meter === 'run_count'
                && $event->quantity === 1
                && ($event->context['project_id'] ?? null) === $project->id
                && ($event->context['run_id'] ?? null) !== null;
        });
        Event::assertDispatchedTimes(UsageMetered::class, 1);
        Bus::assertDispatched(ExecuteRunJob::class);
    }

    public function test_tenant_creation_emits_active_members_meter_event(): void
    {
        Event::fake([UsageMetered::class]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('tenants.store'), [
            'name' => fake()->company(),
        ])->assertRedirect(route('projects.index'));

        /** @var Tenant $latestTenant */
        $latestTenant = Tenant::query()->latest('id')->firstOrFail();

        Event::assertDispatched(UsageMetered::class, function (UsageMetered $event) use ($latestTenant, $user): bool {
            return $event->tenantId === $latestTenant->id
                && $event->meter === 'active_members'
                && $event->quantity === 1
                && ($event->context['user_id'] ?? null) === $user->id
                && ($event->context['source'] ?? null) === 'tenant_created';
        });
    }
}
