<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Project;
use App\Models\PromptConversation;
use App\Models\PromptMessage;
use App\Services\Agents\AgentChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class AgentConversationController extends Controller
{
    public function index(Project $project, Agent $agent): JsonResponse
    {
        $this->assertAgentProject($agent, $project);

        $conversations = $agent->conversations()
            ->orderBy('updated_at', 'desc')
            ->get(['id', 'created_at', 'updated_at']);

        return response()->json(['conversations' => $conversations]);
    }

    public function store(Project $project, Agent $agent): JsonResponse
    {
        $this->assertAgentProject($agent, $project);

        $conversation = $project->promptConversations()->create([
            'type' => 'agent_chat',
            'agent_id' => $agent->id,
            'status' => 'active',
        ]);

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'type' => $conversation->type,
                'status' => $conversation->status,
                'created_at' => $conversation->created_at?->toISOString(),
                'updated_at' => $conversation->updated_at?->toISOString(),
            ],
            'messages' => [],
        ]);
    }

    public function show(Project $project, Agent $agent, PromptConversation $conversation): JsonResponse
    {
        $this->assertAgentProject($agent, $project);
        $this->assertConversationAgent($conversation, $agent);

        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'type' => $conversation->type,
                'status' => $conversation->status,
                'created_at' => $conversation->created_at?->toISOString(),
                'updated_at' => $conversation->updated_at?->toISOString(),
            ],
            'messages' => $messages->map(fn ($message) => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'meta' => $message->meta,
                'created_at' => $message->created_at?->toISOString(),
            ]),
        ]);
    }

    public function storeMessage(
        Request $request,
        Project $project,
        Agent $agent,
        PromptConversation $conversation,
        AgentChatService $chatService
    ): JsonResponse {
        $this->assertAgentProject($agent, $project);
        $this->assertConversationAgent($conversation, $agent);

        $request->validate([
            'content' => 'required|string|max:10000',
        ]);

        // Store user message
        $userMessage = $conversation->messages()->create([
            'role' => 'user',
            'content' => $request->string('content'),
        ]);

        $snapshot = null;

        try {
            $snapshot = $chatService->buildRequestSnapshot($agent, $conversation);
            $reply = $chatService->generateReplyFromSnapshot($snapshot);

            $assistantMessage = $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $reply['content'],
                'meta' => [
                    'status' => 'success',
                    'retry' => [
                        'count' => 0,
                        'last_attempt_at' => now()->toISOString(),
                    ],
                    'usage' => $reply['usage'],
                ],
            ]);

            $agent->recordUsage(
                messages: 2,
                tokensIn: $reply['usage']['prompt_tokens'] ?? 0,
                tokensOut: $reply['usage']['completion_tokens'] ?? 0
            );

            $approachingLimit = $chatService->isApproachingContextLimit($agent, $conversation);

            return response()->json([
                'user_message' => $this->serializeMessage($userMessage),
                'assistant_message' => $this->serializeMessage($assistantMessage),
                'approaching_context_limit' => $approachingLimit,
            ]);
        } catch (Throwable $exception) {
            $assistantMessage = $conversation->messages()->create([
                'role' => 'assistant',
                'content' => 'I could not complete that request. Try again.',
                'meta' => [
                    'status' => 'failed',
                    'error_message' => $this->sanitizeErrorMessage($exception),
                    'retry' => [
                        'count' => 0,
                        'last_attempt_at' => now()->toISOString(),
                    ],
                    'request_snapshot' => is_array($snapshot) && $snapshot !== [] ? $snapshot : null,
                ],
            ]);

            return response()->json([
                'user_message' => $this->serializeMessage($userMessage),
                'assistant_message' => $this->serializeMessage($assistantMessage),
            ]);
        }
    }

    /**
     * Retry a failed assistant message for an existing agent conversation.
     */
    public function retryMessage(
        Project $project,
        Agent $agent,
        PromptConversation $conversation,
        PromptMessage $message,
        AgentChatService $chatService
    ): JsonResponse {
        $this->assertAgentProject($agent, $project);
        $this->assertConversationAgent($conversation, $agent);
        $this->assertConversationMessage($conversation, $message);

        if ($message->role !== 'assistant') {
            return response()->json([
                'message' => 'Only assistant messages can be retried.',
            ], 422);
        }

        $meta = is_array($message->meta) ? $message->meta : [];
        if (($meta['status'] ?? null) !== 'failed') {
            return response()->json([
                'message' => 'Only failed assistant messages can be retried.',
            ], 422);
        }

        $lock = Cache::lock("agent-chat-retry:message:{$message->id}", 10);

        if (! $lock->get()) {
            return response()->json([
                'message' => 'A retry is already in progress for this message.',
                'assistant_message' => $this->serializeMessage($message),
            ], 409);
        }

        try {
            $latestMeta = is_array($message->meta) ? $message->meta : [];
            $snapshot = $latestMeta['request_snapshot'] ?? null;
            $nextRetryCount = (int) data_get($latestMeta, 'retry.count', 0) + 1;
            $attemptedAt = now()->toISOString();

            if (! is_array($snapshot)) {
                $latestMeta['status'] = 'failed';
                $latestMeta['error_message'] = 'Retry snapshot is missing or invalid.';
                $latestMeta['retry'] = [
                    'count' => $nextRetryCount,
                    'last_attempt_at' => $attemptedAt,
                ];
                $message->meta = $latestMeta;
                $message->save();

                return response()->json([
                    'message' => 'Retry snapshot is unavailable.',
                    'assistant_message' => $this->serializeMessage($message->fresh()),
                ], 422);
            }

            try {
                $reply = $chatService->generateReplyFromSnapshot($snapshot);

                $latestMeta['status'] = 'success';
                $latestMeta['error_message'] = null;
                $latestMeta['usage'] = $reply['usage'];
                $latestMeta['retry'] = [
                    'count' => $nextRetryCount,
                    'last_attempt_at' => $attemptedAt,
                ];
                $latestMeta['request_snapshot'] = $snapshot;

                $message->content = $reply['content'];
                $message->meta = $latestMeta;
                $message->save();

                $agent->recordUsage(
                    messages: 1,
                    tokensIn: $reply['usage']['prompt_tokens'] ?? 0,
                    tokensOut: $reply['usage']['completion_tokens'] ?? 0
                );
            } catch (Throwable $exception) {
                $latestMeta['status'] = 'failed';
                $latestMeta['error_message'] = $this->sanitizeErrorMessage($exception);
                $latestMeta['retry'] = [
                    'count' => $nextRetryCount,
                    'last_attempt_at' => $attemptedAt,
                ];
                $latestMeta['request_snapshot'] = $snapshot;

                $message->meta = $latestMeta;
                $message->save();
            }
        } finally {
            $lock->release();
        }

        return response()->json([
            'assistant_message' => $this->serializeMessage($message->fresh()),
        ]);
    }

    private function assertAgentProject(Agent $agent, Project $project): void
    {
        if ($agent->project_id !== $project->id || $agent->tenant_id !== currentTenantId()) {
            abort(404);
        }
    }

    private function assertConversationAgent(PromptConversation $conversation, Agent $agent): void
    {
        if ($conversation->agent_id !== $agent->id || $conversation->tenant_id !== currentTenantId()) {
            abort(404);
        }
    }

    private function assertConversationMessage(PromptConversation $conversation, PromptMessage $message): void
    {
        $messageConversationTenantId = PromptConversation::query()
            ->whereKey($message->conversation_id)
            ->value('tenant_id');

        if (
            $message->conversation_id !== $conversation->id
            || $messageConversationTenantId !== $conversation->tenant_id
        ) {
            abort(404);
        }
    }

    private function sanitizeErrorMessage(Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        return $message !== '' ? mb_substr($message, 0, 300) : 'Unknown model call error.';
    }

    private function serializeMessage(PromptMessage $message): array
    {
        return [
            'id' => $message->id,
            'role' => $message->role,
            'content' => $message->content,
            'meta' => $message->meta,
            'created_at' => $message->created_at?->toISOString(),
        ];
    }

    public function destroy(Project $project, Agent $agent, PromptConversation $conversation): JsonResponse
    {
        $this->assertAgentProject($agent, $project);
        $this->assertConversationAgent($conversation, $agent);

        DB::transaction(function () use ($agent, $conversation): void {
            $messages = $conversation->messages()->get(['id', 'role', 'meta']);
            $messageCount = $messages->count();
            $tokensIn = 0;
            $tokensOut = 0;

            foreach ($messages as $message) {
                $usage = is_array($message->meta) ? ($message->meta['usage'] ?? null) : null;
                if (! is_array($usage)) {
                    continue;
                }

                $tokensIn += (int) ($usage['prompt_tokens'] ?? 0);
                $tokensOut += (int) ($usage['completion_tokens'] ?? 0);
            }

            $conversation->messages()->delete();
            $conversation->delete();

            $agent->refresh();
            $agent->update([
                'total_conversations' => max(0, (int) $agent->total_conversations - 1),
                'total_messages' => max(0, (int) $agent->total_messages - $messageCount),
                'total_tokens_in' => max(0, (int) $agent->total_tokens_in - $tokensIn),
                'total_tokens_out' => max(0, (int) $agent->total_tokens_out - $tokensOut),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Conversation deleted successfully',
        ]);
    }
}
