<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Project;
use App\Models\PromptConversation;
use App\Services\Agents\AgentChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        // Generate AI response
        $reply = $chatService->generateReply($agent, $conversation);

        // Store assistant message
        $assistantMessage = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $reply['content'],
            'meta' => [
                'usage' => $reply['usage'],
            ],
        ]);

        // Update agent analytics
        $agent->recordUsage(
            messages: 2, // user + assistant
            tokensIn: $reply['usage']['prompt_tokens'] ?? 0,
            tokensOut: $reply['usage']['completion_tokens'] ?? 0
        );

        // Check if approaching context limit
        $approachingLimit = $chatService->isApproachingContextLimit($agent, $conversation);

        return response()->json([
            'user_message' => [
                'id' => $userMessage->id,
                'role' => $userMessage->role,
                'content' => $userMessage->content,
                'created_at' => $userMessage->created_at?->toISOString(),
            ],
            'assistant_message' => [
                'id' => $assistantMessage->id,
                'role' => $assistantMessage->role,
                'content' => $assistantMessage->content,
                'meta' => $assistantMessage->meta,
                'created_at' => $assistantMessage->created_at?->toISOString(),
            ],
            'approaching_context_limit' => $approachingLimit,
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
