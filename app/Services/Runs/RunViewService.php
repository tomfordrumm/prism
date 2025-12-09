<?php

namespace App\Services\Runs;

use App\Models\Dataset;
use App\Models\Chain;
use App\Models\Project;
use App\Models\Run;
use App\Models\RunStep;
use App\Models\TestCase;
use App\Models\PromptVersion;
use App\Support\Presenters\RunStepPresenter;
use App\Support\TargetPromptResolver;
use Illuminate\Support\Collection;

class RunViewService
{
    public function __construct(
        private TargetPromptResolver $targetPromptResolver,
        private RunStepPresenter $runStepPresenter
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

            return [
                'id' => $run->id,
                'status' => $run->status,
                'chain' => $chain ? [
                    'id' => $chain->id,
                    'name' => $chain->name,
                ] : null,
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
            'project' => $project->only(['id', 'name', 'description']),
            'runs' => $runs,
        ];
    }

    public function showData(Project $project, Run $run): array
    {
        $run->load([
            'chain:id,name',
            'dataset:id,name',
            'testCase:id,name',
            'steps' => fn ($query) => $query->with(['chainNode.providerCredential', 'feedback'])->orderBy('order_index'),
        ]);

        /** @var \Illuminate\Database\Eloquent\Collection<int, RunStep> $stepsCollection */
        $stepsCollection = $run->steps;

        $targetPromptVersionIds = $this->targetPromptResolver->collectTargetVersionIds($stepsCollection);

        $promptVersions = PromptVersion::query()
            ->whereIn('id', $targetPromptVersionIds)
            ->get(['id', 'prompt_template_id'])
            ->keyBy('id');

        /** @var Chain|null $chain */
        $chain = $run->chain;
        /** @var Dataset|null $dataset */
        $dataset = $run->dataset;
        /** @var TestCase|null $testCase */
        $testCase = $run->testCase;

        return [
            'project' => $project->only(['id', 'name', 'description']),
            'run' => [
                'id' => $run->id,
                'status' => $run->status,
                'chain' => $chain ? [
                    'id' => $chain->id,
                    'name' => $chain->name,
                ] : null,
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
                'duration_ms' => $run->duration_ms,
                'created_at' => $run->created_at,
                'finished_at' => $run->finished_at,
            ],
            'steps' => $this->presentSteps($stepsCollection, $promptVersions),
        ];
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, RunStep> $steps
     * @param Collection<int, PromptVersion> $promptVersions
     */
    private function presentSteps(Collection $steps, Collection $promptVersions): array
    {
        return $steps
            ->map(fn (RunStep $step): array => $this->runStepPresenter->present($step, $promptVersions))
            ->values()
            ->all();
    }
}
