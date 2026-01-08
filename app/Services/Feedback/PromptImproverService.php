<?php

namespace App\Services\Feedback;

use App\Models\PromptVersion;
use App\Models\ProviderCredential;
use App\Models\Tenant;
use App\Models\RunStep;
use App\Services\Llm\LlmService;
use App\Services\Prompts\PromptFileRenderer;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PromptImproverService
{
    public function __construct(
        private LlmService $llmService,
        private PromptFileRenderer $promptFileRenderer,
        private PromptImprovementParser $promptImprovementParser
    ) {
    }

    public function suggest(
        RunStep $runStep,
        PromptVersion $targetPromptVersion,
        string $comment,
        ?int $providerCredentialId = null,
        ?string $modelName = null
    ): ?array {
        $defaults = $this->resolveTenantDefaults();
        $credential = $this->resolveCredential($providerCredentialId ?? $defaults['provider_credential_id']);

        if (! $credential) {
            Log::warning('PromptImproverService: no judge credential found');

            return null;
        }

        $modelName = $modelName ?: $defaults['model_name'] ?: config('llm.judge_model', 'gpt-5.1-mini');
        $params = $this->judgeParams();

        $messages = $this->buildMessages($runStep, $targetPromptVersion, $comment);

        try {
            $response = $this->llmService->call($credential, $modelName, $messages, $params);

            return $this->promptImprovementParser->parse($response->content);
        } catch (\Throwable $e) {
            Log::error('PromptImproverService failed', [
                'error' => $e->getMessage(),
                'run_step_id' => $runStep->id,
            ]);

            throw new RuntimeException($e->getMessage() ?: 'Failed to fetch suggestion');
        }
    }

    private function buildMessages(RunStep $runStep, PromptVersion $targetPromptVersion, string $comment): array
    {
        $requestPayload = (array) ($runStep->request_payload ?? []);
        $messages = $requestPayload['messages'] ?? [];

        $systemPromptUsed = $this->extractMessageContent($messages, 'system');
        $userPromptUsed = $this->extractMessageContent($messages, 'user');
        $modelResponse = $this->extractModelResponse($runStep);

        $systemPrompt = $this->promptFileRenderer->render('improver_system_prompt');
        $userPrompt = $this->promptFileRenderer->render('improver_user_message', [
            'sys_prompt' => $systemPromptUsed ?: '',
            'user_prompt' => $userPromptUsed ?: $targetPromptVersion->content,
            'model_response' => $modelResponse,
            'customer_comment' => $comment,
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

        return $this->resolveJudgeCredential();
    }

    private function resolveJudgeCredential(): ?ProviderCredential
    {
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

    private function extractMessageContent(mixed $messages, string $role): ?string
    {
        if (! is_array($messages)) {
            return null;
        }

        foreach ($messages as $message) {
            if (is_array($message) && ($message['role'] ?? null) === $role) {
                $content = $message['content'] ?? null;

                if (is_string($content)) {
                    return $content;
                }

                if (is_array($content)) {
                    return json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
        }

        return null;
    }

    private function extractModelResponse(RunStep $runStep): string
    {
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
