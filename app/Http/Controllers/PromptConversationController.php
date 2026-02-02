<?php

namespace App\Http\Controllers;

use App\Http\Requests\PromptConversations\StorePromptConversationRequest;
use App\Http\Requests\PromptConversations\StorePromptMessageRequest;
use App\Models\Project;
use App\Models\PromptConversation;
use App\Services\Prompts\PromptConversationLlmService;
use App\Services\Prompts\PromptConversationService;
use Illuminate\Http\JsonResponse;

class PromptConversationController extends Controller
{
    public function __construct(
        private PromptConversationService $conversationService,
        private PromptConversationLlmService $llmService
    ) {}

    public function index(Project $project): JsonResponse
    {
        $type = request()->query('type');
        if (! is_string($type) || $type === '') {
            return response()->json(['error' => 'Type parameter is required'], 400);
        }

        $conversations = $this->conversationService->listConversations($project, $type);

        return response()->json([
            'conversations' => $conversations->map(fn ($conversation) => [
                'id' => $conversation->id,
                'type' => $conversation->type,
                'status' => $conversation->status,
                'created_at' => $conversation->created_at?->toISOString(),
                'updated_at' => $conversation->updated_at?->toISOString(),
            ]),
        ]);
    }

    public function store(StorePromptConversationRequest $request, Project $project): JsonResponse
    {
        $forceNew = $request->boolean('force_new');

        if ($forceNew) {
            $conversation = $this->conversationService->createNewConversation(
                $project,
                $request->input('type'),
                $request->integer('run_id') ?: null,
                $request->integer('run_step_id') ?: null,
                $request->integer('target_prompt_version_id') ?: null
            );
        } else {
            $conversation = $this->conversationService->getOrCreate($project, [
                'type' => $request->input('type'),
                'run_id' => $request->integer('run_id') ?: null,
                'run_step_id' => $request->integer('run_step_id') ?: null,
                'target_prompt_version_id' => $request->integer('target_prompt_version_id') ?: null,
            ]);
        }

        $initialMessage = $request->input('initial_message');
        if (is_string($initialMessage) && $initialMessage !== '') {
            $this->conversationService->appendMessage($conversation, 'user', $initialMessage);
        }

        return response()->json([
            'conversation' => $this->serializeConversation($conversation),
            'messages' => $this->conversationService->messages($conversation)->map(
                fn ($message) => $this->serializeMessage($message)
            ),
        ]);
    }

    public function show(Project $project, PromptConversation $conversation): JsonResponse
    {
        $this->assertConversationProject($project, $conversation);

        return response()->json([
            'conversation' => $this->serializeConversation($conversation),
            'messages' => $this->conversationService->messages($conversation)->map(
                fn ($message) => $this->serializeMessage($message)
            ),
        ]);
    }

    public function storeMessage(
        StorePromptMessageRequest $request,
        Project $project,
        PromptConversation $conversation
    ): JsonResponse {
        $this->assertConversationProject($project, $conversation);

        $userMessage = $this->conversationService->appendMessage(
            $conversation,
            'user',
            $request->string('content')
        );

        $reply = $this->llmService->generateReply($conversation);

        $assistantMeta = array_filter([
            'analysis' => $reply['analysis'] ?? null,
            'suggested_prompt' => $reply['suggested_prompt'] ?? null,
            'usage' => $reply['usage'] ?? null,
        ]);

        $assistantMessage = $this->conversationService->appendMessage(
            $conversation,
            'assistant',
            (string) ($reply['assistant_content'] ?? ''),
            $assistantMeta
        );

        return response()->json([
            'user_message' => $this->serializeMessage($userMessage),
            'assistant_message' => $this->serializeMessage($assistantMessage),
        ]);
    }

    private function assertConversationProject(Project $project, PromptConversation $conversation): void
    {
        if ($conversation->project_id !== $project->id || $conversation->tenant_id !== currentTenantId()) {
            abort(404);
        }
    }

    private function serializeConversation(PromptConversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'status' => $conversation->status,
            'project_id' => $conversation->project_id,
            'run_id' => $conversation->run_id,
            'run_step_id' => $conversation->run_step_id,
            'target_prompt_version_id' => $conversation->target_prompt_version_id,
            'created_at' => $conversation->created_at?->toISOString(),
            'updated_at' => $conversation->updated_at?->toISOString(),
        ];
    }

    private function serializeMessage($message): array
    {
        return [
            'id' => $message->id,
            'role' => $message->role,
            'content' => $message->content,
            'meta' => $message->meta,
            'created_at' => $message->created_at?->toISOString(),
        ];
    }
}
