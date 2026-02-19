<?php

namespace App\Actions\Runs;

use App\Actions\Prompts\RunPromptTemplateAction;
use App\Jobs\ExecuteRunJob;
use App\Models\Chain;
use App\Models\Dataset;
use App\Models\Project;
use App\Models\PromptTemplate;
use App\Models\ProviderCredential;
use App\Models\Run;
use App\Models\TestCase;
use App\Services\Entitlements\EntitlementEnforcer;
use App\Services\Runs\ChainSnapshotLoader;

class RerunRunAction
{
    public function __construct(
        private RunPromptTemplateAction $promptRunner,
        private ChainSnapshotLoader $snapshotLoader,
        private EntitlementEnforcer $entitlementEnforcer
    ) {}

    /**
     * @return array{type: 'single'|'dataset', run: ?Run}
     */
    public function execute(Project $project, Run $run): array
    {
        return $run->chain_id
            ? $this->rerunChain($project, $run)
            : $this->rerunPrompt($project, $run);
    }

    /**
     * @return array{type: 'single'|'dataset', run: ?Run}
     */
    private function rerunChain(Project $project, Run $run): array
    {
        $tenantId = currentTenantId();
        if ($tenantId === null) {
            abort(403);
        }

        $chain = Chain::query()
            ->where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->findOrFail($run->chain_id);

        $snapshot = is_array($run->chain_snapshot) && $run->chain_snapshot !== []
            ? $run->chain_snapshot
            : $this->snapshotLoader->createSnapshot($chain);

        if ($run->dataset_id) {
            $dataset = Dataset::query()
                ->where('tenant_id', $tenantId)
                ->where('project_id', $project->id)
                ->findOrFail($run->dataset_id);

            $dataset->load('testCases');
            $this->entitlementEnforcer->ensureCanRunChain(
                tenantId: $tenantId,
                requestedRuns: $dataset->testCases->count(),
            );

            foreach ($dataset->testCases as $testCase) {
                /** @var TestCase $testCase */
                $newRun = Run::create([
                    'tenant_id' => $tenantId,
                    'project_id' => $project->id,
                    'chain_id' => $chain->id,
                    'input' => $testCase->input_variables ?? [],
                    'chain_snapshot' => $snapshot,
                    'status' => 'pending',
                    'dataset_id' => $dataset->id,
                    'test_case_id' => $testCase->id,
                    'started_at' => now(),
                ]);

                ExecuteRunJob::dispatch($newRun->id);
            }

            return ['type' => 'dataset', 'run' => null];
        }

        $this->entitlementEnforcer->ensureCanRunChain($tenantId);

        $newRun = Run::create([
            'tenant_id' => $tenantId,
            'project_id' => $project->id,
            'chain_id' => $chain->id,
            'input' => $run->input ?? [],
            'chain_snapshot' => $snapshot,
            'status' => 'pending',
            'dataset_id' => null,
            'test_case_id' => null,
            'started_at' => now(),
        ]);

        ExecuteRunJob::dispatch($newRun->id);

        return ['type' => 'single', 'run' => $newRun];
    }

    /**
     * @return array{type: 'single'|'dataset', run: ?Run}
     */
    private function rerunPrompt(Project $project, Run $run): array
    {
        $tenantId = currentTenantId();
        if ($tenantId === null) {
            abort(403);
        }

        $snapshot = is_array($run->chain_snapshot) ? $run->chain_snapshot : [];
        $node = $snapshot[0] ?? [];

        $promptTemplateId = $node['prompt_template_id'] ?? null;
        $providerCredentialId = $node['provider_credential_id'] ?? null;
        $modelName = $node['model_name'] ?? null;

        if (! $promptTemplateId || ! $providerCredentialId || ! $modelName) {
            abort(422, 'Prompt run snapshot is missing model configuration.');
        }

        $promptTemplate = PromptTemplate::query()
            ->where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->findOrFail($promptTemplateId);

        $providerCredential = ProviderCredential::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($providerCredentialId);

        if ($run->dataset_id) {
            $dataset = Dataset::query()
                ->where('tenant_id', $tenantId)
                ->where('project_id', $project->id)
                ->findOrFail($run->dataset_id);

            $dataset->load('testCases');
            $this->entitlementEnforcer->ensureCanRunChain(
                tenantId: $tenantId,
                requestedRuns: $dataset->testCases->count(),
            );

            foreach ($dataset->testCases as $testCase) {
                /** @var TestCase $testCase */
                $variables = $testCase->input_variables ?? [];

                $newRun = $this->promptRunner->run(
                    $project,
                    $promptTemplate,
                    $providerCredential,
                    $modelName,
                    is_array($variables) ? $variables : [],
                    $dataset->id,
                    $testCase->id
                );

                ExecuteRunJob::dispatch($newRun->id);
            }

            return ['type' => 'dataset', 'run' => null];
        }

        $variables = $run->input ?? [];
        $this->entitlementEnforcer->ensureCanRunChain($tenantId);

        $newRun = $this->promptRunner->run(
            $project,
            $promptTemplate,
            $providerCredential,
            $modelName,
            is_array($variables) ? $variables : []
        );

        ExecuteRunJob::dispatch($newRun->id);

        return ['type' => 'single', 'run' => $newRun];
    }
}
