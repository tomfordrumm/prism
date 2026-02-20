<?php

namespace App\Http\Controllers;

use App\Http\Requests\PromptConversations\StorePromptConversationRequest;
use App\Http\Requests\PromptConversations\StorePromptMessageRequest;
use App\Models\Project;
use App\Models\PromptConversation;
use App\Models\PromptMessage;
use App\Services\Prompts\PromptConversationLlmService;
use App\Services\Prompts\PromptConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

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

        $snapshot = null;

        try {
            $snapshot = $this->llmService->buildRequestSnapshot($conversation);
            $reply = $this->llmService->generateReplyFromSnapshot($snapshot);

            $assistantMeta = array_filter([
                'status' => 'success',
                'analysis' => $reply['analysis'] ?? null,
                'suggested_prompt' => $reply['suggested_prompt'] ?? null,
                'usage' => $reply['usage'] ?? null,
                'retry' => [
                    'count' => 0,
                    'last_attempt_at' => now()->toISOString(),
                ],
            ], fn ($value) => $value !== null);

            $assistantMessage = $this->conversationService->appendMessage(
                $conversation,
                'assistant',
                $this->normalizeAssistantContent($reply['assistant_content'] ?? ''),
                $assistantMeta
            );

            return response()->json([
                'user_message' => $this->serializeMessage($userMessage),
                'assistant_message' => $this->serializeMessage($assistantMessage),
            ]);
        } catch (Throwable $exception) {
            $sanitizedError = $this->sanitizeErrorMessage($exception);
            Log::error('PromptConversationController::storeMessage failed', [
                'sanitized_message' => $sanitizedError,
                'exception_class' => get_class($exception),
                'conversation_id' => $conversation->id,
                'project_id' => $project->id,
                'user_id' => auth()->id(),
            ]);

            $assistantMessage = $this->conversationService->appendMessage(
                $conversation,
                'assistant',
                'I could not complete that request. Try again.',
                [
                    'status' => 'failed',
                    'error_message' => $sanitizedError,
                    'retry' => [
                        'count' => 0,
                        'last_attempt_at' => now()->toISOString(),
                    ],
                    'request_snapshot' => is_array($snapshot) ? $snapshot : null,
                ]
            );

            return response()->json([
                'user_message' => $this->serializeMessage($userMessage),
                'assistant_message' => $this->serializeMessage($assistantMessage),
            ]);
        }
    }

    public function retryMessage(
        Project $project,
        PromptConversation $conversation,
        PromptMessage $message
    ): JsonResponse {
        $this->assertConversationProject($project, $conversation);
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

        $lock = Cache::lock("prompt-chat-retry:message:{$message->id}", 60);
        if (! $lock->get()) {
            return response()->json([
                'message' => 'A retry is already in progress for this message.',
                'assistant_message' => $this->serializeMessage($message),
            ], 409);
        }

        try {
            $message->refresh();
            $latestMeta = is_array($message->meta) ? $message->meta : [];
            if ($message->role !== 'assistant' || ($latestMeta['status'] ?? null) !== 'failed') {
                return response()->json([
                    'message' => 'A retry is already in progress for this message.',
                    'assistant_message' => $this->serializeMessage($message),
                ], 409);
            }

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
                $reply = $this->llmService->generateReplyFromSnapshot($snapshot);

                $latestMeta['status'] = 'success';
                $latestMeta['error_message'] = null;
                $latestMeta['analysis'] = $reply['analysis'] ?? null;
                $latestMeta['suggested_prompt'] = $reply['suggested_prompt'] ?? null;
                $latestMeta['usage'] = $reply['usage'] ?? null;
                $latestMeta['retry'] = [
                    'count' => $nextRetryCount,
                    'last_attempt_at' => $attemptedAt,
                ];
                $latestMeta['request_snapshot'] = $snapshot;

                $message->content = $this->normalizeAssistantContent($reply['assistant_content'] ?? '');
                $message->meta = $latestMeta;
                $message->save();
            } catch (Throwable $exception) {
                $sanitizedError = $this->sanitizeErrorMessage($exception);
                Log::error('PromptConversationController::retryMessage failed', [
                    'sanitized_message' => $sanitizedError,
                    'exception_class' => get_class($exception),
                    'conversation_id' => $conversation->id,
                    'message_id' => $message->id,
                    'project_id' => $project->id,
                    'user_id' => auth()->id(),
                ]);

                $latestMeta['status'] = 'failed';
                $latestMeta['error_message'] = $sanitizedError;
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

    private function assertConversationProject(Project $project, PromptConversation $conversation): void
    {
        if ($conversation->project_id !== $project->id || $conversation->tenant_id !== currentTenantId()) {
            abort(404);
        }
    }

    private function assertConversationMessage(PromptConversation $conversation, PromptMessage $message): void
    {
        if ($message->conversation_id !== $conversation->id) {
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

    private function serializeMessage(PromptMessage $message): array
    {
        $meta = is_array($message->meta) ? $message->meta : null;
        if (is_array($meta)) {
            unset($meta['request_snapshot']);
        }

        return [
            'id' => $message->id,
            'role' => $message->role,
            'content' => $message->content,
            'meta' => $meta,
            'created_at' => $message->created_at?->toISOString(),
        ];
    }

    private function normalizeAssistantContent(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        if (is_array($content) || is_object($content)) {
            return json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        if ($content === null) {
            return '';
        }

        return (string) $content;
    }

    private function sanitizeErrorMessage(Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        return $message !== '' ? mb_substr($message, 0, 300) : 'Unknown model call error.';
    }
}
