<?php

namespace App\Services\Prompts;

use App\Models\PromptConversation;
use App\Models\ProviderCredential;
use App\Models\RunStep;
use App\Models\Tenant;
use App\Services\Feedback\PromptImprovementParser;
use App\Services\Llm\LlmResponseDto;
use App\Services\Llm\LlmService;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PromptConversationLlmService
{
    public function __construct(
        private LlmService $llmService,
        private PromptFileRenderer $promptFileRenderer,
        private PromptImprovementParser $promptImprovementParser
    ) {}

    public function generateReply(PromptConversation $conversation): array
    {
        $snapshot = $this->buildRequestSnapshot($conversation);

        try {
            $response = $this->generateReplyFromSnapshot($snapshot);
        } catch (\Throwable $e) {
            Log::error('PromptConversationLlmService failed', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversation->id,
            ]);

            throw new RuntimeException($e->getMessage() ?: 'Failed to fetch suggestion');
        }

        return $response;
    }

    /**
     * @return array{
     *     provider_credential_id: int,
     *     model_name: string,
     *     model_params: array<string, mixed>,
     *     messages: array<int, array{role: string, content: string}>
     * }
     */
    public function buildRequestSnapshot(PromptConversation $conversation): array
    {
        $defaults = $this->resolveTenantDefaults();
        $credential = $this->resolveCredential($defaults['provider_credential_id'] ?? null);

        if (! $credential) {
            Log::warning('PromptConversationLlmService: no judge credential found', [
                'conversation_id' => $conversation->id,
            ]);

            throw new RuntimeException('No provider credential configured for chat.');
        }

        $modelName = $defaults['model_name'] ?: config('llm.judge_model', 'gpt-5.1-mini');

        return [
            'provider_credential_id' => $credential->id,
            'model_name' => $modelName,
            'model_params' => $this->judgeParams(),
            'messages' => $this->buildMessages($conversation),
        ];
    }

    /**
     * @param  array{
     *     provider_credential_id: int,
     *     model_name: string,
     *     model_params: array<string, mixed>,
     *     messages: array<int, array{role: string, content: string}>
     * }  $snapshot
     */
    public function generateReplyFromSnapshot(array $snapshot): array
    {
        $credentialId = data_get($snapshot, 'provider_credential_id');
        $modelName = data_get($snapshot, 'model_name');
        $params = data_get($snapshot, 'model_params', []);
        $messages = data_get($snapshot, 'messages', []);

        if (! is_int($credentialId) || $credentialId <= 0) {
            throw new RuntimeException('Retry snapshot is missing provider credential.');
        }

        if (! is_string($modelName) || $modelName === '') {
            throw new RuntimeException('Retry snapshot is missing model name.');
        }

        if (! is_array($params)) {
            throw new RuntimeException('Retry snapshot model params are invalid.');
        }

        if (! is_array($messages)) {
            throw new RuntimeException('Retry snapshot messages are invalid.');
        }

        $credential = ProviderCredential::query()
            ->where('tenant_id', currentTenantId())
            ->find($credentialId);

        if (! $credential) {
            throw new RuntimeException('Retry provider credential is unavailable.');
        }

        $response = $this->llmService->call($credential, $modelName, $messages, $params);

        return $this->parseResponse($response);
    }

    private function buildMessages(PromptConversation $conversation): array
    {
        $conversation->loadMissing(['messages', 'runStep', 'targetPromptVersion']);

        $systemPrompt = $this->buildSystemPrompt($conversation);

        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
        ];

        $contextMessage = $this->buildContextMessage($conversation);
        if ($contextMessage) {
            $messages[] = $contextMessage;
        }

        foreach ($conversation->messages()->orderBy('created_at')->get() as $message) {
            $messages[] = [
                'role' => $message->role,
                'content' => $message->content,
            ];
        }

        return $messages;
    }

    private function buildSystemPrompt(PromptConversation $conversation): string
    {
        return match ($conversation->type) {
            'run_feedback' => $this->promptFileRenderer->render('improver_system_prompt'),
            default => $this->promptFileRenderer->render('idea_to_prompt_system'),
        };
    }

    private function buildContextMessage(PromptConversation $conversation): ?array
    {
        if ($conversation->type !== 'run_feedback') {
            return null;
        }

        $runStep = $conversation->runStep;
        $targetPromptVersion = $conversation->targetPromptVersion;

        if (! $runStep && ! $targetPromptVersion) {
            return null;
        }

        $systemPromptUsed = $this->extractMessageContent($runStep, 'system');
        $userPromptUsed = $this->extractMessageContent($runStep, 'user');
        $modelResponse = $this->extractModelResponse($runStep);

        $context = $this->promptFileRenderer->render('improver_user_message', [
            'sys_prompt' => $systemPromptUsed ?: '',
            'user_prompt' => $userPromptUsed ?: ($targetPromptVersion?->content ?? ''),
            'model_response' => $modelResponse,
            'customer_comment' => 'User feedback will follow in subsequent messages.',
        ]);

        return [
            'role' => 'system',
            'content' => $context,
        ];
    }

    private function parseResponse(LlmResponseDto $response): array
    {
        $parsed = $this->promptImprovementParser->parse($response->content);
        $analysis = $parsed['analysis'] ?? null;
        $suggestion = $parsed['suggestion'] ?? null;

        $assistantContent = $analysis ?: $suggestion ?: $response->content;

        return [
            'assistant_content' => $assistantContent,
            'analysis' => $analysis,
            'suggested_prompt' => $suggestion,
            'usage' => $response->usage,
            'raw' => $response->raw,
        ];
    }

    private function resolveCredential(?int $credentialId): ?ProviderCredential
    {
        if ($credentialId) {
            return ProviderCredential::query()
                ->where('tenant_id', currentTenantId())
                ->find($credentialId);
        }

        $credentialId = config('llm.judge_credential_id');

        $query = ProviderCredential::query()
            ->where('tenant_id', currentTenantId());

        if ($credentialId) {
            $query->where('id', $credentialId);
        } else {
            $query->where('provider', 'openai');
        }

        return $query->first();
    }

    private function resolveTenantDefaults(): array
    {
        $tenant = Tenant::query()->find(currentTenantId());

        return [
            'provider_credential_id' => $tenant?->improvement_provider_credential_id,
            'model_name' => $tenant?->improvement_model_name,
        ];
    }

    private function judgeParams(): array
    {
        $params = config('llm.judge_params', []);

        if (is_string($params)) {
            $decoded = json_decode($params, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($params) ? $params : [];
    }

    private function extractMessageContent(?RunStep $runStep, string $role): ?string
    {
        if (! $runStep) {
            return null;
        }

        $payload = (array) ($runStep->request_payload ?? []);
        $messages = $payload['messages'] ?? null;
        if (! is_array($messages)) {
            return null;
        }

        foreach ($messages as $message) {
            if (! is_array($message) || ($message['role'] ?? null) !== $role) {
                continue;
            }

            $content = $message['content'] ?? null;

            if (is_string($content)) {
                return $content;
            }

            if (is_array($content)) {
                return json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        return null;
    }

    private function extractModelResponse(?RunStep $runStep): string
    {
        if (! $runStep) {
            return '';
        }

        if (is_string($runStep->response_content) && $runStep->response_content !== '') {
            return $runStep->response_content;
        }

        $raw = $runStep->response_raw ?? [];

        if (is_array($raw)) {
            $content = $raw['choices'][0]['message']['content'] ?? null;
            if (! $content) {
                $content = $raw['content'] ?? null;
            }

            if (is_string($content)) {
                return $content;
            }

            if (is_array($content)) {
                return json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
            }
        }

        if (is_string($raw)) {
            return $raw;
        }

        return '';
    }
}
