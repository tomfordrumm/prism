<?php

namespace App\Services\Feedback;

use App\Models\PromptVersion;
use App\Models\Run;
use App\Models\RunStep;
use App\Services\Feedback\Exceptions\FeedbackSubmissionException;
use App\Support\TargetPromptResolver;

class FeedbackSubmissionService
{
    public function __construct(
        private PromptImproverService $promptImproverService,
        private TargetPromptResolver $targetPromptResolver
    ) {
    }

    public function submit(
        Run $run,
        RunStep $runStep,
        int $userId,
        string $comment,
        ?int $rating,
        bool $requestSuggestion,
        ?int $providerCredentialId = null,
        ?string $modelName = null,
        ?int $targetPromptVersionId = null
    ): array {
        $runStep->loadMissing('chainNode');

        $suggestionContent = null;
        $analysis = null;
        $resolvedPromptVersion = null;

        if ($requestSuggestion) {
            $promptVersion = $this->resolveTargetPromptVersion($runStep, $targetPromptVersionId);
            $resolvedPromptVersion = $promptVersion;

            $suggestion = $this->promptImproverService->suggest(
                $runStep,
                $promptVersion,
                $comment,
                $providerCredentialId,
                $modelName
            );

            if (! $suggestion) {
                throw new FeedbackSubmissionException('Failed to fetch suggestion.');
            }

            $suggestionContent = $suggestion['suggestion'] ?? null;
            $analysis = $suggestion['analysis'] ?? null;
        } elseif ($targetPromptVersionId) {
            $resolvedPromptVersion = $this->resolveTargetPromptVersion($runStep, $targetPromptVersionId);
        }

        return [
            'tenant_id' => currentTenantId(),
            'run_id' => $run->id,
            'run_step_id' => $runStep->id,
            'user_id' => $userId,
            'type' => $requestSuggestion ? 'llm_suggestion' : 'manual',
            'rating' => $rating,
            'comment' => $comment,
            'suggested_prompt_content' => $suggestionContent,
            'analysis' => $analysis,
            'target_prompt_version_id' => $resolvedPromptVersion?->id,
        ];
    }

    private function resolveTargetPromptVersion(RunStep $runStep, ?int $targetPromptVersionId = null): PromptVersion
    {
        if ($targetPromptVersionId) {
            $promptVersion = PromptVersion::query()
                ->where('tenant_id', currentTenantId())
                ->find($targetPromptVersionId);

            if (! $promptVersion) {
                throw new FeedbackSubmissionException('Prompt version is not available for this step.');
            }

            $allowedTemplateIds = $this->allowedTargetTemplateIds($runStep);

            if ($allowedTemplateIds && ! in_array($promptVersion->prompt_template_id, $allowedTemplateIds, true)) {
                throw new FeedbackSubmissionException('Selected prompt is not available for this step.');
            }

            return $promptVersion;
        }

        $targetPromptVersionId = $this->targetPromptResolver->fromRunStep($runStep);

        if (! $targetPromptVersionId) {
            throw new FeedbackSubmissionException('Cannot request suggestion: no prompt version found for this step.');
        }

        $promptVersion = PromptVersion::query()
            ->where('tenant_id', currentTenantId())
            ->find($targetPromptVersionId);

        if (! $promptVersion) {
            throw new FeedbackSubmissionException('Prompt version is not available for this step.');
        }

        return $promptVersion;
    }

    /**
     * @return array<int, int>
     */
    private function allowedTargetPromptVersionIds(RunStep $runStep): array
    {
        if ($runStep->prompt_version_id) {
            return [(int) $runStep->prompt_version_id];
        }

        $stepVersionIds = collect([
            $runStep->system_prompt_version_id,
            $runStep->user_prompt_version_id,
        ])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($stepVersionIds) {
            return $stepVersionIds;
        }

        $chainNode = $runStep->chainNode;
        $messagesConfig = $chainNode ? (array) ($chainNode->messages_config ?? []) : [];

        return collect([
            $this->targetPromptResolver->fromMessagesConfigForRole($messagesConfig, 'system'),
            $this->targetPromptResolver->fromMessagesConfigForRole($messagesConfig, 'user'),
        ])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function allowedTargetTemplateIds(RunStep $runStep): array
    {
        $templateIds = [];

        $stepVersionIds = collect([
            $runStep->system_prompt_version_id,
            $runStep->user_prompt_version_id,
            $runStep->prompt_version_id,
        ])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        foreach ($stepVersionIds as $versionId) {
            $version = PromptVersion::query()
                ->where('tenant_id', currentTenantId())
                ->find($versionId);
            if ($version) {
                $templateIds[] = (int) $version->prompt_template_id;
            }
        }

        $chainNode = $runStep->chainNode;
        $messagesConfig = $chainNode ? (array) ($chainNode->messages_config ?? []) : [];

        foreach (['system', 'user'] as $role) {
            $message = collect($messagesConfig)->firstWhere('role', $role);
            if (! is_array($message)) {
                continue;
            }

            $templateId = $message['prompt_template_id'] ?? null;
            if ($templateId) {
                $templateIds[] = (int) $templateId;
                continue;
            }

            $versionId = $message['prompt_version_id'] ?? null;
            if ($versionId) {
                $version = PromptVersion::query()
                    ->where('tenant_id', currentTenantId())
                    ->find((int) $versionId);
                if ($version) {
                    $templateIds[] = (int) $version->prompt_template_id;
                }
            }
        }

        return collect($templateIds)->filter()->unique()->values()->all();
    }
}
