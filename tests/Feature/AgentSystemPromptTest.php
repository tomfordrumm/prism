<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Project;
use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use App\Models\ProviderCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentSystemPromptTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_store_uses_latest_prompt_version_when_version_not_provided(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $project = $this->createProject('Agent Project');
        $credential = $this->createProviderCredential();
        $template = $this->createPromptTemplate($project, 'Support Agent Prompt');
        $this->createPromptVersion($template, $user, 1, 'System v1');
        $latestVersion = $this->createPromptVersion($template, $user, 2, 'System v2');

        $response = $this->post("/projects/{$project->uuid}/agents", [
            'name' => 'Support Agent',
            'description' => 'Helpful assistant',
            'system_prompt_mode' => 'template',
            'system_prompt_template_id' => $template->id,
            'system_prompt_version_id' => null,
            'system_inline_content' => null,
            'provider_credential_id' => $credential->id,
            'model_name' => 'gpt-4o-mini',
            'model_params' => ['temperature' => 0.7],
            'max_context_messages' => 20,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('agents', [
            'tenant_id' => currentTenantId(),
            'project_id' => $project->id,
            'system_prompt_mode' => 'template',
            'system_prompt_template_id' => $template->id,
            'system_prompt_version_id' => $latestVersion->id,
            'system_prompt_content' => 'System v2',
        ]);
    }

    public function test_agent_update_uses_latest_prompt_version_when_version_not_provided(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $project = $this->createProject('Agent Project');
        $credential = $this->createProviderCredential();
        $template = $this->createPromptTemplate($project, 'Support Agent Prompt');
        $version1 = $this->createPromptVersion($template, $user, 1, 'System v1');
        $latestVersion = $this->createPromptVersion($template, $user, 2, 'System v2');

        $agent = Agent::create([
            'tenant_id' => currentTenantId(),
            'project_id' => $project->id,
            'name' => 'Support Agent',
            'description' => 'Helpful assistant',
            'system_prompt_content' => 'System v1',
            'system_prompt_mode' => 'template',
            'system_prompt_template_id' => $template->id,
            'system_prompt_version_id' => $version1->id,
            'system_inline_content' => null,
            'provider_credential_id' => $credential->id,
            'model_name' => 'gpt-4o-mini',
            'model_params' => ['temperature' => 0.7],
            'max_context_messages' => 20,
        ]);

        $response = $this->put("/projects/{$project->uuid}/agents/{$agent->id}", [
            'name' => $agent->name,
            'description' => $agent->description,
            'system_prompt_mode' => 'template',
            'system_prompt_template_id' => $template->id,
            'system_prompt_version_id' => null,
            'system_inline_content' => null,
            'provider_credential_id' => $credential->id,
            'model_name' => $agent->model_name,
            'model_params' => ['temperature' => 0.7],
            'max_context_messages' => 20,
            'is_active' => true,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('agents', [
            'id' => $agent->id,
            'system_prompt_version_id' => $latestVersion->id,
            'system_prompt_content' => 'System v2',
        ]);
    }

    private function createProject(string $name): Project
    {
        return Project::create([
            'tenant_id' => currentTenantId(),
            'name' => $name,
        ]);
    }

    private function createProviderCredential(): ProviderCredential
    {
        return ProviderCredential::create([
            'tenant_id' => currentTenantId(),
            'provider' => 'openai',
            'name' => 'OpenAI',
            'encrypted_api_key' => 'test-key',
        ]);
    }

    private function createPromptTemplate(Project $project, string $name): PromptTemplate
    {
        return PromptTemplate::create([
            'tenant_id' => currentTenantId(),
            'project_id' => $project->id,
            'name' => $name,
        ]);
    }

    private function createPromptVersion(
        PromptTemplate $template,
        User $user,
        int $version,
        string $content
    ): PromptVersion {
        return PromptVersion::create([
            'tenant_id' => currentTenantId(),
            'prompt_template_id' => $template->id,
            'version' => $version,
            'content' => $content,
            'created_by' => $user->id,
        ]);
    }
}
