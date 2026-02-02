<?php

namespace App\Services\Prompts;

use App\Models\Project;
use App\Models\PromptConversation;
use App\Models\PromptMessage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PromptConversationService
{
    public function getOrCreate(Project $project, array $attributes): PromptConversation
    {
        $type = $attributes['type'] ?? null;
        if ($type instanceof \Stringable) {
            $type = (string) $type;
        }
        if (! is_string($type) || $type === '') {
            throw new RuntimeException('Conversation type is required.');
        }

        $runId = $attributes['run_id'] ?? null;
        $runStepId = $attributes['run_step_id'] ?? null;
        $targetPromptVersionId = $attributes['target_prompt_version_id'] ?? null;

        return PromptConversation::query()
            ->where('tenant_id', currentTenantId())
            ->where('project_id', $project->id)
            ->where('type', $type)
            ->where('status', 'active')
            ->when($runId, fn ($query) => $query->where('run_id', $runId))
            ->when(! $runId, fn ($query) => $query->whereNull('run_id'))
            ->when($runStepId, fn ($query) => $query->where('run_step_id', $runStepId))
            ->when(! $runStepId, fn ($query) => $query->whereNull('run_step_id'))
            ->when($targetPromptVersionId, fn ($query) => $query->where('target_prompt_version_id', $targetPromptVersionId))
            ->when(! $targetPromptVersionId, fn ($query) => $query->whereNull('target_prompt_version_id'))
            ->firstOr(function () use ($project, $type, $runId, $runStepId, $targetPromptVersionId) {
                return PromptConversation::create([
                    'tenant_id' => currentTenantId(),
                    'project_id' => $project->id,
                    'type' => $type,
                    'run_id' => $runId,
                    'run_step_id' => $runStepId,
                    'target_prompt_version_id' => $targetPromptVersionId,
                    'status' => 'active',
                ]);
            });
    }

    public function messages(PromptConversation $conversation): Collection
    {
        $this->assertTenantAccess($conversation);

        return $conversation
            ->messages()
            ->orderBy('created_at')
            ->get();
    }

    public function appendMessage(
        PromptConversation $conversation,
        string $role,
        string $content,
        array $meta = []
    ): PromptMessage {
        $this->assertTenantAccess($conversation);

        return DB::transaction(function () use ($conversation, $role, $content, $meta) {
            return $conversation->messages()->create([
                'role' => $role,
                'content' => $content,
                'meta' => $meta ?: null,
            ]);
        });
    }

    private function assertTenantAccess(PromptConversation $conversation): void
    {
        if ($conversation->tenant_id !== currentTenantId()) {
            throw new RuntimeException('Conversation does not belong to the current tenant.');
        }
    }
}
