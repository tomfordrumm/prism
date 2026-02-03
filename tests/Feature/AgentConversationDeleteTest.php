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

class AgentConversationDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_conversation_decrements_agent_totals(): void
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
            'total_conversations' => 1,
            'total_messages' => 2,
            'total_tokens_in' => 3,
            'total_tokens_out' => 2,
        ]);

        $conversation = PromptConversation::create([
            'tenant_id' => currentTenantId(),
            'project_id' => $project->id,
            'agent_id' => $agent->id,
            'type' => 'agent_chat',
            'status' => 'active',
        ]);

        PromptMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Hello',
        ]);

        PromptMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Hi there',
            'meta' => [
                'usage' => [
                    'prompt_tokens' => 3,
                    'completion_tokens' => 2,
                ],
            ],
        ]);

        $response = $this->deleteJson(
            "/projects/{$project->uuid}/agents/{$agent->id}/conversations/{$conversation->id}"
        );

        $response->assertOk();

        $this->assertDatabaseMissing('prompt_conversations', [
            'id' => $conversation->id,
        ]);

        $this->assertDatabaseMissing('prompt_messages', [
            'conversation_id' => $conversation->id,
        ]);

        $agent->refresh();

        $this->assertSame(0, $agent->total_conversations);
        $this->assertSame(0, $agent->total_messages);
        $this->assertSame(0, $agent->total_tokens_in);
        $this->assertSame(0, $agent->total_tokens_out);
    }
}
