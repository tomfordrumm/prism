<?php

namespace App\Services\Runs;

use App\Models\ChainNode;
use App\Models\Run;
use App\Models\RunStep;
use App\Services\Llm\LlmResponseDto;

class RunStepRecorder
{
    public function record(
        Run $run,
        ChainNode $node,
        array $messages,
        array $params,
        ?LlmResponseDto $responseDto,
        ?array $parsedOutput,
        array $validationErrors,
        string $status,
        int $durationMs,
        ?int $promptVersionId = null,
        ?int $providerCredentialId = null,
        ?int $systemPromptVersionId = null,
        ?int $userPromptVersionId = null
    ): void {
        $usage = $responseDto ? $responseDto->usage : [];
        $meta = $responseDto ? $responseDto->meta : [];

        RunStep::create([
            'tenant_id' => currentTenantId(),
            'run_id' => $run->id,
            'chain_node_id' => $node->id,
            'provider_credential_id' => $providerCredentialId,
            'prompt_version_id' => $promptVersionId,
            'system_prompt_version_id' => $systemPromptVersionId,
            'user_prompt_version_id' => $userPromptVersionId,
            'order_index' => $node->order_index,
            'request_payload' => [
                'model' => $node->model_name,
                'params' => $params,
                'messages' => $messages,
            ],
            'response_raw' => $responseDto ? $responseDto->raw : [],
            'response_content' => $responseDto ? $responseDto->content : null,
            'parsed_output' => $parsedOutput,
            'tokens_in' => $usage['tokens_in'] ?? null,
            'tokens_out' => $usage['tokens_out'] ?? null,
            'duration_ms' => $durationMs,
            'retry_count' => $meta['retry_count'] ?? null,
            'retry_reasons' => $meta['retry_reasons'] ?? null,
            'validation_errors' => $validationErrors ?: null,
            'status' => $status,
        ]);
    }
}
