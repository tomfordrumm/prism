<?php

namespace Tests\Feature\Prompt;

use App\Models\Project;
use App\Models\PromptConversation;
use App\Models\PromptMessage;
use App\Models\User;
use App\Services\Prompts\PromptConversationLlmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_prompt_conversation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $project = Project::create([
            'tenant_id' => currentTenantId(),
            'name' => 'Chat Project',
        ]);

        $response = $this->postJson("/projects/{$project->uuid}/prompt-conversations", [
            'type' => 'idea',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('conversation.type', 'idea');

        $this->assertDatabaseHas('prompt_conversations', [
            'project_id' => $project->id,
            'tenant_id' => currentTenantId(),
            'type' => 'idea',
        ]);
    }

    public function test_user_can_post_message_and_receive_reply(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $project = Project::create([
            'tenant_id' => currentTenantId(),
            'name' => 'Chat Project',
        ]);

        $this->mock(PromptConversationLlmService::class, function ($mock) {
            $mock->shouldReceive('generateReply')
                ->andReturn([
                    'assistant_content' => 'Thanks! Here is your prompt.',
                    'analysis' => 'Short analysis.',
                    'suggested_prompt' => 'New prompt',
                    'usage' => [],
                    'raw' => [],
                ]);
        });

        $conversationResponse = $this->postJson("/projects/{$project->uuid}/prompt-conversations", [
            'type' => 'idea',
        ]);

        $conversationId = $conversationResponse->json('conversation.id');
        $this->assertNotNull($conversationId);

        $messageResponse = $this->postJson(
            "/projects/{$project->uuid}/prompt-conversations/{$conversationId}/messages",
            ['content' => 'Hello there']
        );

        $messageResponse
            ->assertOk()
            ->assertJsonPath('user_message.content', 'Hello there')
            ->assertJsonPath('assistant_message.content', 'Thanks! Here is your prompt.');

        $this->assertDatabaseCount('prompt_messages', 2);

        $this->assertDatabaseHas('prompt_messages', [
            'conversation_id' => $conversationId,
            'role' => 'assistant',
            'content' => 'Thanks! Here is your prompt.',
        ]);
    }

    public function test_user_cannot_access_other_tenant_conversation(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $project = Project::create([
            'tenant_id' => currentTenantId(),
            'name' => 'Primary Project',
        ]);

        $conversation = PromptConversation::create([
            'tenant_id' => currentTenantId(),
            'project_id' => $project->id,
            'type' => 'idea',
            'status' => 'active',
        ]);

        PromptMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Hi',
        ]);

        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);

        $this->get("/projects/{$project->uuid}/prompt-conversations/{$conversation->id}")
            ->assertNotFound();
    }
}
