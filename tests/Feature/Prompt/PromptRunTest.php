<?php

namespace Tests\Feature\Prompt;

use App\Models\Project;
use App\Models\PromptTemplate;
use App\Models\ProviderCredential;
use App\Models\Run;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class PromptRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_run_latest_prompt_version(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Bus::fake();

        $project = Project::create([
            'tenant_id' => currentTenantId(),
            'name' => 'Demo',
        ]);

        $template = PromptTemplate::create([
            'tenant_id' => currentTenantId(),
            'project_id' => $project->id,
            'name' => 'Greeting',
            'description' => null,
        ]);

        $template->createNewVersion([
            'content' => 'Hi {{ name }}',
            'changelog' => 'v1',
            'created_by' => $user->id,
        ]);

        $latest = $template->createNewVersion([
            'content' => 'Hello {{ name }}!',
            'changelog' => 'v2',
            'created_by' => $user->id,
        ]);

        $credential = ProviderCredential::create([
            'tenant_id' => currentTenantId(),
            'provider' => 'anthropic',
            'name' => 'Default',
            'encrypted_api_key' => 'secret',
        ]);

        $response = $this->post(route('projects.prompts.run', [$project, $template]), [
            'provider_credential_id' => $credential->id,
            'model_name' => 'claude-test',
            'variables' => json_encode(['name' => 'Ada']),
        ]);

        $run = Run::latest('id')->first();
        $this->assertNotNull($run);

        $response->assertRedirect(route('projects.runs.show', [$project, $run]));
        $this->assertSame('pending', $run->status);
        $this->assertNull($run->chain_id);
        $this->assertSame($project->id, $run->project_id);
        $this->assertSame($credential->id, $run->chain_snapshot[0]['provider_credential_id']);
        $this->assertSame($latest->id, $run->chain_snapshot[0]['messages_config'][0]['prompt_version_id']);
        $this->assertSame(['name' => 'Ada'], $run->input);
        Bus::assertDispatched(\App\Jobs\ExecuteRunJob::class);
    }
}
