<?php

namespace App\Http\Controllers;

use App\Http\Requests\Datasets\StoreDatasetRequest;
use App\Http\Requests\Datasets\UpdateDatasetRequest;
use App\Http\Requests\TestCases\StoreTestCaseRequest;
use App\Http\Requests\TestCases\UpdateTestCaseRequest;
use App\Models\Dataset;
use App\Models\Project;
use App\Models\TestCase;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DatasetController extends Controller
{
    public function index(Project $project): Response
    {
        $this->assertProjectTenant($project);

        $datasets = Dataset::query()
            ->withCount('testCases')
            ->where('project_id', $project->id)
            ->orderBy('name')
            ->get()
            ->map(fn (Dataset $dataset) => [
                'id' => $dataset->id,
                'name' => $dataset->name,
                'description' => $dataset->description,
                'test_cases_count' => $dataset->test_cases_count,
            ]);

        return Inertia::render('projects/datasets/Index', [
            'project' => $project->only(['id', 'uuid', 'name', 'description']),
            'datasets' => $datasets,
        ]);
    }

    public function create(Project $project): Response
    {
        $this->assertProjectTenant($project);

        return Inertia::render('projects/datasets/Create', [
            'project' => $project->only(['id', 'uuid', 'name', 'description']),
        ]);
    }

    public function store(StoreDatasetRequest $request, Project $project): RedirectResponse
    {
        $this->assertProjectTenant($project);

        Dataset::create([
            'tenant_id' => currentTenantId(),
            'project_id' => $project->id,
            'name' => $request->string('name'),
            'description' => $request->input('description'),
        ]);

        return redirect()->route('projects.datasets.index', $project);
    }

    public function show(Project $project, Dataset $dataset): Response
    {
        $this->assertDatasetProject($dataset, $project);

        $dataset->load(['testCases' => fn ($query) => $query->orderBy('name')]);

        /** @var \Illuminate\Database\Eloquent\Collection<int, TestCase> $testCases */
        $testCases = $dataset->testCases;

        return Inertia::render('projects/datasets/Show', [
            'project' => $project->only(['id', 'uuid', 'name', 'description']),
            'dataset' => [
                'id' => $dataset->id,
                'name' => $dataset->name,
                'description' => $dataset->description,
            ],
            'testCases' => $testCases->map(fn (TestCase $testCase) => [
                'id' => $testCase->id,
                'name' => $testCase->name,
                'input_variables' => $testCase->input_variables,
                'tags' => $testCase->tags,
            ]),
        ]);
    }

    public function update(UpdateDatasetRequest $request, Project $project, Dataset $dataset): RedirectResponse
    {
        $this->assertDatasetProject($dataset, $project);

        $dataset->update([
            'name' => $request->string('name'),
            'description' => $request->input('description'),
        ]);

        return redirect()->route('projects.datasets.show', [$project, $dataset]);
    }

    public function destroy(Project $project, Dataset $dataset): RedirectResponse
    {
        $this->assertDatasetProject($dataset, $project);
        $dataset->delete();

        return redirect()->route('projects.datasets.index', $project);
    }

    public function storeTestCase(
        StoreTestCaseRequest $request,
        Project $project,
        Dataset $dataset
    ): RedirectResponse {
        $this->assertDatasetProject($dataset, $project);

        TestCase::create([
            'tenant_id' => currentTenantId(),
            'dataset_id' => $dataset->id,
            'name' => $request->string('name'),
            'input_variables' => $request->input('input_variables'),
            'tags' => $request->input('tags'),
        ]);

        return redirect()->route('projects.datasets.show', [$project, $dataset]);
    }

    public function updateTestCase(
        UpdateTestCaseRequest $request,
        Project $project,
        Dataset $dataset,
        TestCase $testCase
    ): RedirectResponse {
        $this->assertDatasetProject($dataset, $project);
        $this->assertTestCaseDataset($testCase, $dataset);

        $testCase->update([
            'name' => $request->string('name'),
            'input_variables' => $request->input('input_variables'),
            'tags' => $request->input('tags'),
        ]);

        return redirect()->route('projects.datasets.show', [$project, $dataset]);
    }

    public function destroyTestCase(Project $project, Dataset $dataset, TestCase $testCase): RedirectResponse
    {
        $this->assertDatasetProject($dataset, $project);
        $this->assertTestCaseDataset($testCase, $dataset);

        $testCase->delete();

        return redirect()->route('projects.datasets.show', [$project, $dataset]);
    }

    private function assertProjectTenant(Project $project): void
    {
        if ($project->tenant_id !== currentTenantId()) {
            abort(404);
        }
    }

    private function assertDatasetProject(Dataset $dataset, Project $project): void
    {
        $this->assertProjectTenant($project);

        if ($dataset->project_id !== $project->id || $dataset->tenant_id !== $project->tenant_id) {
            abort(404);
        }
    }

    private function assertTestCaseDataset(TestCase $testCase, Dataset $dataset): void
    {
        if ($testCase->dataset_id !== $dataset->id || $testCase->tenant_id !== $dataset->tenant_id) {
            abort(404);
        }
    }
}
