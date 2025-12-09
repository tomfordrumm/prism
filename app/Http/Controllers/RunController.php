<?php

namespace App\Http\Controllers;

use App\Http\Requests\Runs\RunChainRequest;
use App\Jobs\ExecuteRunJob;
use App\Models\ChainNode;
use App\Models\Dataset;
use App\Models\Chain;
use App\Models\Feedback;
use App\Models\Project;
use App\Models\ProviderCredential;
use App\Models\Run;
use App\Models\RunStep;
use App\Models\TestCase;
use App\Models\PromptVersion;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RunController extends Controller
{
    public function index(Project $project): Response
    {
        $this->assertProjectTenant($project);

        /** @var \Illuminate\Database\Eloquent\Collection<int, Run> $runsCollection */
        $runsCollection = Run::query()
            ->with(['chain:id,name', 'dataset:id,name', 'testCase:id,name'])
            ->where('project_id', $project->id)
            ->latest()
            ->get();

        $runs = $runsCollection->map(function ($run): array {
            /** @var Run $run */
            /** @var Chain|null $chain = $run->chain */
            $chain = $run->chain;
            /** @var Dataset|null $dataset = $run->dataset */
            $dataset = $run->dataset;
            /** @var \App\Models\TestCase|null $testCase = $run->testCase */
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

        return Inertia::render('projects/runs/Index', [
            'project' => $project->only(['id', 'name', 'description']),
            'runs' => $runs,
        ]);
    }

    public function show(Project $project, Run $run): Response
    {
        $this->assertRunProject($run, $project);

        $run->load([
            'chain:id,name',
            'dataset:id,name',
            'testCase:id,name',
            'steps' => fn ($query) => $query->with(['chainNode.providerCredential', 'feedback'])->orderBy('order_index'),
        ]);

        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\RunStep> $stepsCollection */
        $stepsCollection = $run->steps;

        $targetPromptVersionIds = $stepsCollection
            ->map(fn ($step) => $this->targetPromptVersionId($step->chainNode->messages_config ?? []))
            ->filter()
            ->unique()
            ->values();

        $promptVersions = PromptVersion::query()
            ->whereIn('id', $targetPromptVersionIds)
            ->get(['id', 'prompt_template_id'])
            ->keyBy('id');

        /** @var Chain|null $chain = $run->chain */
        $chain = $run->chain;
        /** @var Dataset|null $dataset = $run->dataset */
        $dataset = $run->dataset;
        /** @var \App\Models\TestCase|null $testCase = $run->testCase */
        $testCase = $run->testCase;

        return Inertia::render('projects/runs/Show', [
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
            /** @phpstan-ignore argument.type */
            'steps' => $stepsCollection->map(function (RunStep $step) use ($promptVersions): array {
                /** @var ChainNode|null $chainNode = $step->chainNode */
                $chainNode = $step->chainNode;
                /** @var ProviderCredential|null $providerCredential = $chainNode?->providerCredential */
                $providerCredential = $chainNode?->providerCredential;

                /** @phpstan-ignore return.type */
                return [
                    'id' => $step->id,
                    'order_index' => $step->order_index,
                    'status' => $step->status,
                    'chain_node' => $chainNode ? [
                        'id' => $chainNode->id,
                        'name' => $chainNode->name,
                        'provider' => $providerCredential?->provider,
                        'provider_name' => $providerCredential?->name,
                        'model_name' => $chainNode->model_name,
                    ] : null,
                    'target_prompt_version_id' => $this->targetPromptVersionId($chainNode ? $chainNode->messages_config ?? [] : []),
                    'target_prompt_template_id' => ($id = $this->targetPromptVersionId($chainNode ? $chainNode->messages_config ?? [] : []))
                        ? $promptVersions->get($id)?->prompt_template_id
                        : null,
                    'request_payload' => $step->request_payload,
                    'response_raw' => $step->response_raw,
                    'parsed_output' => $step->parsed_output,
                    'tokens_in' => $step->tokens_in,
                    'tokens_out' => $step->tokens_out,
                    'duration_ms' => $step->duration_ms,
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
                        ];
                    }),
                ];
            }),
        ]);
    }

    public function run(
        RunChainRequest $request,
        Project $project,
        Chain $chain
    ): RedirectResponse {
        $this->assertChainProject($chain, $project);

        $input = $request->input('input');
        $inputData = $input ? json_decode($input, true) : [];

        $chain->load(['nodes' => fn ($query) => $query->orderBy('order_index')]);

        $run = Run::create([
            'tenant_id' => currentTenantId(),
            'project_id' => $project->id,
            'chain_id' => $chain->id,
            'input' => $inputData ?: [],
            'chain_snapshot' => $this->snapshotChain($chain),
            'status' => 'pending',
            'dataset_id' => null,
            'test_case_id' => null,
            'started_at' => now(),
        ]);

        ExecuteRunJob::dispatch($run->id);

        return redirect()->route('projects.runs.show', [$project, $run]);
    }

    public function runDataset(
        RunChainRequest $request,
        Project $project,
        Chain $chain
    ): RedirectResponse {
        $this->assertChainProject($chain, $project);

        $datasetId = $request->integer('dataset_id');

        $dataset = Dataset::where('id', $datasetId)
            ->where('tenant_id', currentTenantId())
            ->where('project_id', $project->id)
            ->firstOrFail();

        $dataset->load('testCases');

        $chain->load(['nodes' => fn ($query) => $query->orderBy('order_index')]);
        $snapshot = $this->snapshotChain($chain);

        foreach ($dataset->testCases as $testCase) {
            /** @var \App\Models\TestCase $testCase */
            $run = Run::create([
                'tenant_id' => currentTenantId(),
                'project_id' => $project->id,
                'chain_id' => $chain->id,
                'input' => $testCase->input_variables ?? [],
                'chain_snapshot' => $snapshot,
                'status' => 'pending',
                'dataset_id' => $dataset->id,
                'test_case_id' => $testCase->id,
                'started_at' => now(),
            ]);

            ExecuteRunJob::dispatch($run->id);
        }

        return redirect()->route('projects.runs.index', $project);
    }

    private function assertProjectTenant(Project $project): void
    {
        if ($project->tenant_id !== currentTenantId()) {
            abort(404);
        }
    }

    private function assertChainProject(Chain $chain, Project $project): void
    {
        if ($chain->project_id !== $project->id || $chain->tenant_id !== $project->tenant_id) {
            abort(404);
        }
    }

    private function assertRunProject(Run $run, Project $project): void
    {
        if ($run->project_id !== $project->id || $run->tenant_id !== $project->tenant_id) {
            abort(404);
        }
    }

    private function snapshotChain(Chain $chain): array
    {
        return $chain->nodes->map(function ($node) {
            /** @var ChainNode $node */
            return [
                'id' => $node->id,
                'name' => $node->name,
                'provider_credential_id' => $node->provider_credential_id,
                'model_name' => $node->model_name,
                'model_params' => $node->model_params,
                'messages_config' => $node->messages_config,
                'output_schema' => $node->output_schema,
                'stop_on_validation_error' => $node->stop_on_validation_error,
                'order_index' => $node->order_index,
            ];
        })->all();
    }

    private function targetPromptVersionId(array $messagesConfig): ?int
    {
        $system = collect($messagesConfig)->firstWhere('role', 'system');
        if ($system && isset($system['prompt_version_id'])) {
            return (int) $system['prompt_version_id'];
        }

        $user = collect($messagesConfig)->firstWhere('role', 'user');
        if ($user && isset($user['prompt_version_id'])) {
            return (int) $user['prompt_version_id'];
        }

        return null;
    }
}
