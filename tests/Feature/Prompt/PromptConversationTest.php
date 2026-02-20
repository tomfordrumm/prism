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

        $project = Project::factory()->create([
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

        $project = Project::factory()->create([
            'tenant_id' => currentTenantId(),
            'name' => 'Chat Project',
        ]);

        $this->mock(PromptConversationLlmService::class, function ($mock) {
            $mock->shouldReceive('buildRequestSnapshot')
                ->once()
                ->andReturn([
                    'provider_credential_id' => 1,
                    'model_name' => 'gpt-5.1-mini',
                    'model_params' => [],
                    'messages' => [
                        ['role' => 'system', 'content' => 'System'],
                        ['role' => 'user', 'content' => 'Hello there'],
                    ],
                ]);
            $mock->shouldReceive('generateReplyFromSnapshot')
                ->once()
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

    public function test_prompt_message_failure_persists_failed_assistant_message(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $project = Project::factory()->create([
            'tenant_id' => currentTenantId(),
            'name' => 'Chat Project',
        ]);

        $snapshot = [
            'provider_credential_id' => 1,
            'model_name' => 'gpt-5.1-mini',
            'model_params' => [],
            'messages' => [
                ['role' => 'system', 'content' => 'System'],
                ['role' => 'user', 'content' => 'Hello there'],
            ],
        ];

        $this->mock(PromptConversationLlmService::class, function ($mock) use ($snapshot) {
            $mock->shouldReceive('buildRequestSnapshot')
                ->once()
                ->andReturn($snapshot);
            $mock->shouldReceive('generateReplyFromSnapshot')
                ->once()
                ->andThrow(new \RuntimeException('Gemini high demand'));
        });

        $conversationResponse = $this->postJson("/projects/{$project->uuid}/prompt-conversations", [
            'type' => 'idea',
        ]);

        $conversationId = $conversationResponse->json('conversation.id');

        $response = $this->postJson(
            "/projects/{$project->uuid}/prompt-conversations/{$conversationId}/messages",
            ['content' => 'Hello there']
        );

        $response->assertOk();
        $response->assertJsonPath('assistant_message.meta.status', 'failed');
        $response->assertJsonPath('assistant_message.meta.retry.count', 0);
        $response->assertJsonMissingPath('assistant_message.meta.request_snapshot');

        $assistant = PromptMessage::query()
            ->where('conversation_id', $conversationId)
            ->where('role', 'assistant')
            ->latest('id')
            ->first();

        $this->assertNotNull($assistant);
        $meta = is_array($assistant->meta) ? $assistant->meta : [];
        $this->assertSame($snapshot, $meta['request_snapshot'] ?? null);
    }

    public function test_prompt_message_with_array_assistant_content_is_serialized(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $project = Project::factory()->create([
            'tenant_id' => currentTenantId(),
            'name' => 'Chat Project',
        ]);

        $this->mock(PromptConversationLlmService::class, function ($mock) {
            $mock->shouldReceive('buildRequestSnapshot')
                ->once()
                ->andReturn([
                    'provider_credential_id' => 1,
                    'model_name' => 'gpt-5.1-mini',
                    'model_params' => [],
                    'messages' => [
                        ['role' => 'system', 'content' => 'System'],
                        ['role' => 'user', 'content' => 'Hello there'],
                    ],
                ]);
            $mock->shouldReceive('generateReplyFromSnapshot')
                ->once()
                ->andReturn([
                    'assistant_content' => ['text' => 'Array payload'],
                    'analysis' => null,
                    'suggested_prompt' => null,
                    'usage' => [],
                    'raw' => [],
                ]);
        });

        $conversationResponse = $this->postJson("/projects/{$project->uuid}/prompt-conversations", [
            'type' => 'idea',
        ]);
        $conversationId = $conversationResponse->json('conversation.id');

        $response = $this->postJson(
            "/projects/{$project->uuid}/prompt-conversations/{$conversationId}/messages",
            ['content' => 'Hello there']
        );

        $response->assertOk();
        $response->assertJsonPath('assistant_message.content', '{"text":"Array payload"}');
    }

    public function test_failed_prompt_message_can_be_retried_successfully(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $project = Project::factory()->create([
            'tenant_id' => currentTenantId(),
            'name' => 'Chat Project',
        ]);

        $conversation = PromptConversation::create([
            'tenant_id' => currentTenantId(),
            'project_id' => $project->id,
            'type' => 'idea',
            'status' => 'active',
        ]);

        $failed = PromptMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'I could not complete that request. Try again.',
            'meta' => [
                'status' => 'failed',
                'error_message' => 'Gemini high demand',
                'retry' => [
                    'count' => 0,
                    'last_attempt_at' => now()->subMinute()->toISOString(),
                ],
                'request_snapshot' => [
                    'provider_credential_id' => 1,
                    'model_name' => 'gpt-5.1-mini',
                    'model_params' => [],
                    'messages' => [
                        ['role' => 'system', 'content' => 'System'],
                        ['role' => 'user', 'content' => 'Hello there'],
                    ],
                ],
            ],
        ]);

        $this->mock(PromptConversationLlmService::class, function ($mock) {
            $mock->shouldReceive('generateReplyFromSnapshot')
                ->once()
                ->andReturn([
                    'assistant_content' => 'Recovered answer',
                    'analysis' => null,
                    'suggested_prompt' => null,
                    'usage' => [],
                    'raw' => [],
                ]);
        });

        $response = $this->postJson(
            "/projects/{$project->uuid}/prompt-conversations/{$conversation->id}/messages/{$failed->id}/retry"
        );

        $response->assertOk();
        $response->assertJsonPath('assistant_message.id', $failed->id);
        $response->assertJsonPath('assistant_message.content', 'Recovered answer');
        $response->assertJsonPath('assistant_message.meta.status', 'success');
        $response->assertJsonPath('assistant_message.meta.retry.count', 1);
    }

    public function test_prompt_retry_on_non_assistant_message_returns_422(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $project = Project::factory()->create([
            'tenant_id' => currentTenantId(),
            'name' => 'Chat Project',
        ]);

        $conversation = PromptConversation::create([
            'tenant_id' => currentTenantId(),
            'project_id' => $project->id,
            'type' => 'idea',
            'status' => 'active',
        ]);

        $message = PromptMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Hello',
            'meta' => ['status' => 'failed'],
        ]);

        $response = $this->postJson(
            "/projects/{$project->uuid}/prompt-conversations/{$conversation->id}/messages/{$message->id}/retry"
        );

        $response->assertStatus(422);
    }

    public function test_prompt_retry_on_non_failed_message_returns_422(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $project = Project::factory()->create([
            'tenant_id' => currentTenantId(),
            'name' => 'Chat Project',
        ]);

        $conversation = PromptConversation::create([
            'tenant_id' => currentTenantId(),
            'project_id' => $project->id,
            'type' => 'idea',
            'status' => 'active',
        ]);

        $message = PromptMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Done',
            'meta' => ['status' => 'success'],
        ]);

        $response = $this->postJson(
            "/projects/{$project->uuid}/prompt-conversations/{$conversation->id}/messages/{$message->id}/retry"
        );

        $response->assertStatus(422);
    }

    public function test_prompt_retry_with_missing_snapshot_returns_422(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $project = Project::factory()->create([
            'tenant_id' => currentTenantId(),
            'name' => 'Chat Project',
        ]);

        $conversation = PromptConversation::create([
            'tenant_id' => currentTenantId(),
            'project_id' => $project->id,
            'type' => 'idea',
            'status' => 'active',
        ]);

        $message = PromptMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Retry me',
            'meta' => [
                'status' => 'failed',
                'retry' => [
                    'count' => 0,
                    'last_attempt_at' => now()->subMinute()->toISOString(),
                ],
                'request_snapshot' => null,
            ],
        ]);

        $response = $this->postJson(
            "/projects/{$project->uuid}/prompt-conversations/{$conversation->id}/messages/{$message->id}/retry"
        );

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Retry snapshot is unavailable.');
    }

    public function test_prompt_retry_failure_keeps_failed_status_and_increments_retry_count(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $project = Project::factory()->create([
            'tenant_id' => currentTenantId(),
            'name' => 'Chat Project',
        ]);

        $conversation = PromptConversation::create([
            'tenant_id' => currentTenantId(),
            'project_id' => $project->id,
            'type' => 'idea',
            'status' => 'active',
        ]);

        $message = PromptMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Retry me',
            'meta' => [
                'status' => 'failed',
                'retry' => [
                    'count' => 0,
                    'last_attempt_at' => now()->subMinute()->toISOString(),
                ],
                'request_snapshot' => [
                    'provider_credential_id' => 1,
                    'model_name' => 'gpt-5.1-mini',
                    'model_params' => [],
                    'messages' => [
                        ['role' => 'system', 'content' => 'System'],
                        ['role' => 'user', 'content' => 'Hello there'],
                    ],
                ],
            ],
        ]);

        $this->mock(PromptConversationLlmService::class, function ($mock) {
            $mock->shouldReceive('generateReplyFromSnapshot')
                ->once()
                ->andThrow(new \RuntimeException('Still overloaded'));
        });

        $response = $this->postJson(
            "/projects/{$project->uuid}/prompt-conversations/{$conversation->id}/messages/{$message->id}/retry"
        );

        $response->assertOk();
        $response->assertJsonPath('assistant_message.meta.status', 'failed');
        $response->assertJsonPath('assistant_message.meta.retry.count', 1);
        $response->assertJsonPath('assistant_message.meta.error_message', 'Still overloaded');
    }

    public function test_user_cannot_access_other_tenant_conversation(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $project = Project::factory()->create([
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
