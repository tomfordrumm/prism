<?php

namespace App\Services\Feedback;

use App\Models\Feedback;
use App\Models\PromptVersion;
use App\Models\RunStep;
use App\Services\Llm\LlmService;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PromptImproverService
{
    public function __construct(private LlmService $llmService)
    {
    }

    public function suggest(
        RunStep $runStep,
        PromptVersion $targetPromptVersion,
        string $comment
    ): ?string {
        $credential = $this->resolveJudgeCredential();

        if (! $credential) {
            Log::warning('PromptImproverService: no judge credential found');

            return null;
        }

        $modelName = config('llm.judge_model', 'gpt-5.1-mini');
        $params = $this->judgeParams();

        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a prompt engineer assistant. Improve the given prompt to address the feedback while keeping intent.',
            ],
            [
                'role' => 'user',
                'content' => $this->buildPayload($targetPromptVersion, $runStep, $comment),
            ],
        ];

        try {
            $response = $this->llmService->call($credential, $modelName, $messages, $params);

            return $response->content;
        } catch (\Throwable $e) {
            Log::error('PromptImproverService failed', [
                'error' => $e->getMessage(),
                'run_step_id' => $runStep->id,
            ]);

            return null;
        }
    }

    private function buildPayload(PromptVersion $promptVersion, RunStep $runStep, string $comment): string
    {
        /** @var \App\Models\Run|null $run */
        $run = $runStep->run;
        $input = $run ? $run->input : [];

        /** @var array $response */
        $response = $runStep->response_raw ?? [];
        $answer = $response['choices'][0]['message']['content'] ?? '';

        return <<<TXT
Current prompt:
{$promptVersion->content}

User feedback:
{$comment}

Input variables:
{$this->prettyJson($input)}

Model answer:
{$answer}

Return an improved prompt text only.
TXT;
    }

    private function prettyJson(mixed $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT) ?: (string) $value;
    }

    private function resolveJudgeCredential()
    {
        $credentialId = config('llm.judge_credential_id');

        $query = \App\Models\ProviderCredential::query()
            ->where('tenant_id', currentTenantId());

        if ($credentialId) {
            $query->where('id', $credentialId);
        } else {
            $query->where('provider', 'openai');
        }

        return $query->first();
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
