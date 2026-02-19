<?php

namespace App\Services\Agents;

use App\Models\Agent;
use App\Models\PromptConversation;
use App\Models\ProviderCredential;
use App\Services\Llm\LlmService;
use RuntimeException;

class AgentChatService
{
    public function __construct(
        private LlmService $llmService
    ) {}

    /**
     * Generate a reply from the agent based on conversation history
     */
    public function generateReply(Agent $agent, PromptConversation $conversation): array
    {
        return $this->generateReplyFromSnapshot(
            $this->buildRequestSnapshot($agent, $conversation)
        );
    }

    /**
     * @return array{
     *     provider_credential_id: int,
     *     model_name: string,
     *     model_params: array<string, mixed>,
     *     messages: array<int, array{role: string, content: string}>
     * }
     */
    public function buildRequestSnapshot(Agent $agent, PromptConversation $conversation): array
    {
        $providerCredentialId = $agent->provider_credential_id;

        if (! $providerCredentialId) {
            throw new RuntimeException('No provider credential configured for agent.');
        }

        return [
            'provider_credential_id' => $providerCredentialId,
            'model_name' => $agent->model_name,
            'model_params' => $agent->model_params ?? [],
            'messages' => $this->buildMessages($agent, $conversation),
        ];
    }

    /**
     * @param  array{
     *     provider_credential_id: int,
     *     model_name: string,
     *     model_params: array<string, mixed>,
     *     messages: array<int, array{role: string, content: string}>
     * }  $snapshot
     * @return array{
     *     content: string,
     *     usage: array{
     *         prompt_tokens: int,
     *         completion_tokens: int,
     *         total_tokens: int
     *     }
     * }
     */
    public function generateReplyFromSnapshot(array $snapshot): array
    {
        $credentialId = data_get($snapshot, 'provider_credential_id');
        $modelName = data_get($snapshot, 'model_name');
        $modelParams = data_get($snapshot, 'model_params', []);
        $messages = data_get($snapshot, 'messages', []);

        if (! is_int($credentialId) || $credentialId <= 0) {
            throw new RuntimeException('Retry snapshot is missing provider credential.');
        }

        if (! is_string($modelName) || $modelName === '') {
            throw new RuntimeException('Retry snapshot is missing model name.');
        }

        if (! is_array($modelParams)) {
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

        $response = $this->llmService->call(
            $credential,
            $modelName,
            $messages,
            $modelParams
        );

        return [
            'content' => $response->content,
            'usage' => [
                'prompt_tokens' => $response->tokensIn() ?? 0,
                'completion_tokens' => $response->tokensOut() ?? 0,
                'total_tokens' => ($response->tokensIn() ?? 0) + ($response->tokensOut() ?? 0),
            ],
        ];
    }

    /**
     * Build the messages array for the LLM call
     */
    private function buildMessages(Agent $agent, PromptConversation $conversation): array
    {
        $messages = [];

        // System prompt
        $systemPrompt = $agent->getSystemPrompt();
        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        // Conversation history (limited to max_context_messages)
        $history = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at', 'desc')
            ->limit($agent->max_context_messages)
            ->get()
            ->reverse(); // Reverse to get chronological order

        foreach ($history as $message) {
            $messages[] = [
                'role' => $message->role,
                'content' => $message->content,
            ];
        }

        return $messages;
    }

    /**
     * Check if conversation is approaching context limit
     */
    public function isApproachingContextLimit(Agent $agent, PromptConversation $conversation): bool
    {
        $messageCount = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->count();

        $threshold = (int) ($agent->max_context_messages * 0.8);

        return $messageCount >= $threshold;
    }
}
