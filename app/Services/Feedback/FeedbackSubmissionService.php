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
        ?string $modelName = null
    ): array {
        $runStep->loadMissing('chainNode');

        $suggestionContent = null;
        $analysis = null;

        if ($requestSuggestion) {
            $promptVersion = $this->resolveTargetPromptVersion($runStep);

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
        ];
    }

    private function resolveTargetPromptVersion(RunStep $runStep): PromptVersion
    {
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
}
