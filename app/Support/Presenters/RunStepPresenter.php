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
    public function present(RunStep $step, Collection $promptVersions, array $chainSnapshot = []): array
    {
        /** @var ChainNode|null $chainNode */
        $chainNode = $step->chainNode;
        /** @var ProviderCredential|null $providerCredential */
        $providerCredential = $chainNode?->providerCredential ?? $step->providerCredential;

        $messagesConfig = $this->resolveMessagesConfig($step, $chainNode, $chainSnapshot);

        $targetPromptVersionId = $step->prompt_version_id
            ? (int) $step->prompt_version_id
            : ($step->system_prompt_version_id
                ? (int) $step->system_prompt_version_id
                : ($step->user_prompt_version_id ? (int) $step->user_prompt_version_id : null));

        if (! $targetPromptVersionId) {
            $targetPromptVersionId = $this->targetPromptResolver->fromMessagesConfig($messagesConfig);
        }
        $targetTemplateId = null;
        $targetPromptContent = null;
        if ($targetPromptVersionId) {
            $version = $promptVersions->get($targetPromptVersionId) ?? $promptVersions->firstWhere('id', $targetPromptVersionId);
            $targetTemplateId = $version?->prompt_template_id;
            $targetPromptContent = $version?->content;
        }

        $promptTargets = $this->buildPromptTargets($step, $messagesConfig, $promptVersions);

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
            'system_prompt_version_id' => $step->system_prompt_version_id,
            'user_prompt_version_id' => $step->user_prompt_version_id,
            'prompt_targets' => $promptTargets,
            'request_payload' => $step->request_payload,
            'response_raw' => $step->response_raw,
            'response_content' => $step->response_content,
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
                    'target_prompt_version_id' => $feedback->target_prompt_version_id,
                ];
            }),
        ];
    }

    /**
     * @param \Illuminate\Support\Collection<int, \App\Models\PromptVersion> $promptVersions
     */
    private function buildPromptTargets(RunStep $step, array $messagesConfig, Collection $promptVersions): array
    {
        $systemVersionId = $step->system_prompt_version_id
            ? (int) $step->system_prompt_version_id
            : null;
        $userVersionId = $step->user_prompt_version_id
            ? (int) $step->user_prompt_version_id
            : null;

        if (! $systemVersionId) {
            $systemVersionId = $this->targetPromptResolver->fromMessagesConfigForRole($messagesConfig, 'system');
        }

        if (! $userVersionId) {
            if (! $messagesConfig && $step->prompt_version_id) {
                $userVersionId = (int) $step->prompt_version_id;
            } else {
                $userVersionId = $this->targetPromptResolver->fromMessagesConfigForRole($messagesConfig, 'user');
            }
        }

        return [
            'system' => $this->resolvePromptTarget($systemVersionId, $promptVersions),
            'user' => $this->resolvePromptTarget($userVersionId, $promptVersions),
        ];
    }

    /**
     * @param \Illuminate\Support\Collection<int, \App\Models\PromptVersion> $promptVersions
     */
    private function resolvePromptTarget(?int $versionId, Collection $promptVersions): ?array
    {
        if (! $versionId) {
            return null;
        }

        $version = $promptVersions->get($versionId) ?? $promptVersions->firstWhere('id', $versionId);

        if (! $version) {
            return null;
        }

        return [
            'prompt_version_id' => $versionId,
            'prompt_template_id' => $version->prompt_template_id,
            'content' => $version->content,
        ];
    }

    private function resolveMessagesConfig(RunStep $step, ?ChainNode $chainNode, array $chainSnapshot): array
    {
        $snapshotNode = null;
        if ($step->chain_node_id) {
            $snapshotNode = collect($chainSnapshot)->first(function ($node) use ($step) {
                if (! is_array($node)) {
                    return false;
                }

                return isset($node['id']) && (int) $node['id'] === (int) $step->chain_node_id;
            });
        }

        if (! $snapshotNode) {
            $snapshotNode = collect($chainSnapshot)->first(function ($node) use ($step) {
            if (! is_array($node)) {
                return false;
            }

            return isset($node['order_index']) && (int) $node['order_index'] === (int) $step->order_index;
            });
        }

        if (is_array($snapshotNode) && isset($snapshotNode['messages_config']) && is_array($snapshotNode['messages_config'])) {
            return $snapshotNode['messages_config'];
        }

        return $chainNode ? (array) ($chainNode->messages_config ?? []) : [];
    }
}
