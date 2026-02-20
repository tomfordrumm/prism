<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Project;
use App\Models\PromptConversation;
use App\Models\PromptMessage;
use App\Models\ProviderCredential;
use App\Models\User;
use App\Services\Agents\AgentChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class AgentConversationRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_failure_creates_failed_assistant_message_with_retry_metadata(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        [$project, $agent, $conversation] = $this->createAgentConversation();

        $snapshot = [
            'provider_credential_id' => $agent->provider_credential_id,
            'model_name' => $agent->model_name,
            'model_params' => ['temperature' => 0.4],
            'messages' => [
                ['role' => 'system', 'content' => 'System prompt'],
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ];

        $this->mock(AgentChatService::class, function ($mock) use ($snapshot): void {
            $mock->shouldReceive('buildRequestSnapshot')
                ->once()
                ->andReturn($snapshot);
            $mock->shouldReceive('generateReplyFromSnapshot')
                ->once()
                ->andThrow(new RuntimeException('Gemini timeout'));
        });

        $response = $this->postJson(
            "/projects/{$project->uuid}/agents/{$agent->id}/conversations/{$conversation->id}/messages",
            ['content' => 'Hello']
        );

        $response->assertOk();
        $response->assertJsonPath('assistant_message.meta.status', 'failed');
        $response->assertJsonPath('assistant_message.meta.retry.count', 0);

        $this->assertDatabaseCount('prompt_messages', 2);

        $assistant = PromptMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->first();

        $this->assertNotNull($assistant);
        $meta = is_array($assistant->meta) ? $assistant->meta : [];
        $this->assertSame('failed', $meta['status'] ?? null);
        $this->assertSame(0, data_get($meta, 'retry.count'));
        $this->assertSame($snapshot, $meta['request_snapshot'] ?? null);
    }

    public function test_retry_success_updates_same_assistant_message_without_duplication(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        [$project, $agent, $conversation] = $this->createAgentConversation();

        $snapshot = [
            'provider_credential_id' => $agent->provider_credential_id,
            'model_name' => $agent->model_name,
            'model_params' => ['temperature' => 0.4],
            'messages' => [
                ['role' => 'system', 'content' => 'System prompt'],
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ];

        $assistant = PromptMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'I could not complete that request. Try again.',
            'meta' => [
                'status' => 'failed',
                'error_message' => 'Gemini timeout',
                'retry' => [
                    'count' => 0,
                    'last_attempt_at' => now()->subMinute()->toISOString(),
                ],
                'request_snapshot' => $snapshot,
            ],
        ]);

        $this->mock(AgentChatService::class, function ($mock) use ($snapshot): void {
            $mock->shouldReceive('generateReplyFromSnapshot')
                ->once()
                ->with($snapshot)
                ->andReturn([
                    'content' => 'Recovered answer',
                    'usage' => [
                        'prompt_tokens' => 10,
                        'completion_tokens' => 3,
                        'total_tokens' => 13,
                    ],
                ]);
        });

        $response = $this->postJson(
            "/projects/{$project->uuid}/agents/{$agent->id}/conversations/{$conversation->id}/messages/{$assistant->id}/retry"
        );

        $response->assertOk();
        $response->assertJsonPath('assistant_message.id', $assistant->id);
        $response->assertJsonPath('assistant_message.content', 'Recovered answer');

        $assistant->refresh();
        $meta = is_array($assistant->meta) ? $assistant->meta : [];

        $this->assertSame('success', $meta['status'] ?? null);
        $this->assertSame(1, data_get($meta, 'retry.count'));
        $this->assertSame(13, data_get($meta, 'usage.total_tokens'));

        $this->assertSame(1, PromptMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->count());
    }

    public function test_retry_failure_updates_same_message_and_increments_count(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        [$project, $agent, $conversation] = $this->createAgentConversation();

        $snapshot = [
            'provider_credential_id' => $agent->provider_credential_id,
            'model_name' => $agent->model_name,
            'model_params' => ['temperature' => 0.4],
            'messages' => [
                ['role' => 'system', 'content' => 'System prompt'],
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ];

        $assistant = PromptMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'I could not complete that request. Try again.',
            'meta' => [
                'status' => 'failed',
                'error_message' => 'Gemini timeout',
                'retry' => [
                    'count' => 0,
                    'last_attempt_at' => now()->subMinute()->toISOString(),
                ],
                'request_snapshot' => $snapshot,
            ],
        ]);

        $this->mock(AgentChatService::class, function ($mock) use ($snapshot): void {
            $mock->shouldReceive('generateReplyFromSnapshot')
                ->once()
                ->with($snapshot)
                ->andThrow(new RuntimeException('Gemini still failing'));
        });

        $response = $this->postJson(
            "/projects/{$project->uuid}/agents/{$agent->id}/conversations/{$conversation->id}/messages/{$assistant->id}/retry"
        );

        $response->assertOk();
        $response->assertJsonPath('assistant_message.id', $assistant->id);
        $response->assertJsonPath('assistant_message.meta.status', 'failed');
        $response->assertJsonPath('assistant_message.meta.retry.count', 1);

        $assistant->refresh();
        $meta = is_array($assistant->meta) ? $assistant->meta : [];

        $this->assertSame('failed', $meta['status'] ?? null);
        $this->assertSame(1, data_get($meta, 'retry.count'));
        $this->assertSame('Gemini still failing', data_get($meta, 'error_message'));
    }

    public function test_concurrent_retry_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        [$project, $agent, $conversation] = $this->createAgentConversation();

        $assistant = PromptMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'I could not complete that request. Try again.',
            'meta' => [
                'status' => 'failed',
                'error_message' => 'Gemini timeout',
                'retry' => [
                    'count' => 0,
                    'last_attempt_at' => now()->subMinute()->toISOString(),
                ],
                'request_snapshot' => [
                    'provider_credential_id' => $agent->provider_credential_id,
                    'model_name' => $agent->model_name,
                    'model_params' => ['temperature' => 0.4],
                    'messages' => [
                        ['role' => 'system', 'content' => 'System prompt'],
                        ['role' => 'user', 'content' => 'Hello'],
                    ],
                ],
            ],
        ]);

        $lock = Cache::lock("agent-chat-retry:message:{$assistant->id}", 10);
        $this->assertTrue($lock->get());

        try {
            $response = $this->postJson(
                "/projects/{$project->uuid}/agents/{$agent->id}/conversations/{$conversation->id}/messages/{$assistant->id}/retry"
            );

            $response->assertStatus(409);
            $response->assertJsonPath('assistant_message.id', $assistant->id);
            $response->assertJsonPath('assistant_message.meta.retry.count', 0);
        } finally {
            $lock->release();
        }
    }

    public function test_retry_on_non_assistant_message_returns_422(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        [$project, $agent, $conversation] = $this->createAgentConversation();

        $message = PromptMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'User message',
            'meta' => [
                'status' => 'failed',
            ],
        ]);

        $response = $this->postJson(
            "/projects/{$project->uuid}/agents/{$agent->id}/conversations/{$conversation->id}/messages/{$message->id}/retry"
        );

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Only assistant messages can be retried.');
    }

    public function test_retry_on_non_failed_message_returns_422(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        [$project, $agent, $conversation] = $this->createAgentConversation();

        $message = PromptMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Done',
            'meta' => [
                'status' => 'success',
            ],
        ]);

        $response = $this->postJson(
            "/projects/{$project->uuid}/agents/{$agent->id}/conversations/{$conversation->id}/messages/{$message->id}/retry"
        );

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Only failed assistant messages can be retried.');
    }

    public function test_retry_with_missing_request_snapshot_returns_422(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        [$project, $agent, $conversation] = $this->createAgentConversation();

        $message = PromptMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'I could not complete that request. Try again.',
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
            "/projects/{$project->uuid}/agents/{$agent->id}/conversations/{$conversation->id}/messages/{$message->id}/retry"
        );

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Retry snapshot is unavailable.');
        $response->assertJsonPath('assistant_message.meta.retry.count', 1);
    }

    /**
     * @return array{0: Project, 1: Agent, 2: PromptConversation}
     */
    private function createAgentConversation(): array
    {
        $project = Project::factory()->create([
            'tenant_id' => currentTenantId(),
            'name' => 'Agent Project',
        ]);

        $credential = ProviderCredential::factory()->create([
            'tenant_id' => currentTenantId(),
            'provider' => 'openai',
            'name' => 'OpenAI',
        ]);

        $agent = Agent::factory()->create([
            'tenant_id' => currentTenantId(),
            'project_id' => $project->id,
            'name' => 'Content Buddy',
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

        $conversation = PromptConversation::factory()->create([
            'tenant_id' => currentTenantId(),
            'project_id' => $project->id,
            'agent_id' => $agent->id,
            'type' => 'agent_chat',
            'status' => 'active',
        ]);

        return [$project, $agent, $conversation];
    }
}
