<?php

namespace App\Services\Runs;

use App\Models\Dataset;
use App\Models\Chain;
use App\Models\ProviderCredential;
use App\Models\Project;
use App\Models\Run;
use App\Models\RunStep;
use App\Models\TestCase;
use App\Models\Tenant;
use App\Models\PromptVersion;
use App\Services\Llm\ModelCatalog;
use App\Support\Presenters\RunStepPresenter;
use App\Support\TargetPromptResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RunViewService
{
    public function __construct(
        private TargetPromptResolver $targetPromptResolver,
        private RunStepPresenter $runStepPresenter,
        private ModelCatalog $modelCatalog
    ) {
    }

    public function indexData(Project $project): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Run> $runsCollection */
        $runsCollection = Run::query()
            ->with(['chain:id,name', 'dataset:id,name', 'testCase:id,name'])
            ->where('project_id', $project->id)
            ->latest()
            ->get();

        $runs = $runsCollection->map(function (Run $run): array {
            /** @var Chain|null $chain */
            $chain = $run->chain;
            /** @var Dataset|null $dataset */
            $dataset = $run->dataset;
            /** @var TestCase|null $testCase */
            $testCase = $run->testCase;
            $snapshot = is_array($run->chain_snapshot) ? $run->chain_snapshot : [];
            $snapshotName = is_array($snapshot) && isset($snapshot[0]['name']) ? $snapshot[0]['name'] : null;

            return [
                'id' => $run->id,
                'status' => $run->status,
                'chain' => $chain ? [
                    'id' => $chain->id,
                    'name' => $chain->name,
                ] : null,
                'chain_label' => $chain?->name ?? $snapshotName ?? 'Prompt run',
                'dataset' => $dataset ? [
                    'id' => $dataset->id,
                    'name' => $dataset->name,
                ] : null,
                'test_case' => $testCase ? [
                    'id' => $testCase->id,
                    'name' => $testCase->name,
                ] : null,
                'total_tokens_in' => $run->total_tokens_in,
                'total_tokens_out' => $run->total_tokens_out,
                'duration_ms' => $run->duration_ms,
                'created_at' => $run->created_at,
            ];
        });

        return [
            'project' => $project->only(['id', 'uuid', 'name', 'description']),
            'runs' => $runs,
        ];
    }

    public function showData(Project $project, Run $run): array
    {
        $details = $this->runDetails($project, $run);
        $providerCredentials = $this->providerCredentials();
        $tenant = Tenant::query()->find(currentTenantId());

        return [
            ...$details,
            'runHistory' => $this->runHistory($project, $run),
            'providerCredentials' => $this->providerCredentialOptions($providerCredentials),
            'providerCredentialModels' => $this->providerCredentialModels($providerCredentials),
            'improvementDefaults' => [
                'provider_credential_id' => $tenant?->improvement_provider_credential_id,
                'model_name' => $tenant?->improvement_model_name,
            ],
        ];
    }

    public function runDetails(Project $project, Run $run): array
    {
        $run->load([
            'chain:id,name',
            'dataset:id,name',
            'testCase:id,name',
            'steps' => fn ($query) => $query
                ->with(['chainNode.providerCredential', 'providerCredential', 'feedback'])
                ->orderBy('order_index'),
        ]);

        /** @var \Illuminate\Database\Eloquent\Collection<int, RunStep> $stepsCollection */
        $stepsCollection = $run->steps;

        $snapshot = is_array($run->chain_snapshot) ? $run->chain_snapshot : [];

        $stepVersionIds = $stepsCollection
            ->flatMap(fn (RunStep $step) => [
                $step->prompt_version_id,
                $step->system_prompt_version_id,
                $step->user_prompt_version_id,
            ])
            ->filter()
            ->unique()
            ->values()
            ->all();

        $targetPromptVersionIds = $stepVersionIds
            ?: $this->targetPromptResolver->collectTargetVersionIdsFromSnapshot($snapshot);

        $promptVersions = PromptVersion::query()
            ->whereIn('id', $targetPromptVersionIds)
            ->get(['id', 'prompt_template_id', 'content'])
            ->keyBy('id');

        /** @var Chain|null $chain */
        $chain = $run->chain;
        /** @var Dataset|null $dataset */
        $dataset = $run->dataset;
        /** @var TestCase|null $testCase */
        $testCase = $run->testCase;
        $snapshotName = is_array($snapshot) && isset($snapshot[0]['name']) ? $snapshot[0]['name'] : null;

        return [
            'project' => $project->only(['id', 'uuid', 'name', 'description']),
            'run' => [
                'id' => $run->id,
                'status' => $run->status,
                'chain' => $chain ? [
                    'id' => $chain->id,
                    'name' => $chain->name,
                ] : null,
                'chain_label' => $chain?->name ?? $snapshotName ?? 'Prompt run',
                'dataset' => $dataset ? [
                    'id' => $dataset->id,
                    'name' => $dataset->name,
                ] : null,
                'test_case' => $testCase ? [
                    'id' => $testCase->id,
                    'name' => $testCase->name,
                ] : null,
                'input' => $run->input,
                'chain_snapshot' => $run->chain_snapshot,
                'total_tokens_in' => $run->total_tokens_in,
                'total_tokens_out' => $run->total_tokens_out,
                'total_cost' => $run->total_cost,
                'duration_ms' => $run->duration_ms,
                'created_at' => $run->created_at,
                'finished_at' => $run->finished_at,
            ],
            'steps' => $this->presentSteps($stepsCollection, $promptVersions, $snapshot),
        ];
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, RunStep> $steps
     * @param Collection<int, PromptVersion> $promptVersions
     */
    private function presentSteps(Collection $steps, Collection $promptVersions, array $snapshot): array
    {
        return $steps
            ->map(fn (RunStep $step): array => $this->runStepPresenter->present($step, $promptVersions, $snapshot))
            ->values()
            ->all();
    }

    private function providerCredentialOptions(Collection $providerCredentials): array
    {
        return $providerCredentials
            ->map(fn (ProviderCredential $credential) => [
                'value' => $credential->id,
                'label' => sprintf('%s (%s)', $credential->name, $credential->provider),
                'provider' => $credential->provider,
            ])
            ->all();
    }

    private function providerCredentialModels(Collection $providerCredentials): array
    {
        return $providerCredentials
            ->mapWithKeys(fn (ProviderCredential $credential) => [
                $credential->id => $this->modelCatalog->getModelsFor($credential),
            ])
            ->all();
    }

    private function providerCredentials(): Collection
    {
        return ProviderCredential::query()
            ->where('tenant_id', currentTenantId())
            ->orderBy('name')
            ->get(['id', 'name', 'provider', 'encrypted_api_key', 'metadata']);
    }

    private function runHistory(Project $project, Run $run): array
    {
        $query = Run::query()
            ->where('project_id', $project->id);

        if ($run->chain_id) {
            $query->where('chain_id', $run->chain_id);
        } else {
            $query->whereNull('chain_id');
        }

        if (! $run->chain_id) {
            $promptTemplateId = $this->promptTemplateIdFromSnapshot($run->chain_snapshot);
            if ($promptTemplateId) {
                $query->where('chain_snapshot->0->prompt_template_id', $promptTemplateId);
            }
        }

        $runs = $query
            ->orderByDesc('created_at')
            ->with(['latestStep' => function ($query): void {
                $query->select(
                    'run_steps.id',
                    'run_steps.run_id',
                    'run_steps.order_index',
                    'run_steps.response_raw',
                    'run_steps.parsed_output'
                );
            }])
            ->take(10)
            ->get(['id', 'chain_id', 'status', 'duration_ms', 'total_tokens_in', 'total_tokens_out', 'created_at']);

        return $runs
            ->map(function (Run $item) use ($project): array {
                $snippet = $this->runFinalSnippet($item->latestStep);

                return [
                    'id' => $item->id,
                    'status' => $item->status,
                    'duration_ms' => $item->duration_ms,
                    'total_tokens_in' => $item->total_tokens_in,
                    'total_tokens_out' => $item->total_tokens_out,
                    'created_at' => $item->created_at?->toIso8601String(),
                    'href' => route('projects.runs.show', [$project, $item]),
                    'final_snippet' => $snippet,
                ];
            })
            ->values()
            ->all();
    }

    private function promptTemplateIdFromSnapshot(mixed $snapshot): ?int
    {
        if (! is_array($snapshot) || $snapshot === []) {
            return null;
        }

        $first = $snapshot[0] ?? null;
        if (! is_array($first)) {
            return null;
        }

        $value = $first['prompt_template_id'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    private function runFinalSnippet(?RunStep $step): ?string
    {
        if (! $step) {
            return null;
        }

        $raw = $step->response_raw;
        $content = data_get($raw, 'choices.0.message.content')
            ?? data_get($raw, 'choices.0.content')
            ?? data_get($raw, 'message.content')
            ?? data_get($raw, 'content');

        if (is_array($content)) {
            $content = collect($content)
                ->pluck('text')
                ->filter()
                ->implode(' ');
        }

        if (! $content && $step->parsed_output) {
            $content = json_encode($step->parsed_output);
        }

        if (! is_string($content)) {
            return null;
        }

        $clean = trim(preg_replace('/\s+/', ' ', $content) ?? '');

        return $clean !== '' ? Str::limit($clean, 140) : null;
    }
}
