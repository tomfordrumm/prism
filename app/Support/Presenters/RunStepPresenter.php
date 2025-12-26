<?php

namespace App\Support\Presenters;

use App\Models\ChainNode;
use App\Models\Feedback;
use App\Models\ProviderCredential;
use App\Models\RunStep;
use App\Support\TargetPromptResolver;
use Illuminate\Support\Collection;

class RunStepPresenter
{
    public function __construct(private TargetPromptResolver $targetPromptResolver)
    {
    }

    /**
     * @param \Illuminate\Support\Collection<int, \App\Models\PromptVersion> $promptVersions
     */
    public function present(RunStep $step, Collection $promptVersions): array
    {
        /** @var ChainNode|null $chainNode */
        $chainNode = $step->chainNode;
        /** @var ProviderCredential|null $providerCredential */
        $providerCredential = $chainNode?->providerCredential ?? $step->providerCredential;

        $targetPromptVersionId = $step->prompt_version_id
            ? (int) $step->prompt_version_id
            : $this->targetPromptResolver->fromMessagesConfig($chainNode ? $chainNode->messages_config ?? [] : []);
        $targetTemplateId = null;
        $targetPromptContent = null;
        if ($targetPromptVersionId) {
            $version = $promptVersions->get($targetPromptVersionId) ?? $promptVersions->firstWhere('id', $targetPromptVersionId);
            $targetTemplateId = $version?->prompt_template_id;
            $targetPromptContent = $version?->content;
        }

        $modelName = $chainNode?->model_name ?? ($step->request_payload['model'] ?? null);
        $chainNodePayload = null;

        if ($chainNode) {
            $chainNodePayload = [
                'id' => $chainNode->id,
                'name' => $chainNode->name,
                'provider' => $providerCredential?->provider,
                'provider_name' => $providerCredential?->name,
                'model_name' => $chainNode->model_name,
            ];
        } elseif ($providerCredential || $modelName) {
            $chainNodePayload = [
                'id' => null,
                'name' => 'Prompt',
                'provider' => $providerCredential?->provider,
                'provider_name' => $providerCredential?->name,
                'model_name' => $modelName,
            ];
        }

        return [
            'id' => $step->id,
            'order_index' => $step->order_index,
            'status' => $step->status,
            'chain_node' => $chainNodePayload,
            'target_prompt_version_id' => $targetPromptVersionId,
            'target_prompt_template_id' => $targetTemplateId,
            'target_prompt_content' => $targetPromptContent,
            'request_payload' => $step->request_payload,
            'response_raw' => $step->response_raw,
            'parsed_output' => $step->parsed_output,
            'tokens_in' => $step->tokens_in,
            'tokens_out' => $step->tokens_out,
            'duration_ms' => $step->duration_ms,
            'retry_count' => $step->retry_count,
            'retry_reasons' => $step->retry_reasons,
            'validation_errors' => $step->validation_errors,
            'created_at' => $step->created_at,
            /** @phpstan-ignore argument.type */
            'feedback' => $step->feedback->map(function (Feedback $feedback): array {
                return [
                    'id' => $feedback->id,
                    'type' => $feedback->type,
                    'rating' => $feedback->rating,
                    'comment' => $feedback->comment,
                    'suggested_prompt_content' => $feedback->suggested_prompt_content,
                    'analysis' => $feedback->analysis,
                ];
            }),
        ];
    }
}
