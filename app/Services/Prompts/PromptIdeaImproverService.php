<?php

namespace App\Services\Prompts;

use App\Models\ProviderCredential;
use App\Models\Tenant;
use App\Services\Feedback\PromptImprovementParser;
use App\Services\Llm\LlmService;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PromptIdeaImproverService
{
    public function __construct(
        private LlmService $llmService,
        private PromptFileRenderer $promptFileRenderer,
        private PromptImprovementParser $promptImprovementParser
    ) {
    }

    public function suggest(string $idea): ?array
    {
        $defaults = $this->resolveTenantDefaults();
        $credential = $this->resolveCredential($defaults['provider_credential_id'] ?? null);

        if (! $credential) {
            Log::warning('PromptIdeaImproverService: no judge credential found');

            return null;
        }

        $modelName = $defaults['model_name'] ?: config('llm.judge_model', 'gpt-5.1-mini');
        $params = $this->judgeParams();

        $messages = $this->buildMessages($idea);

        try {
            $response = $this->llmService->call($credential, $modelName, $messages, $params);

            return $this->promptImprovementParser->parse($response->content);
        } catch (\Throwable $e) {
            Log::error('PromptIdeaImproverService failed', [
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException($e->getMessage() ?: 'Failed to fetch suggestion');
        }
    }

    private function buildMessages(string $idea): array
    {
        $systemPrompt = $this->promptFileRenderer->render('idea_to_prompt_system');
        $userPrompt = $this->promptFileRenderer->render('idea_to_prompt_user', [
            'idea' => $idea,
        ]);

        return [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user',
                'content' => $userPrompt,
            ],
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
}
