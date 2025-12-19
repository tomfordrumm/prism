<?php

namespace App\Http\Controllers;

use App\Http\Requests\Runs\RunChainRequest;
use App\Jobs\ExecuteRunJob;
use App\Models\Dataset;
use App\Models\Chain;
use App\Models\Project;
use App\Models\Run;
use App\Models\TestCase;
use App\Services\Runs\ChainSnapshotLoader;
use App\Services\Runs\RunViewService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RunController extends Controller
{
    public function __construct(
        private RunViewService $runViewService
    ) {
    }

    public function index(Project $project): Response
    {
        $this->assertProjectTenant($project);

        $viewData = $this->runViewService->indexData($project);

        return Inertia::render('projects/runs/Index', $viewData);
    }

    public function show(Project $project, Run $run): Response
    {
        $this->assertRunProject($run, $project);

        $viewData = $this->runViewService->showData($project, $run);

        return Inertia::render('projects/runs/Show', $viewData);
    }

    public function stream(Project $project, Run $run)
    {
        $this->assertRunProject($run, $project);

        return response()->stream(function () use ($project, $run) {
            while (true) {
                $run->refresh();

                $payload = $this->runViewService->runDetails($project, $run);

                echo 'data: '.json_encode([
                    'run' => $payload['run'],
                    'steps' => $payload['steps'],
                ])."\n\n";

                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
                flush();

                if (! in_array($run->status, ['pending', 'running'], true) || connection_aborted()) {
                    break;
                }

                sleep(1);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function run(
        RunChainRequest $request,
        Project $project,
        Chain $chain,
        ChainSnapshotLoader $snapshotLoader
    ): RedirectResponse {
        $this->assertChainProject($chain, $project);

        $input = $request->input('input');
        $inputData = $input ? json_decode($input, true) : [];

        $chain->load(['nodes' => fn ($query) => $query->orderBy('order_index')]);
        $snapshot = $snapshotLoader->createSnapshot($chain);

        $run = Run::create([
            'tenant_id' => currentTenantId(),
            'project_id' => $project->id,
            'chain_id' => $chain->id,
            'input' => $inputData ?: [],
            'chain_snapshot' => $snapshot,
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
        Chain $chain,
        ChainSnapshotLoader $snapshotLoader
    ): RedirectResponse {
        $this->assertChainProject($chain, $project);

        $datasetId = $request->integer('dataset_id');

        $dataset = Dataset::where('id', $datasetId)
            ->where('tenant_id', currentTenantId())
            ->where('project_id', $project->id)
            ->firstOrFail();

        $dataset->load('testCases');

        $chain->load(['nodes' => fn ($query) => $query->orderBy('order_index')]);
        $snapshot = $snapshotLoader->createSnapshot($chain);

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

}
