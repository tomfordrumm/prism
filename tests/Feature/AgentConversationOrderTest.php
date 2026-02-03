<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Project;
use App\Models\PromptConversation;
use App\Models\PromptMessage;
use App\Models\ProviderCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentConversationOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_conversation_messages_are_returned_oldest_first(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $project = Project::create([
            'tenant_id' => currentTenantId(),
            'name' => 'Agent Project',
        ]);

        $credential = ProviderCredential::create([
            'tenant_id' => currentTenantId(),
            'provider' => 'openai',
            'name' => 'OpenAI',
            'encrypted_api_key' => 'test-key',
        ]);

        $agent = Agent::create([
            'tenant_id' => currentTenantId(),
            'project_id' => $project->id,
            'name' => 'Content Buddy',
            'description' => 'Assistant',
            'system_prompt_content' => 'System',
            'system_prompt_mode' => 'inline',
            'system_prompt_template_id' => null,
            'system_prompt_version_id' => null,
            'system_inline_content' => 'System',
            'provider_credential_id' => $credential->id,
            'model_name' => 'gpt-4o-mini',
            'model_params' => ['temperature' => 0.7],
            'max_context_messages' => 20,
        ]);

        $conversation = PromptConversation::create([
            'tenant_id' => currentTenantId(),
            'project_id' => $project->id,
            'agent_id' => $agent->id,
            'type' => 'agent_chat',
            'status' => 'active',
        ]);

        $first = PromptMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'First message',
            'created_at' => now()->subMinute(),
        ]);

        $second = PromptMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Second message',
            'created_at' => now(),
        ]);

        $response = $this->getJson(
            "/projects/{$project->uuid}/agents/{$agent->id}/conversations/{$conversation->id}"
        );

        $response->assertOk();
        $response->assertJsonPath('messages.0.id', $first->id);
        $response->assertJsonPath('messages.1.id', $second->id);
    }
}
