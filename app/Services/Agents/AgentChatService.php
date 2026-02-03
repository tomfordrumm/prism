<?php

namespace App\Services\Agents;

use App\Models\Agent;
use App\Models\PromptConversation;
use App\Services\Llm\LlmService;

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
        // Build message history (respecting max_context_messages)
        $messages = $this->buildMessages($agent, $conversation);

        // Call LLM
        $response = $this->llmService->call(
            $agent->providerCredential,
            $agent->model_name,
            $messages,
            $agent->model_params ?? []
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
